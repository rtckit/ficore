<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;

enum CallLegEnum: string
{
    case ALEG = 'aleg';
    case BLEG = 'bleg';
    case BOTH = 'both';
}
