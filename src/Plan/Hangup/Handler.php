<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Hangup;

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

class Handler implements HandlerInterface
{
    use HandlerTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Hangup';

    /** @var bool */
    public const NO_ANSWER = true;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        if ($element->delay > 0) {
            return $element->channel->client->sendMsg(
                (new ESL\Request\SendMsg())
                    ->setHeader('call-command', 'execute')
                    ->setHeader('execute-app-name', 'sched_hangup')
                    ->setHeader('execute-app-arg', "+{$element->delay} {$element->reason}")
                    ->setHeader('event-lock', 'true')
            )
                ->then(function (ESL\Response\CommandReply $response) use ($element): PromiseInterface {
                    if ($response->isSuccessful()) {
                        $this->app->planConsumer->logger->info("Hangup will occur in {$element->delay} secs!");
                    } else {
                        $this->app->planConsumer->logger->error('Hangup Failed: ' . ($response->getBody() ?? '<null>'));
                    }

                    return resolve(null);
                });
        }

        /* Not part of the legacy implementation, but it makes sense (for now) */
        $element->channel->hangupCause = HangupCauseEnum::from($element->reason);

        $this->app->planConsumer->logger->info("Hanging up now ({$element->reason})");

        return $element->channel->client->sendMsg(
            (new ESL\Request\SendMsg())
                ->setHeader('call-command', 'execute')
                ->setHeader('execute-app-name', 'hangup')
                ->setHeader('execute-app-arg', $element->reason)
                ->setHeader('event-lock', 'true')
        )
            ->then(function (): bool {
                return true;
            });
    }
}
