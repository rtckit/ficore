<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan;

use RTCKit\FiCore\Switch\Channel;

abstract class AbstractElement
{
    /** @var bool */
    public const NO_ANSWER = false;

    public Channel $channel;

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $ret = [];

        foreach (get_object_vars($this) as $key => $value) {
            switch (gettype($value)) {
                case 'boolean':
                case 'integer':
                case 'double':
                case 'string':
                case 'array':
                case 'NULL':
                    $ret[$key] = $value;
                    break;
            }
        }

        return $ret;
    }
}
