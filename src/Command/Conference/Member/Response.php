<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Conference\Member;

use RTCKit\FiCore\Command\ResponseInterface;

class Response implements ResponseInterface
{
    public bool $successful;

    /** @var list<string> */
    public array $members = [];
}
