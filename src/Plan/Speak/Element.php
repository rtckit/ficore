<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Speak;

use RTCKit\FiCore\Plan\AbstractElement;

class Element extends AbstractElement
{
    public string $text;
    public int $loop;
    public string $engine;
    public string $language;
    public string $voice;
    public string $type;
    public string $method;

    public int $iteration;
}
