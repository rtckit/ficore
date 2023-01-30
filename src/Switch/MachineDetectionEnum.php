<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;

enum MachineDetectionEnum: string
{
    case UNKNOWN = 'unknown';
    case HUMAN = 'human';
    case MACHINE_START = 'machine_start';
    case MACHINE_END_BEEP = 'machine_end_beep';
    case MACHINE_END_OTHER = 'machine_end_other';
}
