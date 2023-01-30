<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;

class Gateway
{
    public string $name;

    public string $codecs;

    public int $timeout;

    public int $tries;
}
