<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Originate;

use Ramsey\Uuid\Uuid;
use function React\Promise\resolve;

use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use RTCKit\FiCore\Command\{
    AbstractHandler,
    RequestInterface,
};
use RTCKit\FiCore\Exception\CoreException;
use RTCKit\FiCore\Switch\{
    Core,
    HangupCauseEnum,
    Job,
    OriginateJob,
    ScheduledHangup,
};

class Handler extends AbstractHandler
{
    public function execute(RequestInterface $request): PromiseInterface
    {
        assert($request instanceof Request);

        $enterprise = $request->action === ActionEnum::Enterprise;
        $response = new Response();
        $response->successful = true;
        $response->originateJobs = [];

        if (!isset($request->core)) {
            try {
                $request->core = $this->app->allocateCore();
            } catch (CoreException $t) {
                $response->successful = false;

                return resolve($response);
            }
        }

        if ($enterprise) {
            $job = $this->spawnJob($request, implode(',', $request->destinations));
        }

        foreach ($request->destinations as $idx => $to) {
            if (!$enterprise) {
                $job = $this->spawnJob($request, $to);
            }

            assert(isset($job));
            assert($job instanceof OriginateJob);

            $response->originateJobs[] = $job;

            $globals = [
                "{$this->app->config->appPrefix}_request_uuid={$job->uuid}",
                "{$this->app->config->appPrefix}_answer_seq={$request->sequence}",
                "{$this->app->config->appPrefix}_ring_attn={$job->onRingAttn}",
                "{$this->app->config->appPrefix}_hangup_attn={$request->onHangupAttn}",
                "origination_caller_id_number={$request->source}",
            ];

            if ($enterprise) {
                $globals[] = "{$this->app->config->appPrefix}_app=true";
                $globals[] = "{$this->app->config->appPrefix}_group_call=true";
                $globals[] = 'ignore_early_media=true';
                $globals[] = "fail_on_single_reject='" . implode(',', $request->rejectCauses) . "'";

                if (isset($request->confirmMedia[0])) {
                    $playStr = 'file_string://silence_stream://1!' . implode('!', $request->confirmMedia);

                    if (isset($request->confirmTone)) {
                        $globals[] = 'group_confirm_file=' . $playStr;
                        $globals[] = 'group_confirm_key=' . $request->confirmTone;
                    } else {
                        $globals[] = 'group_confirm_file=playback ' . $playStr;
                        $globals[] = 'group_confirm_key=exec';
                    }

                    $globals[] = 'group_confirm_cancel_timeout=1';
                    $globals[] = 'playback_delimiter=!';
                }
            }

            $gateways = $request->gateways[$idx];

            $vars = [];

            if (isset($request->sourceNames[$idx])) {
                $vars[] = "origination_caller_id_name='". str_replace("'", "\\'", $request->sourceNames[$idx]) . "'";
            }

            if (!empty($request->extraDialString)) {
                $extra = str_getcsv($request->extraDialString, ',', "'");

                foreach ($extra as $var) {
                    $vars[] = $var;
                }
            }

            foreach ($request->tags as $tag => $value) {
                $vars[] = "{$this->app->config->appPrefix}_tag_{$tag}={$value}";
            }

            $hupOnRing = -1;
            $execOnMedia = 1;

            if (isset($request->onRingHangup[$idx])) {
                $hupOnRing = $request->onRingHangup[$idx];
            }

            if (!$hupOnRing) {
                $vars[] = "execute_on_media='hangup ORIGINATOR_CANCEL'";
                $vars[] = "execute_on_ring='hangup ORIGINATOR_CANCEL'";
                $execOnMedia++;
            } elseif ($hupOnRing > 1) {
                $vars[] = "execute_on_media_{$execOnMedia}='sched_hangup +{$hupOnRing} ORIGINATOR_CANCEL'";
                $vars[] = "execute_on_ring='sched_hangup +{$hupOnRing} ORIGINATOR_CANCEL'";
                $execOnMedia++;
            }

            if (isset($request->onMediaDTMF, $request->onMediaDTMF[$idx])) {
                $vars[] = "execute_on_media_{$execOnMedia}='send_dtmf {$request->onMediaDTMF[$idx]}'";
            }

            if (isset($request->onAnswerDTMF, $request->onAnswerDTMF[$idx])) {
                $vars[] = "execute_on_answer='send_dtmf {$request->onAnswerDTMF[$idx]}'";
            }

            $timeLimit = -1;

            if (isset($request->maxDuration[$idx])) {
                $timeLimit = $request->maxDuration[$idx];
            }

            if ($timeLimit > 0) {
                $schedHup = new ScheduledHangup();
                $schedHup->uuid = Uuid::uuid4()->toString();
                $schedHup->timeout = $timeLimit;

                $request->core->addScheduledHangup($schedHup);

                $vars[] = "api_on_answer_1='sched_api +{$timeLimit} {$schedHup->uuid} hupall " .
                    HangupCauseEnum::ALLOTTED_TIMEOUT->value .
                    " {$this->app->config->appPrefix}_request_uuid {$job->uuid}'";
                $vars[] = "{$this->app->config->appPrefix}_sched_hangup_id={$schedHup->uuid}";
            }

            if (isset($request->amd)) {
                $vars[] = "{$this->app->config->appPrefix}_amd=on";
                $vars[] = "{$this->app->config->appPrefix}_amd_timeout={$request->amdTimeout}";

                if ($request->onAmdMachineHangup) {
                    $vars[] = "{$this->app->config->appPrefix}_amd_msg_end=on";
                }

                $vars[] = "{$this->app->config->appPrefix}_amd_async=" . ($request->amdAsync ? 'on' : 'off');

                if ($request->amdAsync && isset($request->onAmdAttn)) {
                    $vars[] = "{$this->app->config->appPrefix}_amd_attn={$request->onAmdAttn}";
                }

                $amdTimeoutMs = $request->amdTimeout * 1000;
                $amdInitialSilenceTimeoutMs = (int)$request->amdInitialSilenceTimeout * 1000;
                $amdSilenceThresholdMs = (int)$request->amdSilenceThreshold * 1000;
                $amdSpeechThresholdMs = (int)$request->amdSpeechThreshold * 1000;

                $vars[] = "execute_on_answer='amd total_analysis_time={$amdTimeoutMs} maximum_word_length={$amdSpeechThresholdMs} after_greeting_silence={$amdSilenceThresholdMs} initial_silence={$amdInitialSilenceTimeoutMs}'";
            }

            $vars[] = "{$this->app->config->appPrefix}_from='{$request->source}'";
            $vars[] = "{$this->app->config->appPrefix}_to='{$to}'";

            $originateStr = [];

            foreach ($gateways as $gateway) {
                $gwVars = [];

                $gwVars[] = "{$this->app->config->appPrefix}_app=true";

                if (!empty($gateway->codecs)) {
                    $gwVars[] = "absolute_codec_string='" . $gateway->codecs . "'";
                }

                if (!empty($gateway->timeout)) {
                    $gwVars[] = 'originate_timeout=' . $gateway->timeout;
                }

                $gwVars[] = 'ignore_early_media=true';

                $endpoint = $gateway->name . $to;
                $retries = $gateway->tries;

                for ($i = 0; $i < $retries; $i++) {
                    if ($enterprise) {
                        $originateStr[] = '[' . implode(',', $gwVars) . ']' . $endpoint;
                    } else {
                        $originateStr[] = 'originate {' . implode(',', array_merge($globals, $vars, $gwVars)) . '}' . $endpoint .
                            " &socket('{$this->app->config->eslServerAdvertisedIp}:{$this->app->config->eslServerAdvertisedPort} async full')";
                    }
                }
            }

            if ($enterprise) {
                $job->originateStr[] = '{' . implode(',', $vars) . '}' . implode(',', $originateStr);
            } else {
                $job->originateStr = $originateStr;

                $this->loopGateways($job)
                    ->otherwise(function (\Throwable $t) {
                        $t = $t->getPrevious() ?: $t;

                        $this->app->eslClient->logger->error('Originate channel exception: ' . $t->getMessage(), [
                            'file' => $t->getFile(),
                            'line' => $t->getLine(),
                        ]);
                    });
            }
        }

        if (!$enterprise) {
            return resolve($response);
        }

        $command = 'originate <' . implode(',', $globals) . '>' . implode(':_:', $job->originateStr) .
            " &socket('{$this->app->config->eslServerAdvertisedIp}:{$this->app->config->eslServerAdvertisedPort} async full')";
        $job->originateStr = [];

        $this->app->commandConsumer->logger->debug("Orginate::Enterprise", [$command]);

        return $request->core->client->bgApi(
            (new ESL\Request\BgApi())->setParameters($command)
        )
            ->then(function (ESL\Response $eslResponse) use ($response, $job): Response {
                $uuid = null;

                if ($eslResponse instanceof ESL\Response\CommandReply) {
                    $uuid = $eslResponse->getHeader('job-uuid');
                }

                if (!isset($uuid)) {
                    $response->successful = false;
                    $response->originateJobs = [];
                    $job->core->removeOriginateJob($job->uuid);

                    $this->app->commandConsumer->logger->error("Orginate::Enterprise failed", [$eslResponse]);
                } else {
                    $bgJob = new Job();
                    $bgJob->uuid = $uuid;
                    $bgJob->command = 'originate';
                    $bgJob->group = true;
                    $bgJob->originateJob = $job;
                    $bgJob->deferred = new Deferred();

                    $job->core->addJob($bgJob);

                    assert(!isset($job->job));

                    $job->job = $bgJob;
                }

                return $response;
            });
    }

