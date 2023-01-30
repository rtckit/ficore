<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use RTCKit\FiCore\Signal\Channel\Recording as RecordingSignal;
use RTCKit\FiCore\Switch\{
    Core,
    EventEnum
};

use stdClass as Event;

class RecordStop implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::RECORD_STOP;

    public function execute(Core $core, Event $event): void
    {
        if (!isset($this->app->config->recordingAttn, $event->{'Record-File-Path'}, $event->{'Unique-ID'})) {
            return;
        }

        if ($event->{'Record-File-Path'} === 'all') {
            return;
        }

        $channel = $core->getChannel($event->{'Unique-ID'});

        if (!isset($channel)) {
            return;
        }

        $signal = new RecordingSignal();
        $signal->attn = $this->app->config->recordingAttn;
        $signal->timestamp = (int)$event->{'Event-Date-Timestamp'} / 1e6;
        $signal->event = $event;
        $signal->channel = $channel;
        $signal->medium = $event->{'Record-File-Path'};
        $signal->duration = isset($event->{'variable_record_seconds'}) ? (float)$event->{'variable_record_seconds'} : 0;

        $this->app->signalProducer->produce($signal);
    }
}
