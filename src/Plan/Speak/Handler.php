<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Speak;

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
use RTCKit\FiCore\Switch\{
    Channel,
    HangupCauseEnum
};

use stdClass as Event;

class Handler implements HandlerInterface
{
    use HandlerTrait;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        if (isset($element->type) && isset($element->method)) {
            $promise = $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'say')
                    ->setHeader('execute-app-arg', "{$element->language} {$element->type} {$element->method} {$element->text}")
                    ->setHeader('event-lock', 'true')
                    ->setHeader('loops', (string)$element->loop)
            );
        } else {
            $promise = $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'speak')
                    ->setHeader('execute-app-arg', "{$element->engine}|{$element->voice}|{$element->text}")
                    ->setHeader('event-lock', 'true')
                    ->setHeader('loops', (string)$element->loop)
            );
        }

        return $promise
            ->then(function (ESL\Response\CommandReply $response) use ($element): PromiseInterface {
                if (!$response->isSuccessful()) {
                    $this->app->planConsumer->logger->error('Speak Failed - ' . ($response->getBody() ?? '<null>'));

                    return resolve();
                }

                $element->iteration = 0;

                return $this->waitForEvent($element);
            });
    }

    protected function waitForEvent(Element $element): PromiseInterface
    {
        if ($element->iteration >= $element->loop) {
            $this->app->planConsumer->logger->info('Speak Finished');

            return resolve();
        }

        $this->app->planConsumer->logger->debug('Speaking ' . ($element->iteration + 1) . ' times ...');

        return $this->app->planConsumer->waitForEvent($element->channel)
            ->then(function (?Event $event) use ($element): PromiseInterface {
                if (!isset($event)) {
                    $this->app->planConsumer->logger->warning('Speak Break (empty event)');

                    return resolve();
                }

                $this->app->planConsumer->logger->debug('Speak ' . ++$element->iteration . ' times done (' . $event->{'Application-Response'} . ')');

                return $this->waitForEvent($element);
            });
    }
}
