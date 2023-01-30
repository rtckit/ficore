<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Signal\Channel;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    SpeechInterpretation,
};

class Speech extends AbstractSignal
{
    public Channel $channel;

    /** @var list<SpeechInterpretation> */
    public array $interpretations = [];
}
