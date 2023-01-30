<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Plan;

use Monolog\Logger;
use React\Promise\PromiseInterface;
use RTCKit\ESL;
use RTCKit\FiCore\AbstractApp;

use RTCKit\FiCore\Exception\HangupException;
use RTCKit\FiCore\Signal\AbstractSignal;
use RTCKit\FiCore\Switch\Channel;
use RTCKit\React\ESL\RemoteOutboundClient;
use stdClass as Event;

abstract class AbstractConsumer
{
    public Logger $logger;

    abstract public function setApp(AbstractApp $app): static;

    abstract public function run(): void;

    abstract public function onConnect(RemoteOutboundClient $client, ESL\Response\CommandReply $response): void;

    abstract public function onEvent(Channel $channel, Event $event): void;

    abstract public function waitForEvent(Channel $channel, int $timeout = 3600, bool $raiseExceptionOnHangup = false): PromiseInterface;

    abstract public function pushToEventQueue(Channel $channel, ?Event $event = null): void;

    /**
     * Fetches then executes the initial plan sequence for a given channel
     *
     * @param Channel $channel
     * @param string $sequence
     * @param ?AbstractSignal $signal
     *
     * @throws HangupException
     *
     * @return PromiseInterface
     */
    abstract public function consumeEntryPoint(Channel $channel, string $sequence, ?AbstractSignal $signal = null): PromiseInterface;

    /**
     * Fetches then executes a plan sequence
     *
     * @param Channel $channel
     * @param string $sequence
     * @param ?AbstractSignal $signal
     *
     * @throws HangupException
     *
     * @return PromiseInterface
     */
    abstract public function consume(Channel $channel, string $sequence, ?AbstractSignal $signal = null): PromiseInterface;

    abstract public function rewindExecuteSequence(Channel $channel): PromiseInterface;

    abstract public function executeSequence(Channel $channel): PromiseInterface;

    abstract public function executeElement(Channel $channel, AbstractElement $element): PromiseInterface;

    abstract public function getVariable(Channel $channel, string $variable): PromiseInterface;
}
