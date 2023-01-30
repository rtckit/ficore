<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Originate;

use RTCKit\FiCore\Command\ResponseInterface;
use RTCKit\FiCore\Switch\OriginateJob;

class Response implements ResponseInterface
{
    public bool $successful;

    /** @var list<OriginateJob> */
    public array $originateJobs = [];
}
