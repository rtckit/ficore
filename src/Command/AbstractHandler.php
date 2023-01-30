<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command;

use React\Promise\PromiseInterface;

use RTCKit\FiCore\AbstractApp;

abstract class AbstractHandler
{
    protected AbstractApp $app;

    public function setApp(AbstractApp $app): static
    {
        $this->app = $app;

        return $this;
    }

    abstract public function execute(RequestInterface $request): PromiseInterface;
}
