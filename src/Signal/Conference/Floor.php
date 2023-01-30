<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal\Conference;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    Conference,
};

class Floor extends AbstractSignal
{
    public Channel $channel;

    public Conference $conference;

    public int $member;
}
