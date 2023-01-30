<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command;

use Monolog\Logger;
use React\Promise\PromiseInterface;

use RTCKit\FiCore\AbstractApp;
use RTCKit\FiCore\Exception\FiCoreException;

abstract class AbstractConsumer
{
    protected AbstractApp $app;

    /** @var array<string, AbstractHandler> */
    protected array $handlers;

    public Logger $logger;

    public function setApp(AbstractApp $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function setMethodHandler(string $requestClass, AbstractHandler $handler): AbstractConsumer
    {
        $this->handlers[$requestClass] = $handler;

        $this->handlers[$requestClass]->setApp($this->app);

        return $this;
    }

    public function run(): void
    {
        $this->logger = $this->app->createLogger('command.consumer');

        $this->logger->debug('Starting ...');
    }

    public function consume(RequestInterface $request): PromiseInterface
    {
        $requestClass = $request::class;

        if (!isset($this->handlers[$requestClass])) {
            throw new FiCoreException('Unknown command request class: ' . $requestClass);
        }

        return $this->handlers[$requestClass]->execute($request);
    }
}
