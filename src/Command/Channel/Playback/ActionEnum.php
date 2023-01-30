<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Playback;

enum ActionEnum
{
    case Play;
    case Stop;
    case Schedule;
    case Cancel;
}
