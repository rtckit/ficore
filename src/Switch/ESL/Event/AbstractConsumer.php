<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use Monolog\Logger;
use React\Promise\PromiseInterface;

use RTCKit\FiCore\AbstractApp;
use RTCKit\FiCore\Switch\{
    Channel,
    Conference,
    Core,
    HangupCauseEnum,
    OriginateJob
};
use stdClass as Event;

abstract class AbstractConsumer
{
    public Logger $logger;

    abstract public function setApp(AbstractApp $app): Consumer;

    abstract public function onEvent(Core $core, Event $event): void;

    abstract public function subscribe(Core $core): PromiseInterface;

    abstract public function hangupCompleted(Event $event, HangupCauseEnum $reason, ?string $url = null, ?OriginateJob $originateJob = null, ?Channel $channel = null): void;

    abstract public function run(): void;
}
