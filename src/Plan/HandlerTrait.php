<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan;

use RTCKit\FiCore\AbstractApp;

trait HandlerTrait
{
    public AbstractApp $app;

    public function setApp(AbstractApp $app): static
    {
        $this->app = $app;

        return $this;
    }
}
