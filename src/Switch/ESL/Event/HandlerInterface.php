<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use RTCKit\FiCore\AbstractApp;
use RTCKit\FiCore\Switch\{
    Core,
    EventEnum
};

use stdClass as Event;

interface HandlerInterface
{
    /** @var EventEnum */
    public const EVENT = EventEnum::ALL;

    public function setApp(AbstractApp $app): static;

    public function execute(Core $core, Event $event): void;
}
