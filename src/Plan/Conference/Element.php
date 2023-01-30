<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan\Conference;

use RTCKit\FiCore\Plan\AbstractElement;

class Element extends AbstractElement
{
    public string $room;
    public string $fqrn;
    public string $uuid;

    public string $terminator;
    public int $maxDuration;
    public int $maxMembers;
    public string $enterMedium;
    public string $leaveMedium;
    public string $medium;
    public string $sequence;
    public string $onDtmfAttn;
    public string $onEnterAttn;
    public string $onFloorAttn;
    public string $onLeaveAttn;
    public string $matchTones;

    /** @var list<string> */
    public array $flags = [];

    /** @var list<string> */
    public array $mohMedia = [];

    /** @var list<string> */
    public array $setVars = [];

    /** @var list<string> */
    public array $unsetVars = [];

    public int $member;
}
