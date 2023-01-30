<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Conference\Record;

enum ActionEnum: string
{
    case Start = 'record';
    case Stop = 'norecord';
}
