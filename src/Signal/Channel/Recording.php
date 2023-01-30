<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal\Channel;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\Channel;

class Recording extends AbstractSignal
{
    public Channel $channel;

    public string $medium;

    public float $duration;

    public string $terminator;
}
