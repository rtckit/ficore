<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Originate;

use RTCKit\FiCore\Command\RequestInterface;
use RTCKit\FiCore\Switch\{
    Core,
    Gateway,
};

class Request implements RequestInterface
{
    public ActionEnum $action;

    public Core $core;

    public string $source;

    /** @var list<string> */
    public array $sourceNames;

    /** @var non-empty-array<int, string> */
    public array $destinations;

    public string $extraDialString;

    public bool $amd;

    public bool $amdAsync;

    public int $amdTimeout;

    public float $amdInitialSilenceTimeout;

    public float $amdSilenceThreshold;

    public float $amdSpeechThreshold;

    /** @var list<int> */
    public array $maxDuration;

    public string $sequence;

    public string $onAmdAttn;

    public string $onRingAttn;

    public string $onHangupAttn;

    /** @var list<int> */
    public array $onRingHangup;

    public bool $onAmdMachineHangup;

    /** @var non-empty-array<int<0, max>, string> */
    public array $onMediaDTMF;

    /** @var non-empty-array<int<0, max>, string> */
    public array $onAnswerDTMF;

    /** @var list<string> */
    public array $rejectCauses;

    /** @var list<string> */
    public array $confirmMedia;

    public string $confirmTone;

    /** @var array<string, string> */
    public array $tags = [];

    /** @var non-empty-array<int<0, max>, list<Gateway>> */
    public array $gateways;
}
