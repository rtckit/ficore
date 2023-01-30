<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;


use React\Promise\Deferred;

class Job
{
    use DebugTrait;

    public static int $instances = 0;

    public string $uuid;

    public string $command;

    public bool $group = false;

    public OriginateJob $originateJob;

    public Deferred $deferred;

    public function __construct()
    {
        self::$instances++;
    }

    public function __destruct()
    {
        self::$instances--;
    }
}
