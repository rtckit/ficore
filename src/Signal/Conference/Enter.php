<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal\Conference;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    Conference,
};

class Enter extends AbstractSignal
{
    public Channel $channel;

    public Conference $conference;

    public int $member;
}
