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

class BackgroundJob implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::BACKGROUND_JOB;

    public function execute(Core $core, Event $event): void
    {
        if (!isset($event->{'Job-UUID'})) {
            return;
        }

        $job = $core->getJob($event->{'Job-UUID'});

        if (!isset($job)) {
            return;
        }

        switch ($job->command) {
            case 'originate':
                $originateJob = $core->getOriginateJob($job->originateJob->uuid);

                if (!isset($originateJob)) {
                    break;
                }

                $parts = explode(' ', isset($event->_body) ? trim($event->_body) : '', 2);

                if (!isset($parts[1])) {
                    break;
                }

                if ($job->group) {
                    if (strpos($parts[0], '+OK') === 0) {
                        $this->app->eslClient->logger->warning("GroupCall Attempt Done for RequestUUID {$originateJob->uuid} ({$parts[1]})");
                    } else {
                        $this->app->eslClient->logger->warning("GroupCall Attempt Failed for RequestUUID {$originateJob->uuid} ({$parts[1]})");
                    }

                    $originateJob->core->removeOriginateJob($originateJob->uuid);

                    break;
                }

                if (strpos($parts[0], '+OK') === false) {
                    if (strpos($parts[0], '-USAGE') === 0) {
                        $parts[1] = HangupCauseEnum::INVALID_NUMBER_FORMAT->value;
                    }

                    if (isset($originateJob->status) && in_array($originateJob->status, [StatusEnum::Ringing, StatusEnum::EarlyMedia])) {
                        $this->app->eslClient->logger->warning("Call Attempt Done ({$originateJob->status->value}) for RequestUUID {$originateJob->uuid} but Failed ({$parts[1]})");
                        $this->app->eslClient->logger->debug("Notify Call success for RequestUUID {$originateJob->uuid}");
                        $job->deferred->resolve(true);
                        break;
                    } elseif (!count($originateJob->originateStr)) {
                        $this->app->eslClient->logger->warning("Call Failed for RequestUUID {$originateJob->uuid} but No More Gateways ({$parts[1]})");
                        $this->app->eslClient->logger->debug("Notify Call success for RequestUUID {$originateJob->uuid}");
                        $job->deferred->resolve(true);
                        $this->app->eventConsumer->hangupCompleted(event: $event, reason: HangupCauseEnum::from($parts[1]), originateJob: $originateJob);
                        break;
                    } else {
                        $this->app->eslClient->logger->warning("Call Failed without Ringing/EarlyMedia for RequestUUID {$originateJob->uuid} - Retrying Now ({$parts[1]})");
                        $this->app->eslClient->logger->debug("Notify Call retry for RequestUUID {$originateJob->uuid}");
                        $job->deferred->resolve(false);
                        break;
                    }
                }

                break;

            case 'conference':
                $result = isset($event->_body) ? trim($event->_body) : '';
                $this->app->eslClient->logger->info("Conference Api Response for JobUUID {$job->uuid} -- {$result}");
                break;
        }

        $core->removeJob($event->{'Job-UUID'});
    }
}
