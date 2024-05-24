<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\CaptureTones;

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
    Speak,
    Wait
};
use RTCKit\FiCore\Signal\Channel as ChannelSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    HangupCauseEnum
};

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        $this->app->planConsumer->logger->info('GetDigits Started ' . json_encode($element->media));

        $element->setVars[] = 'playback_delimiter=!';
        $playStr = 'file_string://silence_stream://1!' . implode('!', $element->media);

        if (isset($channel->ttsEngine)) {
            $element->setVars[] = 'tts_engine=' . $channel->ttsEngine;

            unset($channel->ttsEngine);
        }

        if (isset($channel->ttsVoice)) {
            $element->setVars[] = 'tts_voice=' . $channel->ttsVoice;

            unset($channel->ttsVoice);
        }

        if (!isset($element->invalidMedium[0])) {
            $element->invalidMedium = 'silence_stream://150';
        }

        $digitTimeout = $element->timeout;
        $digits = str_split($element->validTones);

        foreach ($digits as $idx => $digit) {
            if ($digit === '*') {
                $digits[$idx] = '\*';
            }
        }

        $regExp = '^(' . implode('|', $digits) . ')+';
        $playStr = str_replace("'", "\\'", $playStr);

        $promises = [
            'set' => $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'multiset')
                    ->setHeader('execute-app-arg', implode(' ', $element->setVars))
                    ->setHeader('event-lock', 'true')
            ),
            'pagd' => $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'play_and_get_digits')
                    ->setHeader(
                        'execute-app-arg',
                        '1 ' . $element->maxTones . ' ' .
                        $element->tries . ' ' . $element->timeout . " '" .
                        $element->terminators . "' '" . $playStr . "' " .
                        $element->invalidMedium . ' pagd_input ' .
                        $regExp . ' ' . $digitTimeout
                    )
                    ->setHeader('event-lock', 'true')
            ),
        ];

        return all($promises)
            ->then(function () use ($element): PromiseInterface {
                return $this->app->planConsumer->waitForEvent($element->channel);
            })
            ->then(function () use ($element): PromiseInterface {
                return $this->app->planConsumer->getVariable($element->channel, 'pagd_input');
            })
            ->then(function (?string $digits) use ($element): PromiseInterface {
                if (isset($digits, $digits[0])) {
                    $this->app->planConsumer->logger->info("GetDigits, Digits '{$digits}' Received");

                    $dtmfSignal = new ChannelSignal\DTMF();
                    $dtmfSignal->channel = $element->channel;
                    $dtmfSignal->tones = $digits;

                    if (isset($element->sequence)) {
                        return $this->app->planConsumer->consume($element->channel, $element->sequence, $dtmfSignal)
                            ->then(function () {
                                return true;
                            });
                    }
                } else {
                    $this->app->planConsumer->logger->info('GetDigits, No Digits Received');
                }

                return resolve(null);
            });
    }
}
