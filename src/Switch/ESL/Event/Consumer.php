<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;

use React\Promise\PromiseInterface;
use RTCKit\FiCore\AbstractApp;
use RTCKit\FiCore\Signal\Channel\Hangup as HangupSignal;
use RTCKit\FiCore\Switch\{
    Channel,
    Conference,
    Core,
    EventEnum,
    HangupCauseEnum,
    OriginateJob,
    StatusEnum,
};
use stdClass as Event;

class Consumer extends AbstractConsumer
{
    public AbstractApp $app;

    public function setApp(AbstractApp $app): Consumer
    {
        $this->app = $app;

        return $this;
    }

    public function onEvent(Core $core, Event $event): void
    {
        if (isset($this->app->eslClient->handlers[$event->{'Event-Name'}])) {
            $this->app->eslClient->handlers[$event->{'Event-Name'}]->execute($core, $event);
        }
    }

    public function subscribe(Core $core): PromiseInterface
    {
        return $core->client->event('json ' . implode(' ', [
            EventEnum::BACKGROUND_JOB->value,
            EventEnum::CHANNEL_PROGRESS->value,
            EventEnum::CHANNEL_PROGRESS_MEDIA->value,
            EventEnum::CHANNEL_HANGUP_COMPLETE->value,
            EventEnum::CHANNEL_STATE->value,
            EventEnum::SESSION_HEARTBEAT->value,
            EventEnum::CALL_UPDATE->value,
            EventEnum::RECORD_STOP->value,
            EventEnum::CUSTOM->value . ' conference::maintenance amd::info avmd::beep avmd::timeout',
        ]));
    }

    public function run(): void
    {
        $this->logger = new Logger('event.consumer');
        $this->logger->pushHandler(
            (new PsrHandler($this->app->stdioLogger, $this->app->config->eventConsumerLogLevel))->setFormatter(new LineFormatter())
        );
        $this->logger->debug('Starting ...');
    }

    public function hangupCompleted(Event $event, HangupCauseEnum $reason, ?string $url = null, ?OriginateJob $originateJob = null, ?Channel $channel = null): void
    {
        $signal = new HangupSignal();
        $signal->timestamp = (int)$event->{'Event-Date-Timestamp'} / 1e6;
        $signal->event = $event;
        $signal->reason = $reason;

        if (isset($originateJob)) {
            $originateJob->core->removeOriginateJob($originateJob->uuid);

            $signal->originateJob = $originateJob;

            if (isset($originateJob->onHangupAttn)) {
                $signal->attn = $originateJob->onHangupAttn;
            }

            if (isset($originateJob->channel)) {
                $this->logger->info("outgoing channel {$originateJob->channel->uuid} hangup ({$reason->value})");
                $originateJob->core->removeChannel($originateJob->channel->uuid);
            } else {
                $this->logger->info("outgoing channel hangup ({$reason->value}), request {$originateJob->uuid}");
            }
        } elseif (isset($channel)) {
            $channel->core->removeChannel($channel->uuid);

            $signal->channel = $channel;

            $hangupSigVar = "variable_{$this->app->config->appPrefix}_hangup_attn";

            if (isset($signal->event->{$hangupSigVar})) {
                $signal->attn = $signal->event->{$hangupSigVar};
            }

            $this->logger->info("incoming channel {$channel->uuid} hangup ({$reason->value})");
        }

        $this->app->signalProducer->produce($signal);
    }
}
