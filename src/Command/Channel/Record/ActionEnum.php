<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Record;

enum ActionEnum: string
{
    case Start = 'start';
    case Stop = 'stop';
}
