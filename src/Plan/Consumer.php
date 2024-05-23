<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan;

use Monolog\Logger;
use React\EventLoop\Loop;
use function React\Promise\{
    all,
    reject,
    resolve,
};
use React\Promise\{
    Deferred,
    PromiseInterface
};

use RTCKit\ESL;
use RTCKit\FiCore\Exception\HangupException;
use RTCKit\FiCore\Rest\Controller\V0_1\Call;
use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Signal\Conference as ConferenceSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    EventEnum,
    HangupCauseEnum,
    StatusEnum
};
use RTCKit\FiCore\{
    AbstractApp,
    Config,
};
use RTCKit\React\ESL\RemoteOutboundClient;
use RTCKit\SIP\Exception\SIPException;
use RTCKit\SIP\Header\NameAddrHeader;

use stdClass as Event;

class Consumer extends AbstractConsumer
{
    /** @var list<string> */
    public const WAIT_FOR_APPLICATIONS = [
        'playback',
        'record',
        'play_and_get_digits',
        'bridge',
        'say',
        'sleep',
        'speak',
        'conference',
        'park',
    ];

    protected string $subscribedEvents = 'json CUSTOM conference::maintenance';

    protected AbstractApp $app;

    /** @var array<string, HandlerInterface> */
    public array $handlers = [];

    public Logger $logger;

