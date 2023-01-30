<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;

class SpeechInterpretation
{
    public string $grammar;

    public float $confidence;

    public string $mode;

    public string $input;
}
