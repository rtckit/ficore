<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal;

use Monolog\Logger;

use React\Promise\PromiseInterface;
use RTCKit\FiCore\AbstractApp;

abstract class AbstractProducer
{
    public Logger $logger;

    abstract public function setApp(AbstractApp $app): static;

    /**
     * Emits a signal
     *
     * @param AbstractSignal $signal
     *
     * @return PromiseInterface
     */
    abstract public function produce(AbstractSignal $signal): PromiseInterface;

    abstract public function run(): void;

    /**
     * Exports a signal
     *
     * @param AbstractSignal $signal
     *
     * @return null|array<string, mixed>
     */
    abstract public function export(AbstractSignal $signal): ?array;
}