    public function setApp(AbstractApp $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function setElementHandler(string $elementClass, HandlerInterface $handler): static
    {
        $this->handlers[$elementClass] = $handler;

        $this->handlers[$elementClass]->setApp($this->app);

        return $this;
    }

    public function run(): void
    {
        $this->logger = $this->app->createLogger('plan.consumer');

        $this->logger->debug('Starting ...');
    }

    public function onConnect(RemoteOutboundClient $client, ESL\Response\CommandReply $response): void
    {
        $context = $response->getHeaders();

        $core = $this->app->getCore($context['core-uuid']);

        if (!isset($core)) {
            $this->logger->warning("Cannot accept connection from unknown core '{$context['core-uuid']}'");
            $client->close();

            return;
        }

        $outbound = $context['call-direction'] === 'outbound';
        $prefix = $this->app->config->appPrefix ?? '';

        if (isset($context["variable_{$prefix}_transfer_seq"]) && strlen($context["variable_{$prefix}_transfer_seq"])) {
            $targetSequence = urldecode($context["variable_{$prefix}_transfer_seq"]);

            $this->logger->info('Using TransferSequence ' . $targetSequence);
        } else if (isset($context["variable_{$prefix}_answer_seq"]) && strlen($context["variable_{$prefix}_answer_seq"])) {
            $targetSequence = urldecode($context["variable_{$prefix}_answer_seq"]);

            $this->logger->info('Using AnswerSequence ' . $targetSequence);
        } else if (!$outbound && !empty($this->app->config->defaultAnswerSequence)) {
            $targetSequence = $this->app->config->defaultAnswerSequence;

            $this->logger->info('Using DefaultAnswerSequence ' . $targetSequence);
        } else {
            $this->logger->error('missing answer sequence');
            $client->close();

            return;
        }

        $channel = $core->getChannel($context['channel-unique-id']);

        if (!isset($channel)) {
            $channel = new Channel();
            $channel->context = $context;
            $channel->uuid = $channel->context['channel-unique-id'];

            $core->addChannel($channel);
        } else {
            $channel->context = $context;
        }

        $channel->client = $client;
        $channel->coreUuid = $channel->context['core-uuid'];
        $channel->callerName = urldecode($channel->context['caller-caller-id-name'] ?? '');
        $channel->outbound = $outbound;
        $channel->targetSequence = $targetSequence;

        $channel->client->resume();
        $channel->client->linger();
        $channel->client->myEvents('json');
        $channel->client->divertEvents('on');
        $channel->client->event($this->subscribedEvents);
        $channel->client->sendMsg(
            (new ESL\Request\SendMsg())
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'multiset')
                ->setHeader('execute-app-arg', $prefix . '_app=true hangup_after_bridge=false')
                ->setHeader('event-lock', 'true')
        );

        $channel->client->on('event', function (ESL\Response\TextEventJson $response) use ($channel): void {
            $event = json_decode($response->getBody() ?? '');
            assert($event instanceof Event);

            try {
                $this->onEvent($channel, $event);
            } catch (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->logger->error('Processing outbound event failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            }
        });

        $tagPrefix = "variable_{$prefix}_tag_";
        $tagPrefixLength = strlen($tagPrefix);

        foreach ($channel->context as $key => $value) {
            if (strpos($key, $tagPrefix) === 0) {
                $channel->tags[substr($key, $tagPrefixLength)] = $value;
            }
        }

        if ($channel->outbound) {
            if (isset($channel->context["variable_{$prefix}_request_uuid"])) {
                $originateJob = $core->getOriginateJob($channel->context["variable_{$prefix}_request_uuid"]);

                if (isset($originateJob)) {
                    $originateJob->channel = $channel;
                    $channel->tags = $originateJob->tags;
                }
            }

            $channel->calledNumber = urldecode(
                $channel->context["variable_{$prefix}_to"]
                ?? $channel->context['caller-destination-number']
                ?? ''
            );
            $channel->callerNumber = urldecode(
                $channel->context["variable_{$prefix}_from"]
                ?? $channel->context['caller-caller-id-number']
                ?? ''
            );

            $channel->aLegUuid = $channel->context['caller-unique-id'];
            $channel->aLegRequestUuid = $channel->context["variable_{$prefix}_request_uuid"];

            if (
                isset($channel->context["variable_{$prefix}_sched_hangup_id"]) &&
                strlen($channel->context["variable_{$prefix}_sched_hangup_id"])
            ) {
                $channel->schedHangupId = $channel->context["variable_{$prefix}_sched_hangup_id"];
            }

            $channel->status = StatusEnum::InProgress;
            $channel->answered = true;
        } else {
            $channel->calledNumber = urldecode(
                $channel->context["variable_{$prefix}_destination_number"]
                ?? $channel->context['caller-destination-number']
                ?? ''
            );
            $channel->callerNumber = urldecode($channel->context['caller-caller-id-number'] ?? '');

            if (isset($channel->context['variable_sip_h_Diversion'])) {
                try {
                    $diversion = NameAddrHeader::parse([$channel->context['variable_sip_h_Diversion']]);

                    if (isset($diversion->uri, $diversion->uri->user)) {
                        $channel->forwardedFrom = ltrim($diversion->uri->user, '+');
                    }
                } catch (SIPException $e) {
                    $this->logger->error("Cannot parse Diversion SIP header '{$channel->context['variable_sip_h_Diversion']}'");
                }
            }

            if (isset($channel->context["{$prefix}_sched_hangup_id"]) && strlen($channel->context["{$prefix}_sched_hangup_id"])) {
                $channel->schedHangupId = $channel->context["variable_{$prefix}_sched_hangup_id"];
            }

            $channel->status = StatusEnum::Ringing;
        }

        $channel->to = ltrim($channel->calledNumber, '+');
        $channel->from = ltrim($channel->callerNumber, '+');

        if (isset($channel->schedHangupId)) {
            $channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'unset')
                    ->setHeader('execute-app-arg', "{$prefix}_sched_hangup_id")
                    ->setHeader('event-lock', 'true')
            );
        }

        $this->logger->info('Processing Call');

        /* Check if AMD is enabled */
        if (
            isset($context["variable_{$prefix}_amd"]) &&
            ($context["variable_{$prefix}_amd"] === 'on')
        ) {
            /* If synchronous, call flow execution must be blocked until AMD resolution */
            if (
                isset($context["variable_{$prefix}_amd_async"]) &&
                ($context["variable_{$prefix}_amd_async"] === 'off')
            ) {
                $amdTimeoutAdj = (int)$context["variable_{$prefix}_amd_timeout"] + 1;

                $this->logger->info('Activating synchronous AMD');

                all([
                    $channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'set')
                            ->setHeader('execute-app-arg', "{$prefix}_amd_seq={$channel->targetSequence}")
                            ->setHeader('event-lock', 'true')
                    ),
                    $channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'playback')
                            ->setHeader('execute-app-arg', 'file_string://silence_stream://' . ($amdTimeoutAdj * 1000))
                    ),
                ]);

                return;
            } else {
                $this->logger->info('Activating asynchronous AMD');
            }
        }

        $this->consumeEntryPoint($channel, $channel->targetSequence);
    }

    /**
     * Fetches then executes the initial plan sequence for a given channel
     *
     * @param Channel $channel
     * @param string $sequence
     * @param ?AbstractSignal $signal
     *
     * @throws HangupException
     *
     * @return PromiseInterface
     */
    public function consumeEntryPoint(Channel $channel, string $sequence, ?AbstractSignal $signal = null): PromiseInterface
    {
        return $this->consume($channel, $sequence, $signal)
            ->then(function () use ($channel): PromiseInterface {
                if (isset($channel->hangupCause)) {
                    throw new HangupException();
                }

                return $this->getVariable($channel, "{$this->app->config->appPrefix}_transfer_progress");
            })
            ->then(function (?string $transferProgress) use ($channel): void {
                if (!isset($channel->hangupCause)) {
                    if (!is_null($transferProgress)) {
                        $this->logger->info('Transfer In Progress!');
                    } else {
                        $this->logger->info('No more Elements, Hangup Now!');
                        $channel->status = StatusEnum::Completed;
                        $channel->hangupCause = HangupCauseEnum::NORMAL_CLEARING;

                        $this->hangup($channel);
                    }

                    $this->logger->info('Reached end of plan');
                }
            })
            ->catch(function (HangupException $t) {
                $this->logger->warning('Channel has hung up, breaking Processing Call', [$t->getMessage()]);
            })
            ->catch(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->logger->error('Processing call failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            })
            ->finally(function () use ($channel) {
                $this->disconnect($channel);
                $this->logger->info('Processing Call Ended');
            });
    }

    public function onEvent(Channel $channel, Event $event): void
    {
        if (!isset($event->{'Event-Name'})) {
            return;
        }

        switch ($event->{'Event-Name'}) {
            case EventEnum::CHANNEL_EXECUTE_COMPLETE->value:
                if (in_array($event->Application, self::WAIT_FOR_APPLICATIONS)) {
                    $transferProgress = "variable_{$this->app->config->appPrefix}_transfer_progress";

                    if (isset($event->{$transferProgress}) && ($event->{$transferProgress} === 'true')) {
                        $this->pushToEventQueue($channel);
                    } else {
                        $this->pushToEventQueue($channel, $event);
                    }
                }
                break;

            case EventEnum::CHANNEL_HANGUP_COMPLETE->value:
                $channel->hangupCause = HangupCauseEnum::from($event->{'Hangup-Cause'});
                $channel->status = StatusEnum::Completed;

                $this->logger->info("Event: channel {$channel->uuid} has hung up ({$event->{'Hangup-Cause'}})");
                $this->pushToEventQueue($channel);
                break;

            case EventEnum::DETECTED_SPEECH->value:
                if (get_class($channel->currentElement) === CaptureSpeech\Element::class) {
                    $this->pushToEventQueue($channel, $event);
                }
                break;

            case EventEnum::CUSTOM->value:
                if (!empty($channel->currentElement)) {
                    switch (get_class($channel->currentElement)) {
                        case Conference\Element::class:
                            if (($event->{'Event-Subclass'} === 'conference::maintenance') && ($event->{'Unique-ID'} === $channel->uuid)) {
                                switch ($event->Action) {
                                    case 'add-member':
                                        $this->logger->debug('Entered Conference');
                                        $this->pushToEventQueue($channel, $event);
                                        break;

                                    case 'kick':
                                        if (isset($event->{'Conference-Name'}, $event->{'Member-ID'})) {
                                            $channel->client->bgApi(
                                                (new ESL\Request\BgApi())
                                                    ->setParameters("conference {$event->{'Conference-Name'}} kick {$event->{'Member-ID'}}")
                                            );
                                            $this->logger->warning("Conference Room {$event->{'Conference-Name'}}, member {$event->{'Member-ID'}} pressed '*', kicked now!");
                                        }
                                        break;

                                    case 'digits-match':
                                        $this->logger->debug('Digits match on Conference');

                                        if (isset($event->{'Signal-Attn'})) {
                                            $conference = $channel->core->getConference($event->{'Conference-Unique-ID'} ?? '');

                                            if (isset($conference)) {
                                                $dtmfSignal = new ConferenceSignal\DTMF();
                                                $dtmfSignal->attn = $event->{'Signal-Attn'};
                                                $dtmfSignal->channel = $channel;
                                                $dtmfSignal->conference = $conference;
                                                $dtmfSignal->member = isset($event->{'Member-ID'}) ? (int)$event->{'Member-ID'} : 0;
                                                $dtmfSignal->tones = isset($event->{'Digits-Match'}) ? $event->{'Digits-Match'} : '';

                                                $this->app->signalProducer->produce($dtmfSignal);
                                            }
                                        }
                                        break;

                                    case 'floor-change':
                                        if (isset($event->Speak) && ($event->Speak === 'true')) {
                                            $this->pushToEventQueue($channel, $event);
                                        }
                                        break;
                                }
                            }
                            break;
                    }
                }
                break;
        }
    }

    public function waitForEvent(Channel $channel, int $timeout = 3600, bool $raiseExceptionOnHangup = false): PromiseInterface
    {
        $channel->raiseExceptionOnHangup = $raiseExceptionOnHangup;

        $this->logger->debug('wait for action start');

        if (!empty($channel->eventQueue)) {
            $event = array_shift($channel->eventQueue);

            $this->logger->debug('wait for action end ' . json_encode($event));

            if (isset($channel->hangupCause) && $channel->raiseExceptionOnHangup) {
                $this->logger->warning('wait for action call hung up!');

                throw new HangupException();
            }

            return resolve($event);
        }

        $channel->eventQueueDeferred = new Deferred();
        $channel->eventQueueTimer = Loop::addTimer($timeout, function () use ($channel): void {
            $deferred = $channel->eventQueueDeferred;
            unset($channel->eventQueueDeferred);

            if (isset($channel->hangupCause) && $channel->raiseExceptionOnHangup) {
                $this->logger->warning('wait for action call hung up!');
                $deferred->reject(new HangupException());
            } else {
                $this->logger->debug('wait for action end timed out!');
                $deferred->resolve(null);
            }
        });

        return $channel->eventQueueDeferred->promise();
    }

    public function pushToEventQueue(Channel $channel, ?Event $event = null): void
    {
        if (isset($channel->eventQueueDeferred)) {
            $this->logger->debug('wait for action end ' . json_encode($event));

            if (isset($channel->eventQueueTimer)) {
                Loop::cancelTimer($channel->eventQueueTimer);
                unset($channel->eventQueueTimer);
            }

            $deferred = $channel->eventQueueDeferred;

            unset($channel->eventQueueDeferred);

            if (isset($channel->hangupCause) && $channel->raiseExceptionOnHangup) {
                $this->logger->warning('wait for action call hung up!');

                throw new HangupException();
            }

            $deferred->resolve($event);
        } else {
            $channel->eventQueue[] = $event;
        }
    }

    /**
     * Fetches then executes a plan sequence
     *
     * @param Channel $channel
     * @param string $sequence
     * @param ?AbstractSignal $signal
     *
     * @throws HangupException
     *
     * @return PromiseInterface
     */
    public function consume(Channel $channel, string $sequence, ?AbstractSignal $signal = null): PromiseInterface
    {
        return $this->app->planProducer->produce($channel, $sequence, $signal)
            ->then(function (?array $elements) use ($channel): PromiseInterface {
                /** @var list<AbstractElement> $elements */
                if (empty($elements)) {
                    return resolve(null);
                }

                if (isset($channel->hangupCause)) {
                    throw new HangupException();
                }

                $channel->elements = $elements;

                return $this->rewindExecuteSequence($channel);
            })
            ->catch(function (HangupException $t) {
                $this->logger->warning('Channel has hung up, breaking Processing Call', [$t->getMessage()]);
            })
            ->catch(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->logger->error('Processing call failure: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }

    public function rewindExecuteSequence(Channel $channel): PromiseInterface
    {
        reset($channel->elements);

        return $this->executeSequence($channel);
    }

    public function executeSequence(Channel $channel): PromiseInterface
    {
        if (!count($channel->elements)) {
            return resolve(null);
        }

        $element = array_shift($channel->elements);

        return $this->executeElement($channel, $element)
            ->then(function (?bool $break = null) use ($channel): PromiseInterface {
                $elementType = get_class($channel->currentElement);

                $this->logger->info("[{$elementType}] Done");

                if ($break) {
                    return resolve(null);
                }

                if (isset($channel->transferInProgress)) {
                    $this->logger->info("Transfer in progress, breaking redirect");

                    return resolve(null);
                }

                return $this->executeSequence($channel);
            })
            ->catch(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->logger->error('Plan exception: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }

    public function executeElement(Channel $channel, AbstractElement $element): PromiseInterface
    {
        if (!$channel->outbound && !$channel->answered && !$channel->preAnswer && !$element::NO_ANSWER) {
            $elementType = get_class($element);
            $this->logger->debug("{$elementType} requires explicit answer");

            $promise = $channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'answer')
                    ->setHeader('event-lock', 'true')
            )->then(function () use ($channel) {
                $channel->answered = true;
                $channel->status = StatusEnum::InProgress;

                return resolve(null);
            });
        } else {
            $promise = resolve(null);
        }

        $handler = $this->handlers[$element::class];
        $channel->currentElement = $element;

        return $promise
            ->then(function () use ($channel, $handler, $element): PromiseInterface {
                return $handler->execute($channel, $element);
            });
    }

    public function getVariable(Channel $channel, string $variable): PromiseInterface
    {
        return $channel->client->api(
            (new ESL\Request\Api())->setParameters("uuid_getvar {$channel->uuid} {$variable}")
        )
            ->then(function (ESL\Response $response): PromiseInterface {
                if (!($response instanceof ESL\Response\ApiResponse)) {
                    return resolve(null);
                }

                $body = $response->getBody();

                if (is_null($body) || !strlen($body)) {
                    return resolve(null);
                }

                if (($body === '_undef_') || (strpos($body, '-ERR') === 0)) {
                    return resolve(null);
                }

                return resolve($body);
            });
    }

    protected function hangup(Channel $channel): PromiseInterface
    {
        return $channel->client->sendMsg(
            (new ESL\Request\SendMsg())
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'hangup')
                ->setHeader('execute-app-arg', !isset($channel->hangupCause) ? '' : $channel->hangupCause->value)
                ->setHeader('event-lock', 'true')
        )
            ->then(function (ESL\Response\CommandReply $response): PromiseInterface {
                return resolve($response->isSuccessful());
            });
    }

    protected function disconnect(Channel $channel): void
    {
        $channel->client->close();
    }
}
