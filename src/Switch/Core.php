<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;

use React\EventLoop\Loop;

use RTCKit\FiCore\AbstractApp;
use RTCKit\React\ESL\InboundClient;

class Core
{
    public AbstractApp $app;

    public InboundClient $client;

    public string $uuid;

    /**
     * @var array<string, string> Core's global variables
     * @psalm-suppress PossiblyUnusedProperty
     */
    public array $vars;

    /** @var array<string, Channel> */
    protected array $channels = [];

    /** @var array<string, Conference> */
    protected array $conferences = [];

    /** @var array<string, Conference> */
    protected array $conferencesByRoom = [];

    /** @var array<string, Job> */
    protected array $jobs = [];

    /** @var array<string, OriginateJob> */
    protected array $originateJobs = [];

    /** @var array<string, ScheduledHangup> */
    protected array $scheduledHangups = [];

    /** @var array<string, ScheduledPlay> */
    protected array $scheduledPlays = [];

    public function setClient(InboundClient $client): void
    {
        if (isset($this->client)) {
            unset($this->client);
        }

        $this->client = $client;
    }

    public function addChannel(Channel $channel): void
    {
        $this->channels[$channel->uuid] = $channel;
        $channel->core = $this;
        $channel->app = $this->app;

        $this->app->addChannel($channel);
    }

    public function getChannel(string $uuid): ?Channel
    {
        return isset($this->channels[$uuid]) ? $this->channels[$uuid] : null;
    }

    public function removeChannel(string $uuid): void
    {
        if (isset($this->channels[$uuid])) {
            $this->app->removeChannel($uuid);
            unset($this->channels[$uuid]);
        }
    }

    public function addConference(Conference $conference): void
    {
        $this->conferences[$conference->uuid] = $conference;
        $this->conferencesByRoom[$conference->room] = $conference;
        $conference->core = $this;

        $this->app->addConference($conference);
    }

    public function getConference(string $uuid): ?Conference
    {
        return isset($this->conferences[$uuid]) ? $this->conferences[$uuid] : null;
    }

    public function getConferenceByRoom(string $room): ?Conference
    {
        return isset($this->conferencesByRoom[$room]) ? $this->conferencesByRoom[$room] : null;
    }

    public function removeConference(string $uuid): void
    {
        if (isset($this->conferences[$uuid])) {
            if (isset($this->conferencesByRoom[$this->conferences[$uuid]->room])) {
                unset($this->conferencesByRoom[$this->conferences[$uuid]->room]);
            }

            $this->app->removeConference($this->conferences[$uuid]->room);
            unset($this->conferences[$uuid]);
        }
    }

    public function addJob(Job $job): void
    {
        $this->jobs[$job->uuid] = $job;
    }

    public function getJob(string $uuid): ?Job
    {
        return isset($this->jobs[$uuid]) ? $this->jobs[$uuid] : null;
    }

    public function removeJob(string $uuid): void
    {
        if (isset($this->jobs[$uuid])) {
            unset($this->jobs[$uuid]);
        }
    }

    public function addOriginateJob(OriginateJob $originateJob): void
    {
        $this->originateJobs[$originateJob->uuid] = $originateJob;
        $originateJob->core = $this;

        $this->app->addOriginateJob($originateJob);
    }

    public function getOriginateJob(string $uuid): ?OriginateJob
    {
        return isset($this->originateJobs[$uuid]) ? $this->originateJobs[$uuid] : null;
    }

    public function removeOriginateJob(string $uuid): void
    {
        if (isset($this->originateJobs[$uuid])) {
            unset($this->originateJobs[$uuid]);
            $this->app->removeOriginateJob($uuid);
        }
    }

    public function addScheduledHangup(ScheduledHangup $scheduledHangup): void
    {
        $this->scheduledHangups[$scheduledHangup->uuid] = $scheduledHangup;
        $scheduledHangup->core = $this;

        $this->app->addScheduledHangup($scheduledHangup);

        Loop::addTimer($scheduledHangup->timeout + 5, function () use ($scheduledHangup) {
            $this->removeScheduledHangup($scheduledHangup->uuid);
            unset($scheduledHangup);
        });
    }

    /** @psalm-suppress PossiblyUnusedMethod */
    public function getScheduledHangup(string $uuid): ?ScheduledHangup
    {
        return isset($this->scheduledHangups[$uuid]) ? $this->scheduledHangups[$uuid] : null;
    }

    public function removeScheduledHangup(string $uuid): void
    {
        if (isset($this->scheduledHangups[$uuid])) {
            unset($this->scheduledHangups[$uuid]);
            $this->app->removeScheduledHangup($uuid);
        }
    }

    public function addScheduledPlay(ScheduledPlay $scheduledPlay): void
    {
        $this->scheduledPlays[$scheduledPlay->uuid] = $scheduledPlay;
        $scheduledPlay->core = $this;

        $this->app->addScheduledPlay($scheduledPlay);

        Loop::addTimer($scheduledPlay->timeout + 5, function () use ($scheduledPlay) {
            $this->removeScheduledPlay($scheduledPlay->uuid);
            unset($scheduledPlay);
        });
    }

    /** @psalm-suppress PossiblyUnusedMethod */
    public function getScheduledPlay(string $uuid): ?ScheduledPlay
    {
        return isset($this->scheduledPlays[$uuid]) ? $this->scheduledPlays[$uuid] : null;
    }

    public function removeScheduledPlay(string $uuid): void
    {
        if (isset($this->scheduledPlays[$uuid])) {
            unset($this->scheduledPlays[$uuid]);
            $this->app->removeScheduledPlay($uuid);
        }
    }

    /**
     * Returns core's object count
     *
     * @return array<string, int>
     */
    public function gatherStats(): array
    {
        return [
            'OriginateJob' => count($this->originateJobs),
            'Conference' => count($this->conferences),
            'Job' => count($this->jobs),
            'ScheduledHangup' => count($this->scheduledHangups),
            'ScheduledPlay' => count($this->scheduledPlays),
            'Channel' => count($this->channels),
        ];
    }
}
