<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;

trait DebugTrait
{
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
