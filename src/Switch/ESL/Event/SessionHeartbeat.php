<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use RTCKit\FiCore\Signal\Channel\Heartbeat as HeartbeatSignal;
use RTCKit\FiCore\Switch\{
    Core,
    EventEnum,
    HangupCauseEnum,
    StatusEnum
};

use stdClass as Event;

class SessionHeartbeat implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::SESSION_HEARTBEAT;

    public function execute(Core $core, Event $event): void
    {
        if (!isset($this->app->config->heartbeatAttn)) {
            return;
        }

        $channel = $core->getChannel($event->{'Unique-ID'});

        if (!isset($channel, $channel)) {
            return;
        }

        $signal = new HeartbeatSignal();
        $signal->attn = $this->app->config->heartbeatAttn;
        $signal->timestamp = (int)$event->{'Event-Date-Timestamp'} / 1e6;
        $signal->event = $event;
        $signal->channel = $channel;

        $this->app->signalProducer->produce($signal);
    }
}
