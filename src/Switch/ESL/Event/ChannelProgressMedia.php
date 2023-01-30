<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use RTCKit\FiCore\Switch\EventEnum;

class ChannelProgressMedia extends ChannelProgress
{
    /** @var EventEnum */
    public const EVENT = EventEnum::CHANNEL_PROGRESS_MEDIA;
}
