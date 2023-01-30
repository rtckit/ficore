<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;


class Conference
{
    use DebugTrait;

    public static int $instances = 0;

    public Core $core;

    public string $uuid;

    public string $room;

    public function __construct()
    {
        self::$instances++;
    }

    public function __destruct()
    {
        self::$instances--;
    }
}
