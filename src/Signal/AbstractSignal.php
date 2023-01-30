<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal;

use stdClass as Event;

abstract class AbstractSignal
{
    public string $attn;

    public float $timestamp;

    public Event $event;
}
