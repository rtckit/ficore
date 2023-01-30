<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;

use React\EventLoop\TimerInterface;
use function React\Promise\resolve;
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use RTCKit\FiCore\AbstractApp;
use RTCKit\FiCore\Plan\AbstractElement;
use RTCKit\React\ESL\RemoteOutboundClient;
use stdClass as Event;

class Channel
{
    public static int $instances = 0;

    public AbstractApp $app;

    public Core $core;

    public RemoteOutboundClient $client;

    /** @var array<string, string> */
    public array $context;

    public string $coreUuid;

    public string $uuid;

    public StatusEnum $status;

    public bool $answered = false;

    public string $aLegUuid;

    public string $aLegRequestUuid;

    public string $callerNumber;

    public string $callerName;

    public string $from;

    public string $calledNumber;

    public string $to;

    public string $schedHangupId = '';

    public string $forwardedFrom;

    public bool $outbound;

    public HangupCauseEnum $hangupCause;

    public string $targetSequence;

    /** @var list<AbstractElement> */
    public array $elements;

    public AbstractElement $currentElement;

    /** @var array<?Event> */
    public array $eventQueue = [];

    public Deferred $eventQueueDeferred;

    public TimerInterface $eventQueueTimer;

    public bool $raiseExceptionOnHangup = false;

    public mixed $payload;

    public bool $transferInProgress;

    public bool $preAnswer = false;

    /** @var array<string, string> */
    public array $tags = [];

    public TimerInterface $avmdTimer;

    public float $amdDuration;

    /** @psalm-suppress PossiblyUnusedProperty */
    public bool $amdAsync;

    /** @psalm-suppress PossiblyUnusedProperty */
    public string $amdAnsweredBy;

    public string $ttsEngine;

    public string $ttsVoice;

    public function __construct()
    {
        self::$instances++;
    }

    public function __destruct()
    {
        self::$instances--;
    }

    public function bgApi(ESL\Request\BgApi $request): PromiseInterface
    {
        return $this->client->bgApi($request)
            ->then(function (ESL\Response $response) use ($request): PromiseInterface {
                if ($response instanceof ESL\Response\CommandReply) {
                    $uuid = $response->getHeader('job-uuid');

                    if (!is_null($uuid)) {
                        $job = new Job();
                        $job->uuid = $uuid;
                        $job->command = explode(' ', $request->getParameters() ?? '')[0];

                        $this->core->addJob($job);
                    }
                }

                return resolve($response);
            });
    }
}
