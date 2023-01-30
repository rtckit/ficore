<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal\Conference;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    Conference,
};

class Recording extends AbstractSignal
{
    public Conference $conference;

    public string $medium;

    public float $duration;
}
