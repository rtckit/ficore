<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Playback;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\FiCore\Switch\ScheduledPlay;

class Response implements ResponseInterface
{
    public bool $successful;

    public ScheduledPlay $schedPlay;
}
