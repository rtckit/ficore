<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\CaptureSpeech;

use React\EventLoop\TimerInterface;

use RTCKit\FiCore\Plan\AbstractElement;

use stdClass as Event;

class Element extends AbstractElement
{
    public string $sequence;
    public string $engine;
    public int $timeout;

    /** @var list<string> */
    public array $media = [];
    public string $grammar;
    public string $grammarPath;
    public TimerInterface $timer;
    public Event $event;

    /** @var list<string> */
    public array $setVars = [];
}
