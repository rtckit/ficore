<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Hangup;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\FiCore\Switch\{
    Channel,
    HangupCauseEnum,
    OriginateJob,
    ScheduledHangup,
};

class Request implements RequestInterface
{
    public ActionEnum $action;

    public HangupCauseEnum $cause;

    public int $delay;

    public ScheduledHangup $schedHangup;

    public Channel $channel;

    public OriginateJob $originateJob;
}
