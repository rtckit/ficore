<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use RTCKit\FiCore\Signal\Channel\Progress as ProgressSignal;
use RTCKit\FiCore\Switch\{
    Core,
    EventEnum,
    StatusEnum
};

use stdClass as Event;

class ChannelProgress implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CHANNEL_PROGRESS;

    public function execute(Core $core, Event $event): void
    {
        $reqUuidVar = "variable_{$this->app->config->appPrefix}_request_uuid";

        if (!isset($event->{$reqUuidVar}, $event->{'Call-Direction'})) {
            return;
        }

        if ($event->{'Call-Direction'} !== 'outbound') {
            return;
        }

        $status = ($event->{'Event-Name'} === EventEnum::CHANNEL_PROGRESS->value)
            ? StatusEnum::Ringing
            : StatusEnum::EarlyMedia;

        $signal = new ProgressSignal();
        $signal->timestamp = (int)$event->{'Event-Date-Timestamp'} / 1e6;
        $signal->event = $event;
        $signal->status = $status;

        $groupCallVar = "variable_{$this->app->config->appPrefix}_group_call";

        if (isset($event->{$groupCallVar}) && ($event->{$groupCallVar} === 'true')) {
            $ringAttnVar = "variable_{$this->app->config->appPrefix}_ring_attn";
            $signal->attn = isset($event->{$ringAttnVar}) ? $event->{$ringAttnVar} : '';
        } else {
            $originateJob = $core->getOriginateJob($event->{$reqUuidVar});

            if (!isset($originateJob)) {
                return;
            }

            $signal->originateJob = $originateJob;

            $this->app->eventConsumer->logger->debug("outbound channel origination request {$originateJob->uuid} progress ({$status->value})");
            $originateJob->job->deferred->resolve(true);

            if (!isset($originateJob->status)) {
                $originateJob->status = $status;
                $originateJob->originateStr = [];
                $signal->attn = $originateJob->onRingAttn;
            }
        }

        $this->app->signalProducer->produce($signal);
    }
}
