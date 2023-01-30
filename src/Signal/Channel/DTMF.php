<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal\Channel;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\Channel;

class DTMF extends AbstractSignal
{
    public Channel $channel;

    public string $tones;
}
