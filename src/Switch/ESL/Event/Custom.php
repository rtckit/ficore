<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use React\EventLoop\Loop;
use RTCKit\ESL;
use RTCKit\FiCore\Signal\Channel\MachineDetection as MachineDetectionSignal;

use RTCKit\FiCore\Signal\Conference\Recording as RecordingSignal;
use RTCKit\FiCore\Switch\{
    Core,
    EventEnum,
    MachineDetectionEnum,
};
use stdClass as Event;

class Custom implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CUSTOM;

    protected bool $amdEvent = false;

    public function execute(Core $core, Event $event): void
    {
        if (!isset($event->{'Event-Subclass'})) {
            return;
        }

        switch ($event->{'Event-Subclass'}) {
            case 'conference::maintenance':
                if (!isset($event->Action, $event->{'Conference-Unique-ID'})) {
                    return;
                }

                $conference = $core->getConference($event->{'Conference-Unique-ID'});

                if (!isset($conference)) {
                    return;
                }

                switch ($event->Action) {
                    case 'stop-recording':
                        if (!isset($this->app->config->recordingAttn)) {
                            return;
                        }

                        $signal = new RecordingSignal();
                        $signal->attn = $this->app->config->recordingAttn;
                        $signal->timestamp = (int)$event->{'Event-Date-Timestamp'} / 1e6;
                        $signal->event = $event;
                        $signal->conference = $conference;
                        $signal->medium = $event->Path;
                        $signal->duration = (isset($event->{'Milliseconds-Elapsed'}) ? (float)$event->{'Milliseconds-Elapsed'} : 0) / 1000;

                        $this->app->signalProducer->produce($signal);
                        return;

                    case 'conference-destroy':
                        $core->removeConference($event->{'Conference-Unique-ID'});
                        return;
                }

                return;

            case 'amd::info':
                $channel = $core->getChannel($event->{'Unique-ID'});

                if (!isset($channel)) {
                    return;
                }

                $this->app->eslClient->logger->info('AMD event ' . json_encode($event));
                $this->amdEvent = true;

                $channel->amdDuration = ((int)$event->variable_amd_result_microtime - (int)$event->{'Caller-Channel-Answered-Time'}) / 1e6;
                $channel->amdAnsweredBy = 'unknown';

                $result = $event->variable_amd_result ?? 'NOTSURE';
                $isMachine = false;

                switch ($result) {
                    case 'HUMAN':
                        $channel->amdAnsweredBy = 'human';
                        break;

                    case 'MACHINE':
                        $isMachine = true;
                        $channel->amdAnsweredBy = 'machine_start';
                        break;
                }

                $asyncVar = "variable_{$this->app->config->appPrefix}_amd_async";
                $channel->amdAsync = isset($event->{$asyncVar}) && ($event->{$asyncVar} === 'on');

                if ($isMachine && isset($event->{"variable_{$this->app->config->appPrefix}_amd_msg_end"})) {
                    /* Kick off AVMD */
                    $this->app->eslClient->logger->info('Activating AVMD (DetectMessageEnd enabled)');

                    $channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'avmd_start')
                    );

                    $timeout = (float)$event->{"variable_{$this->app->config->appPrefix}_amd_timeout"} - ($channel->amdDuration / 1000);

                    $channel->avmdTimer = Loop::addTimer($timeout, function () use ($channel): void {
                        unset($channel->avmdTimer);

                        $channel->client->sendMsg(
                            (new ESL\Request\SendMsg())
                                ->setHeader('call-command', 'execute')
                                ->setHeader('execute-app-name', 'event')
                                ->setHeader('execute-app-arg', 'Event-Subclass=avmd::timeout,Event-Name=CUSTOM')
                        );
                    });

                    return;
                }

                // no break
            case 'avmd::timeout':
            case 'avmd::beep':
                if (!$this->amdEvent) {
                    $channel = $core->getChannel($event->{'Unique-ID'});

                    if (!isset($channel)) {
                        return;
                    }

                    $this->app->eslClient->logger->info('AVMD event ' . json_encode($event));

                    if (isset($channel->avmdTimer)) {
                        Loop::cancelTimer($channel->avmdTimer);
                        unset($channel->avmdTimer);
                    }

                    $channel->client->sendMsg(
                        (new ESL\Request\SendMsg())
                            ->setHeader('call-command', 'execute')
                            ->setHeader('execute-app-name', 'avmd_stop')
                    );

                    $channel->amdAnsweredBy = ($event->{'Event-Subclass'} === 'avmd::beep') ? 'machine_end_beep' : 'machine_end_other';
                }

                if (!isset($channel)) {
                    return;
                }

                $channel->amdAsync = $event->{"variable_{$this->app->config->appPrefix}_amd_async"} === 'on';

                $signal = new MachineDetectionSignal();
                $signal->channel = $channel;
                $signal->timestamp = (int)$event->{'Event-Date-Timestamp'} / 1e6;
                $signal->event = $event;
                $signal->result = MachineDetectionEnum::from($channel->amdAnsweredBy);
                $signal->duration = (float)$channel->amdDuration;

                if ($channel->amdAsync) {
                    $this->app->eslClient->logger->info('Asynchronous AMD completed');

                    if (isset($event->{"variable_{$this->app->config->appPrefix}_amd_attn"})) {
                        $signal->attn = $event->{"variable_{$this->app->config->appPrefix}_amd_attn"};

                        $this->app->signalProducer->produce($signal)
                            ->otherwise(function (\Throwable $t) {
                                $t = $t->getPrevious() ?: $t;

                                $this->app->eslClient->logger->error('Asynchronous AMD failure: ' . $t->getMessage(), [
                                    'file' => $t->getFile(),
                                    'line' => $t->getLine(),
                                ]);
                            });
                    }
                } else {
                    if (isset($event->{"variable_{$this->app->config->appPrefix}_amd_seq"})) {
                        $this->app->planConsumer->consumeEntryPoint($channel, $event->{"variable_{$this->app->config->appPrefix}_amd_seq"}, $signal);
                    }
                }

                return;
        }
    }
}
