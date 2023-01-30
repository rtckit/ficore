<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Conference;

use function React\Promise\{
    all,
    resolve
};
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;

use RTCKit\FiCore\Plan\{
    AbstractElement,
    HandlerInterface,
    HandlerTrait,
    Play,
    Wait
};
use RTCKit\FiCore\Signal\Conference as ConferenceSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    Conference
};

use stdClass as Event;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public const EVENT_TIMEOUT = 30;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        if (!empty($element->mohMedia)) {
            $element->setVars[] = 'playback_delimiter=!';
            $element->setVars[] = 'conference_moh_sound=file_string://silence_stream://1!' . implode('!', $element->mohMedia);
        } else {
            $element->unsetVars[] = 'conference_moh_sound';
        }

        if (isset($element->leaveMedium)) {
            $element->setVars[] = 'conference_exit_sound=' . $element->leaveMedium;
        }

        if (!empty($element->flags)) {
            $element->setVars[] = 'conference_member_flags=' . implode(',', $element->flags);
        } else {
            $element->unsetVars[] = 'conference_member_flags';
        }

        $element->setVars[] = 'conference_controls=none';
        $element->setVars[] = 'conference_max_members=' . $element->maxMembers;

        $digitRealm = "{$this->app->config->appPrefix}_bda_{$element->channel->uuid}";

        $promises = [
            'set' => $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'multiset')
                    ->setHeader('execute-app-arg', implode(' ', $element->setVars))
                    ->setHeader('event-lock', 'true')
            ),
        ];

        foreach ($element->unsetVars as $var) {
            $promises[$var] = $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'unset')
                    ->setHeader('execute-app-arg', $var)
                    ->setHeader('event-lock', 'true')
            );
        }

        return all($promises)
            ->then(function () use ($element): PromiseInterface {
                if ($element->maxDuration > 0) {
                    $schedGroupName = 'conf_' . $element->room;

                    return $element->channel->client->api(
                        (new ESL\Request\Api())->setParameters('sched_del ' . $schedGroupName)
                    )
                        ->then(function () use ($element, $schedGroupName): PromiseInterface {
                            $this->app->planConsumer->logger->warning("Conference: Room {$element->room}, maxDuration set to {$element->maxDuration} seconds");

                            return $element->channel->client->api(
                                (new ESL\Request\Api())->setParameters(
                                    "sched_api +{$element->maxDuration} {$schedGroupName} conference {$element->room} kick all"
                                )
                            );
                        });
                }

                return resolve();
            })
            ->then(function () use ($element): PromiseInterface {
                $this->app->planConsumer->logger->info("Entering Conference: Room {$element->room}", $element->flags);

                return $element->channel->client->sendMsg(
                    (new ESL\Request\SendMsg())
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'conference')
                        ->setHeader('execute-app-arg', $element->fqrn)
                );
            })
            ->then(function (ESL\Response\CommandReply $response) use ($element) {
                if (!$response->isSuccessful()) {
                    $this->app->planConsumer->logger->error("Conference: Entering Room {$element->room} Failed");
                }

                return $this->app->planConsumer->waitForEvent($element->channel);
            })
            ->then(function (?Event $event) use ($element, $digitRealm): PromiseInterface {
                if (
                    isset($event, $event->{'Event-Subclass'}, $event->Action) &&
                    ($event->{'Event-Subclass'} === 'conference::maintenance') &&
                    ($event->Action === 'add-member')
                ) {
                    $element->uuid = $event->{'Conference-Unique-ID'};
                    $element->member = (int)$event->{'Member-ID'};

                    $conference = $element->channel->core->getConference($element->uuid);

                    if (!isset($conference)) {
                        $conference = new Conference();
                        $conference->uuid = $element->uuid;
                        $conference->room = $element->room;

                        $element->channel->core->addConference($conference);
                    }

                    $this->app->planConsumer->logger->debug("Entered Conference: Room {$element->room} with Member-ID {$element->member}");

                    $hasFloor = isset($event->Floor) ? ($event->Floor === 'true') : false;
                    $canSpeak = isset($event->Speak) ? ($event->Speak === 'true') : false;
                    $isFirst = isset($event->{'Conference-Size'}) ? ($event->{'Conference-Size'} === '1') : false;

                    if (isset($element->onEnterAttn)) {
                        $enterSignal = new ConferenceSignal\Enter();
                        $enterSignal->attn = $element->onEnterAttn;
                        $enterSignal->channel = $element->channel;
                        $enterSignal->conference = $conference;
                        $enterSignal->member = (int)$event->{'Member-ID'};

                        $this->app->signalProducer->produce($enterSignal);
                    }

                    if (isset($element->onFloorAttn) && $hasFloor && $canSpeak && $isFirst) {
                        $floorSignal = new ConferenceSignal\Floor();
                        $floorSignal->attn = $element->onFloorAttn;
                        $floorSignal->channel = $element->channel;
                        $floorSignal->conference = $conference;
                        $floorSignal->member = (int)$event->{'Member-ID'};

                        $this->app->signalProducer->produce($floorSignal);
                    }

                    $promises = [];

                    if (!empty($element->matchTones) && !empty($element->onDtmfAttn)) {
                        $eventTemplate = 'Event-Name=CUSTOM,Event-Subclass=conference::maintenance,Action=digits-match' .
                            ',Unique-ID=' . $element->channel->uuid .
                            ',Signal-Attn=' . $element->onDtmfAttn .
                            ',Member-ID=' . $element->member .
                            ',Conference-Name=' . $element->room .
                            ',Conference-Unique-ID=' . $element->uuid;

                        $matches = explode(',', $element->matchTones);
                        foreach ($matches as $match) {
                            $match = trim($match);

                            if (strlen($match)) {
                                $rawEvent = "{$eventTemplate},Digits-Match={$match}";
                                $args = "{$digitRealm},{$match},exec:event,'{$rawEvent}'";

                                $promises[] = $element->channel->client->sendMsg(
                                    (new ESL\Request\SendMsg())
                                        ->setHeader('call-command', 'execute')
                                        ->setHeader('execute-app-name', 'bind_digit_action')
                                        ->setHeader('execute-app-arg', $args)
                                        ->setHeader('event-lock', 'true')
                                );
                            }
                        }
                    }

                    if (isset($element->terminator)) {
                        $rawEvent = 'Event-Name=CUSTOM,Event-Subclass=conference::maintenance,Action=kick' .
                            ',Unique-ID=' . $element->channel->uuid .
                            ',Member-ID=' . $element->member .
                            ',Conference-Name=' . $element->room .
                            ',Conference-Unique-ID=' . $element->uuid;
                        $args = "{$digitRealm},{$element->terminator},exec:event,'{$rawEvent}'";

                        $promises[] = $element->channel->client->sendMsg(
                            (new ESL\Request\SendMsg())
                                ->setHeader('call-command', 'execute')
                                ->setHeader('execute-app-name', 'bind_digit_action')
                                ->setHeader('execute-app-arg', $args)
                                ->setHeader('event-lock', 'true')
                        );
                    }

                    $promises[] = $element->channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'digit_action_set_realm')
                            ->setHeader('execute-app-arg', $digitRealm)
                            ->setHeader('event-lock', 'true')
                    );

                    if (isset($element->enterMedium)) {
                        $promises[] = $element->channel->bgApi(
                            (new ESL\Request\BgApi())->setParameters("conference {$element->room} play {$element->enterMedium} async")
                        );
                    }

                    if (!empty($element->medium)) {
                        $promises[] = $element->channel->bgApi(
                            (new ESL\Request\BgApi())->setParameters("conference {$element->room} record {$element->medium}")
                        );

                        $this->app->planConsumer->logger->debug("Conference: Room {$element->room}, recording to file {$element->medium}");
                    }

                    $this->app->planConsumer->logger->debug("Conference: Room {$element->room}, waiting end ...");

                    return all($promises)
                        ->then(function () use ($element) {
                            return $this->waitForEvent($element);
                        });
                }

                return resolve();
            })
            ->then(function () use ($element, $digitRealm) {
                $element->channel->client->sendMsg(
                    (new ESL\Request\SendMsg())
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'clear_digit_action')
                        ->setHeader('execute-app-arg', $digitRealm)
                        ->setHeader('event-lock', 'true')
                );

                $conference = $element->channel->core->getConference($element->uuid);

                assert(!is_null($conference));

                $leaveSignal = new ConferenceSignal\Leave();
                $leaveSignal->channel = $element->channel;
                $leaveSignal->conference = $conference;
                $leaveSignal->member = $element->member;

                if (!empty($element->medium)) {
                    $leaveSignal->medium = $element->medium;
                }

                if (isset($element->onLeaveAttn)) {
                    $leaveSignal->attn = $element->onLeaveAttn;

                    $this->app->signalProducer->produce($leaveSignal);
                }

                $this->app->planConsumer->logger->info("Leaving Conference: Room {$element->room}");

                if (!empty($element->sequence)) {
                    return $this->app->planConsumer->consume($element->channel, $element->sequence, $leaveSignal)
                        ->then(function () {
                            return resolve(true);
                        });
                }
            })
            ->otherwise(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->app->planConsumer->logger->error('Unhandled exception: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }

    protected function waitForEvent(Element $element): PromiseInterface
    {
        return $this->app->planConsumer->waitForEvent($element->channel, self::EVENT_TIMEOUT, true)
            ->then(function (?Event $event) use ($element): PromiseInterface {
                if (isset($event)) {
                    if (isset($event->Action) && ($event->Action === 'floor-change')) {
                        $conference = $element->channel->core->getConference($element->uuid);

                        if (isset($element->onFloorAttn, $conference)) {
                            $floorSignal = new ConferenceSignal\Floor();
                            $floorSignal->attn = $element->onFloorAttn;
                            $floorSignal->channel = $element->channel;
                            $floorSignal->conference = $conference;
                            $floorSignal->member = (int)$event->{'Member-ID'};

                            $this->app->signalProducer->produce($floorSignal);
                        }
                    } else {
                        return resolve();
                    }
                }

                return $this->waitForEvent($element);
            });
    }
}
