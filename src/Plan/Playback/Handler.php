<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Playback;

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

use stdClass as Event;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        if (empty($element->medium)) {
            $this->app->planConsumer->logger->error('Invalid Sound File - Ignoring Play');

            return resolve();
        }

        $element->setVars[] = 'playback_sleep_val=0';

        if ($element->loop === 1) {
            $playStr = $element->medium;
        } else {
            $element->setVars[] = 'playback_delimiter=!';
            $playStr = 'file_string://silence_stream://1' . str_repeat('!' . $element->medium, $element->loop);
        }

        $this->app->planConsumer->logger->debug("Playing {$element->loop} times");

        return $element->channel->client->sendMsg(
            (new ESL\Request\SendMsg())
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'multiset')
                ->setHeader('execute-app-arg', implode(' ', $element->setVars))
                ->setHeader('event-lock', 'true')
        )
            ->then(function () use ($element, $playStr) {
                return $element->channel->client->sendMsg(
                    (new ESL\Request\SendMsg())
                        ->setHeader('call-command', 'execute')
                        ->setHeader('execute-app-name', 'playback')
                        ->setHeader('execute-app-arg', $playStr)
                        ->setHeader('event-lock', 'true')
                );
            })
            ->then(function (ESL\Response $response) use ($element) {
                if (!($response instanceof ESL\Response\CommandReply) || !$response->isSuccessful()) {
                    $this->app->planConsumer->logger->error('Play Failed - ' . ($response->getBody() ?? '<null>'));

                    return resolve();
                }

                return $this->app->planConsumer->waitForEvent($element->channel)
                    ->then(function (?Event $event = null) {
                        if (!isset($event)) {
                            $this->app->planConsumer->logger->warning('Play Break (empty event)');
                        } else {
                            $this->app->planConsumer->logger->debug("Play done ({$event->{'Application-Response'}})");
                        }

                        $this->app->planConsumer->logger->info('Play Finished');

                        return resolve();
                    });
            });
    }
}
