<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Redirect;

use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\FiCore\Plan\{
    AbstractElement,
    HandlerInterface,
    HandlerTrait
};

use RTCKit\FiCore\Switch\{
    Channel,
    RedirectCauseEnum
};

class Handler implements HandlerInterface
{
    use HandlerTrait;

    /** @var string */
    public const ELEMENT_TYPE = 'Redirect';

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface
    {
        assert($element instanceof Element);

        return $this->app->planConsumer->consume($element->channel, $element->sequence)
            ->then(function () {
                return true;
            });
    }
}
