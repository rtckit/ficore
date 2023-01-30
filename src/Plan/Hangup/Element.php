<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Hangup;

use RTCKit\FiCore\Plan\AbstractElement;

class Element extends AbstractElement
{
    public string $reason;
    public int $delay;
}
