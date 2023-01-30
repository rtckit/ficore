<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Record;

use RTCKit\FiCore\Plan\AbstractElement;

use stdClass as Event;

class Element extends AbstractElement
{
    public int $maxDuration;
    public int $silenceThreshold;
    public int $silenceHits;
    public string $terminators;
    public string $playMedium;
    public bool $async;
    public string $onCompletedAttn;
    public string $onCompletedSeq;
    public string $medium;
    public Event $event;
}
