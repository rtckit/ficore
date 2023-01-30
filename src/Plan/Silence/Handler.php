<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Silence;

use function React\Promise\resolve;
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

use RTCKit\FiCore\Switch\Channel;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        $this->app->planConsumer->logger->info("Silence started for {$element->duration} seconds");

        $pauseStr = 'file_string://silence_stream://' . intval($element->duration * 1000);

        return $element->channel->client->sendMsg(
            (new ESL\Request\SendMsg())
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'playback')
                ->setHeader('execute-app-arg', $pauseStr)
                ->setHeader('event-lock', 'true')
        )
            ->then(function () use ($element) {
                return $this->app->planConsumer->waitForEvent($element->channel)
                    ->then(function () {
                        return resolve();
                    });
            });
    }
}
