<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Conference\Member;

enum ActionEnum: string
{
    case Mute = 'mute';
    case Unmute = 'unmute';
    case Deaf = 'deaf';
    case Undeaf = 'undeaf';
    case Kick = 'kick';
    case Hangup = 'hup';
}
