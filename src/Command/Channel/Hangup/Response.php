<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Hangup;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\FiCore\Switch\ScheduledHangup;

class Response implements ResponseInterface
{
    public bool $successful;

    public ScheduledHangup $schedHangup;
}
