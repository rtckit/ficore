<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\CaptureTones;

use RTCKit\FiCore\Plan\AbstractElement;

class Element extends AbstractElement
{
    public string $sequence;
    public int $maxTones;
    public int $timeout;
    public string $terminators;
    public int $tries;

    /** @var list<string> */
    public array $media = [];
    public string $validTones;
    public string $invalidMedium;

    /** @var list<string> */
    public array $setVars = [];
}