    protected function spawnJob(RequestInterface $request, string $destination): OriginateJob
    {
        assert($request instanceof Request);

        $job = new OriginateJob();
        $job->uuid = Uuid::uuid4()->toString();
        $job->core = $request->core;
        $job->destination = $destination;
        $job->source = $request->source;
        $job->onRingAttn = $request->onRingAttn ?? '';
        $job->onHangupAttn = $request->onHangupAttn;
        $job->originateStr = [];
        $job->tags = $request->tags;

        $request->core->addOriginateJob($job);

        return $job;
    }

    protected function loopGateways(OriginateJob $originateJob): PromiseInterface
    {
        $originateString = array_shift($originateJob->originateStr);

        assert(!is_null($originateString));

        return $originateJob->core->client->bgApi((new ESL\Request\BgApi())->setParameters($originateString))
            ->then(function (ESL\Response $response) use ($originateJob): PromiseInterface {
                if ($response instanceof ESL\Response\CommandReply) {
                    $uuid = $response->getHeader('job-uuid');

                    if (isset($uuid)) {
                        $job = new Job();
                        $job->uuid = $uuid;
                        $job->command = 'originate';
                        $job->originateJob = $originateJob;
                        $job->deferred = new Deferred();

                        $originateJob->core->addJob($job);

                        assert(!isset($originateJob->job));

                        $originateJob->job = $job;

                        $this->app->commandConsumer->logger->debug("Waiting Call attempt for RequestUUID {$originateJob->uuid} ...");

                        return $job->deferred->promise()
                            ->then(function (bool $success) use ($originateJob): PromiseInterface {
                                unset($originateJob->job);

                                if ($success) {
                                    $this->app->commandConsumer->logger->info("Call Attempt OK for RequestUUID {$originateJob->uuid}");

                                    return resolve();
                                }

                                $this->app->commandConsumer->logger->info("Call Attempt Failed for RequestUUID {$originateJob->uuid}, retrying next gateway ...");

                                return $this->loopGateways($originateJob);
                            });
                    }
                }

                $this->app->commandConsumer->logger->error("Call Failed for RequestUUID {$originateJob->uuid} -- JobUUID not received");

                return $this->loopGateways($originateJob);
            })
            ->otherwise(function (\Throwable $t) {
                $t = $t->getPrevious() ?: $t;

                $this->app->eslClient->logger->error('loopGateways exception: ' . $t->getMessage(), [
                    'file' => $t->getFile(),
                    'line' => $t->getLine(),
                ]);
            });
    }
}
