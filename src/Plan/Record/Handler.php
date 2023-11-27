<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Record;

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
    HandlerTrait
};
use RTCKit\FiCore\Signal\Channel\Recording as RecordingSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    HangupCauseEnum
};
use stdClass as Event;

use Throwable;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        $deferred = new Deferred();

        if ($element->async) {
            all([
                $element->channel->client->sendMsg(
                    (new ESL\Request\SendMsg())
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'set')
                        ->setHeader('execute-app-arg', 'RECORD_STEREO=true')
                        ->setHeader('event-lock', 'true')
                ),
                $element->channel->client->api(
                    (new ESL\Request\Api())
                        ->setParameters("uuid_record {$channel->uuid} start {$element->medium}")
                ),
                $element->channel->client->api(
                    (new ESL\Request\Api())
                        ->setParameters("sched_api +{$element->maxDuration} none uuid_record {$channel->uuid} stop {$element->medium}")
                ),
            ])
                ->then(function () use ($element, $deferred): PromiseInterface {
                    return $this->finalize($element, $deferred);
                });
        } else {
            $promise = resolve(null);

            if (isset($element->playMedium)) {
                $promise = $element->channel->client->sendMsg(
                    (new ESL\Request\SendMsg())
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'playback')
                        ->setHeader('execute-app-arg', $element->playMedium)
                        ->setHeader('event-lock', 'true')
                )
                    ->then(function () use ($element): PromiseInterface {
                        return $this->app->planConsumer->waitForEvent($element->channel);
                    })
                    ->then(function (Event $event) use ($element): PromiseInterface {
                        $element->event = $event;

                        return resolve(null);
                    })
                    ->catch(function (Throwable $t) {
                        $t = $t->getPrevious() ?: $t;
                        $this->app->planConsumer->logger->error("Unhandled record element error: " . $t->getMessage(), [
                            'file' => $t->getFile(),
                            'line' => $t->getLine(),
                        ]);
                    });
            }

            $promise
                ->then(function () use ($element): PromiseInterface {
                    return $element->channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'start_dtmf')
                            ->setHeader('event-lock', 'true')
                    );
                })
                ->then(function () use ($element): PromiseInterface {
                    $this->app->planConsumer->logger->info('Record Started');

                    return all([
                        $element->channel->client->sendMsg(
                            (new ESL\Request\SendMsg())
                                ->setHeader('call-command', 'execute')
                                ->setHeader('execute-app-name', 'set')
                                ->setHeader('execute-app-arg', 'playback_terminators=' . $element->terminators)
                                ->setHeader('event-lock', 'true')
                        ),
                        $element->channel->client->sendMsg(
                            (new ESL\Request\SendMsg())
                                ->setHeader('call-command', 'execute')
                                ->setHeader('execute-app-name', 'record')
                                ->setHeader('execute-app-arg', "{$element->medium} {$element->maxDuration} {$element->silenceThreshold} {$element->silenceHits}")
                                ->setHeader('event-lock', 'true')
                        ),
                    ]);
                })
                ->then(function () use ($element): PromiseInterface {
                    return $this->app->planConsumer->waitForEvent($element->channel);
                })
                ->then(function (Event $event) use ($element): PromiseInterface {
                    $element->event = $event;

                    return $element->channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'stop_dtmf')
                            ->setHeader('event-lock', 'true')
                    );
                })
                ->then(function () use ($element, $deferred): PromiseInterface {
                    $this->app->planConsumer->logger->info('Record Completed');

                    return $this->finalize($element, $deferred);
                })
                ->catch(function (Throwable $t) {
                    $t = $t->getPrevious() ?: $t;
                    $this->app->planConsumer->logger->error("Unhandled record element error: " . $t->getMessage(), [
                        'file' => $t->getFile(),
                        'line' => $t->getLine(),
                    ]);
                });
        }

        return $deferred->promise();
    }

    protected function finalize(Element $element, Deferred $deferred): PromiseInterface
    {
        if (isset($element->onCompletedAttn) || isset($element->onCompletedSeq)) {
            $signal = new RecordingSignal();

            if (isset($element->event)) {
                $signal->timestamp = (int)$element->event->{'Event-Date-Timestamp'} / 1e6;
                $signal->event = $element->event;
            } else {
                $signal->timestamp = microtime(true);
            }

            $signal->channel = $element->channel;
            $signal->medium = $element->medium;
            $signal->duration = isset($element->event, $element->event->variable_record_ms) ? $element->event->variable_record_ms / 1000 : 0;
            $signal->terminator = isset($element->event, $element->event->variable_playback_terminator_used) ? $element->event->variable_playback_terminator_used : '';

            if (isset($element->onCompletedAttn)) {
                $signal->attn = $element->onCompletedAttn;

                $this->app->signalProducer->produce($signal);

                if (!isset($element->onCompletedSeq)) {
                    $deferred->resolve(null);
                }
            }

            if (isset($element->onCompletedSeq)) {
                return $this->app->planConsumer->consume($element->channel, $element->onCompletedSeq, $signal)
                    ->then(function () use ($deferred) {
                        $deferred->resolve(true);

                        return resolve(null);
                    });
            }
        } else {
            $deferred->resolve(null);
        }

        return resolve(null);
    }
}
