<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Conference\Member;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\FiCore\Switch\Conference;

class Request implements RequestInterface
{
    public ActionEnum $action;

    public Conference $conference;

    /** @var list<string> */
    public array $members = [];

    public string $medium;
}
