<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Config;

use Monolog\Level;

class AbstractSet
{
    /** @var string */
    public const INBOUND_SOCKET_ADDRESS = 'inbound_socket_address';

    /* General settings */
    public bool $daemonize = false;

    public string $userName;

    public string $groupName;

    public string $appPrefix = 'ficore';

    public string $pidFile = '/tmp/ficore.pid';

    public string $legacyConfigFile;

    public string $configFile;

    /** @var list<Core> */
    public array $cores = [];

    public string $defaultAnswerSequence;

    public string $defaultHangupSequence;

    /** @var array<int, string> */
    public array $extraChannelVars = [];

    public string $recordingAttn;

    /* ESL Server settings */
    public string $eslServerBindIp = '0.0.0.0';

    public int $eslServerBindPort = 8084;

    public string $eslServerAdvertisedIp = '127.0.0.1';

    public int $eslServerAdvertisedPort;

    public Level $eslServerLogLevel = Level::Debug;

    /* ESL Client settings */
    public Level $eslClientLogLevel = Level::Debug;

    public string $heartbeatAttn;

    public Level $signalProducerLogLevel = Level::Debug;

    public Level $eventConsumerLogLevel = Level::Debug;

    public static function parseSocketAddr(string $str, ?string &$ip, ?int &$port): ?string
    {
        $ret = self::parseHostPort($str, $ip, $port);

        if (!is_null($ret)) {
            return $ret;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'Malformed address (IP address required)';
        }

        return null;
    }

    public static function parseHostPort(string $str, ?string &$host, ?int &$port): ?string
    {
        $parts = explode(':', $str);

        if (count($parts) !== 2) {
            return 'Malformed address (missing port number)';
        } else {
            $host = trim($parts[0]);
            $port = (int)$parts[1];

            if (!$port || ($port > 65535)) {
                return 'Malformed address (port number out of bounds)';
            }
        }

        return null;
    }
}
