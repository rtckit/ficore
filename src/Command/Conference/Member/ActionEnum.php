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
    case Play = 'play';
    case Stop = 'stop';

    case ExtHold = 'ext-hold';
    case ExtUnhold = 'ext-unhold';

    /* These native actions do not produce the desired results:
     *   https://github.com/signalwire/freeswitch/issues/796
     *
     * Use the extended/pseudo actions above instead.
     */
    case Hold = 'hold';
    case Unhold = 'unhold';
}
