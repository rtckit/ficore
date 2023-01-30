<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;


class OriginateJob
{
    use DebugTrait;

    public static int $instances = 0;

    public Core $core;

    public string $uuid;

    public string $source;

    public string $destination;

    public string $onRingAttn;

    public string $onHangupAttn;

    /** @var list<string> */
    public array $originateStr = [];

    /** @var array<string, string> */
    public array $tags = [];

    public Job $job;

    public Channel $channel;

    public StatusEnum $status;

    public function __construct()
    {
        self::$instances++;
    }

    public function __destruct()
    {
        self::$instances--;
    }
}
