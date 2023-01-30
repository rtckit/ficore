<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan;

use Monolog\Logger;
use React\Promise\PromiseInterface;
use RTCKit\FiCore\AbstractApp;

use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    Conference,
};

abstract class AbstractProducer
{
    public Logger $logger;

    abstract public function setApp(AbstractApp $app): static;

    /**
     * Exports channel's payload
     *
     * @param Channel $channel
     *
     * @return array<string, mixed>
     */
    abstract public function getChannelPayload(Channel $channel): array;

    /**
     * Exports conference's payload
     *
     * @param Conference $conference
     *
     * @return array<string, mixed>
     */
    abstract public function getConferencePayload(Conference $conference): array;

    /**
     * Fetches remote plan
     *
     * @param Channel $channel
     * @param string $sequence
     * @param ?AbstractSignal $signal
     *
     * @return PromiseInterface
     */
    abstract public function produce(Channel $channel, string $sequence, ?AbstractSignal $signal = null): PromiseInterface;

    abstract public function run(): void;

    abstract public function parseElements(mixed $input, Channel $channel): PromiseInterface;

    /**
     * Builds a playback array (to be concatenated for file_string://) from an element list
     *
     * @param Channel $channel
     * @param list<AbstractElement> $elements
     * @param list<string> $types
     *
     * @return list<string>
     */
    abstract public function buildPlaybackArray(Channel $channel, array $elements, array $types): array;
}
