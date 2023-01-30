<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Record;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\FiCore\Switch\Channel;

class Request implements RequestInterface
{
    public ActionEnum $action;

    public Channel $channel;

    public string $medium;

    public float $duration;
}
