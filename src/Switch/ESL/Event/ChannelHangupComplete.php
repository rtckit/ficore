<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use RTCKit\FiCore\Switch\{
    Core,
    EventEnum,
    HangupCauseEnum,
    StatusEnum
};

use stdClass as Event;

class ChannelHangupComplete implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CHANNEL_HANGUP_COMPLETE;

    public function execute(Core $core, Event $event): void
    {
        $direction = $event->{'Call-Direction'};

        if ($direction === 'inbound') {
            $channel = $core->getChannel($event->{'Unique-ID'});
            $reason = HangupCauseEnum::from($event->{'Hangup-Cause'});
            $this->app->eventConsumer->hangupCompleted(event: $event, reason: $reason, channel: $channel);
        } else {
            $reqUuidVar = "variable_{$this->app->config->appPrefix}_request_uuid";

            if (!isset($event->{$reqUuidVar}) && ($direction === 'outbound')) {
                return;
            }

            $channel = $core->getChannel($event->{'Unique-ID'});
            $reason = HangupCauseEnum::from($event->{'Hangup-Cause'});
            $groupCallVar = "variable_{$this->app->config->appPrefix}_group_call";
            $originateJob = null;

            if (isset($event->{$groupCallVar}) && ($event->{$groupCallVar} === 'true')) {
                $hangupSig = $event->{"variable_{$this->app->config->appPrefix}_hangup_attn"};
            } else {
                $originateJob = $core->getOriginateJob($event->{$reqUuidVar});

                if (!isset($originateJob)) {
                    return;
                }

                if (count($originateJob->originateStr)) {
                    $this->app->eslClient->logger->debug("Call Failed for RequestUUID {$originateJob->uuid} - Retrying ({$reason->value})");
                    $this->app->eslClient->logger->debug("Notify Call retry for RequestUUID {$originateJob->uuid}");
                    $originateJob->job->deferred->resolve(false);
                    return;
                }

                $hangupSig = $originateJob->onHangupAttn;

                $this->app->eslClient->logger->debug("Notify Call success for RequestUUID {$originateJob->uuid}");

                if (isset($originateJob->job)) {
                    $originateJob->job->deferred->resolve(true);
                }
            }

            $this->app->eventConsumer->hangupCompleted(event: $event, reason: $reason, url: $hangupSig, originateJob: $originateJob, channel: $channel);
        }
    }
}
