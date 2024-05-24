<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan;

use React\Promise\PromiseInterface;
use RTCKit\FiCore\AbstractApp;

use RTCKit\FiCore\Switch\Channel;

interface HandlerInterface
{
    /** @var string */
    public const ELEMENT_TYPE = 'default';

    /** @var list<string> */
    public const NESTABLES = [];

    public function setApp(AbstractApp $app): static;

    public function execute(Channel $channel, AbstractElement $element): PromiseInterface;
}
