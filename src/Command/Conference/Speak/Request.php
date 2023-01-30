<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Conference\Speak;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\FiCore\Switch\Conference;

class Request implements RequestInterface
{
    public ActionEnum $action;

    public Conference $conference;

    public string $text;

    public string $member;
}
