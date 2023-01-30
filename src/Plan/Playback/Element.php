<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Playback;

use RTCKit\FiCore\Plan\AbstractElement;

class Element extends AbstractElement
{
    public string $medium;
    public int $loop;

    /** @var list<string> */
    public array $setVars = [];
}
