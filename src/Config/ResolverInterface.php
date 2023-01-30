<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Config;

interface ResolverInterface
{
    public function resolve(AbstractSet $config): void;
}
