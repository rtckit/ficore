<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Hangup;

enum ActionEnum
{
    case Channel;
    case Job;
    case All;
    case Schedule;
    case Cancel;
}
