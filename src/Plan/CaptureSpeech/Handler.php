<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\CaptureSpeech;

use React\EventLoop\Loop;
use function React\Promise\{
    all,
    reject,
    resolve
};
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use RTCKit\FiCore\Exception\FiCoreException;
use RTCKit\FiCore\Plan\{
    AbstractElement,
    HandlerInterface,
    HandlerTrait,
    Play,
    Speak,
    Wait
};
use RTCKit\FiCore\Signal\Channel\Speech as SpeechSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    HangupCauseEnum,
    SpeechInterpretation,
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

        $element->channel->client->sendMsg(
            (new ESL\Request\SendMsg())
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'detect_speech')
                ->setHeader('execute-app-arg', 'grammarsalloff')
                ->setHeader('event-lock', 'true')
        )
            ->then(function () use ($element) {
                return $element->channel->client->sendMsg(
                    (new ESL\Request\SendMsg())
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'detect_speech')
                        ->setHeader('execute-app-arg', "{$element->engine} {$element->grammar} {$element->grammarPath}/{$element->grammar}.gram")
                        ->setHeader('event-lock', 'true')
                );
            })
            ->then(function (ESL\Response\CommandReply $response) use ($element, $deferred) {
                if (!$response->isSuccessful()) {
                    $this->app->planConsumer->logger->error('GetSpeech Failed - ' . ($response->getBody() ?? '<null>'));

                    $deferred->resolve(null);

                    return reject(new FiCoreException('GetSpeech Failed'));
                }

                return $element->channel->client->sendMsg(
                    (new ESL\Request\SendMsg())
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'detect_speech')
                        ->setHeader('execute-app-arg', 'resume')
                        ->setHeader('event-lock', 'true')
                );
            })
            ->then(function () use ($element) {
                $element->setVars[] = 'playback_delimiter=!';
                $playStr = 'file_string://silence_stream://1!' . implode('!', $element->media);

                if (isset($element->channel->ttsEngine)) {
                    $element->setVars[] = 'tts_engine=' . $element->channel->ttsEngine;

                    unset($element->channel->ttsEngine);
                }

                if (isset($element->channel->ttsVoice)) {
                    $element->setVars[] = 'tts_voice=' . $element->channel->ttsVoice;

                    unset($element->channel->ttsVoice);
                }

                $promises = [
                    'set' => $element->channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'multiset')
                            ->setHeader('execute-app-arg', implode(' ', $element->setVars))
                            ->setHeader('event-lock', 'true')
                    ),
                    'playback' => $element->channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'playback')
                            ->setHeader('execute-app-arg', $playStr)
                            ->setHeader('event-lock', 'true')
                    ),
                ];

                return all($promises);
            })
            ->then(function () use ($element): PromiseInterface {
                return $this->app->planConsumer->waitForEvent($element->channel);
            })
            ->then(function (Event $event) use ($element): PromiseInterface {
                if (
                    ($event->{'Event-Name'} === 'DETECTED_SPEECH') &&
                    in_array($event->{'Speech-Type'}, ['begin-speaking', 'detected-speech'])
                ) {
                    $this->app->planConsumer->logger->debug("GetSpeech Break ({$event->{'Speech-Type'}})");

                    if ($event->{'Speech-Type'} === 'detected-speech') {
                        $element->event = $event;
                    }

                    return $element->channel->client->bgApi(
                        (new ESL\Request\BgApi())->setParameters('uuid_break ' . $element->channel->uuid . ' all')
                    );
                }

                $response = $event->{'Application-Response'} ?? '<null>';
                $this->app->planConsumer->logger->debug("GetSpeech prompt played ({$response})");

                $element->timer = Loop::addTimer($element->timeout, function () use ($element): void {
                    unset($element->timer);

                    $this->app->planConsumer->logger->debug('GetSpeech Break (timeout)');
                    $this->app->planConsumer->pushToEventQueue($element->channel, null);
                });

                return $element->channel->client->sendMsg(
                    (new ESL\Request\SendMsg())
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'detect_speech')
                        ->setHeader('execute-app-arg', 'resume')
                        ->setHeader('event-lock', 'true')
                );
            })
            ->then(function () use ($element): PromiseInterface {
                return $this->waitForEvent($element);
            })
            ->then(function (?string $response = null) use ($element): PromiseInterface {
                return all([
                    'response' => $response,
                    'stop' => $element->channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'detect_speech')
                            ->setHeader('execute-app-arg', 'stop')
                            ->setHeader('event-lock', 'true')
                    ),
                    'break' => $element->channel->client->bgApi(
                        (new ESL\Request\BgApi())->setParameters('uuid_break ' . $element->channel->uuid . ' all')
                    ),
                ]);
            })
            ->then(function (array $args) use ($element) {
                if (isset($element->sequence)) {
                    $signal = new SpeechSignal();
                    $signal->attn = $element->sequence;

                    if (isset($element->event)) {
                        $signal->timestamp = (int)$element->event->{'Event-Date-Timestamp'} / 1e6;
                        $signal->event = $element->event;
                    } else {
                        $signal->timestamp = microtime(true);
                    }

                    $signal->channel = $element->channel;

                    if (isset($args['response']) && is_string($args['response'])) {
                        $resultXml = simplexml_load_string($args['response']);
                        if ($resultXml === false) {
                            $this->app->planConsumer->logger->error("GetSpeech result failure, cannot parse result");
                        } else {
                            if ($resultXml->getName() !== 'result') {
                                $this->app->planConsumer->logger->error("GetSpeech result failure, cannot parse result: No result Tag Present");
                            } elseif (!isset($resultXml->interpretation[0])) {
                                $this->app->planConsumer->logger->error("GetSpeech result failure, cannot parse result: No interpretation found");
                            } else {
                                foreach ($resultXml->interpretation as $entry) {
                                    if (!isset($entry->input)) {
                                        continue;
                                    }

                                    $attributes = $entry->attributes();
                                    $interpretation = new SpeechInterpretation;

                                    if (isset($attributes->grammar)) {
                                        $interpretation->grammar = (string)$attributes->grammar;
                                    }

                                    if (isset($attributes->confidence)) {
                                        $interpretation->confidence = (float)$attributes->confidence;
                                    } else {
                                        $interpretation->confidence = -1;
                                    }

                                    $inputAttrs = $entry->input->attributes();

                                    if (isset($inputAttrs->mode)) {
                                        $interpretation->mode = (string)$inputAttrs->mode;
                                    }

                                    $interpretation->input = (string)$entry->input;
                                    $signal->interpretations[] = $interpretation;
                                }
                            }
                        }
                    }

                    return $this->app->planConsumer->consume($element->channel, $element->sequence, $signal)
                        ->then(function () use ($element) {
                            $element->channel->currentElement = $element;

                            return true;
                        });
                }

                return resolve(false);
            })
            ->then(function (bool $break) use ($deferred) {
                $deferred->resolve($break);
            })
            ->catch(function (Throwable $t) {
                $t = $t->getPrevious() ?: $t;
                $this->app->planConsumer->logger->error("Unhandled getspeech element error: " . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });

        return $deferred->promise();
    }

    protected function waitForEvent(Element $element): PromiseInterface
    {
        return $this->app->planConsumer->waitForEvent($element->channel)
            ->then(function (?Event $event) use ($element): PromiseInterface {
                if (isset($element->event)) {
                    $event = $element->event;
                }

                if (!isset($event)) {
                    $this->app->planConsumer->logger->warning('GetSpeech Break (empty event)');

                    return resolve(null);
                } else if (
                    ($event->{'Event-Name'} === 'DETECTED_SPEECH') &&
                    in_array($event->{'Speech-Type'}, ['begin-speaking', 'detected-speech'])
                ) {
                    if (isset($element->timer)) {
                        Loop::cancelTimer($element->timer);
                    }

                    $this->app->planConsumer->logger->info("GetSpeech ({$event->{'Speech-Type'}}), result '{$event->_body}'");

                    return resolve($event->_body);
                }

                return $this->waitForEvent($element);
            });
    }
}
