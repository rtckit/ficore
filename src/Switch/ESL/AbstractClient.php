<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL;

use Monolog\Logger;

use RTCKit\FiCore\AbstractApp;

abstract class AbstractClient
{
    public AbstractApp $app;

    /** @var array<string, Event\HandlerInterface> */
    public array $handlers = [];

    public Logger $logger;

    abstract public function setApp(AbstractApp $app): static;

    abstract public function run(): void;

    abstract public function shutdown(): void;
}
