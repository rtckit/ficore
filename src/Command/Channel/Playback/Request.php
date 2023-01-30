<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Playback;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\FiCore\Switch\{
    CallLegEnum,
    Channel,
    Core,
    ScheduledPlay,
};

class Request implements RequestInterface
{
    public ActionEnum $action;

    public CallLegEnum $leg;

    public int $duration;

    public bool $loop;

    public bool $mix;

    /** @var list<string> */
    public array $media = [];

    public int $delay;

    public ScheduledPlay $schedPlay;

    public Channel $channel;
}
