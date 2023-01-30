<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal\Channel;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    HangupCauseEnum,
    OriginateJob,
};

class Hangup extends AbstractSignal
{
    public HangupCauseEnum $reason;

    public OriginateJob $originateJob;

    public Channel $channel;
}
