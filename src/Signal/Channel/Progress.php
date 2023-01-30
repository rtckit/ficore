<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal\Channel;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\{
    OriginateJob,
    StatusEnum,
};

class Progress extends AbstractSignal
{
    /** @psalm-suppress PossiblyUnusedProperty */
    public OriginateJob $originateJob;

    public StatusEnum $status;
}
