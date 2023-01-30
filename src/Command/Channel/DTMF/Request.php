<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\DTMF;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\FiCore\Switch\{
    CallLegEnum,
    Channel,
};

class Request implements RequestInterface
{
    public ActionEnum $action;

    public CallLegEnum $leg;

    public string $tones;

    public Channel $channel;
}
