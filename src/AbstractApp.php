<?php

declare(strict_types=1);

namespace RTCKit\FiCore;

use ArrayIterator;
use InfiniteIterator;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use React\EventLoop\Loop;
use RTCKit\FiCore\Switch\{
    Channel,
    Conference,
    Core,
    ESL,
    Job,
    OriginateJob,
    ScheduledHangup,
    ScheduledPlay,
};
use const SIGINT;
use const SIGTERM;
use WyriHaximus\React\PSR3\Stdio\StdioLogger;

abstract class AbstractApp
{
    public Config\AbstractSet $config;

    /** @var list<Config\ResolverInterface> */
    protected array $configResolvers = [];

    public StdioLogger $stdioLogger;

    public ESL\Event\AbstractConsumer $eventConsumer;

    public Plan\AbstractConsumer $planConsumer;

    public Plan\AbstractProducer $planProducer;

    public Signal\AbstractProducer $signalProducer;

    public ESL\AbstractClient $eslClient;

    public ESL\AbstractServer $eslServer;

    public Command\AbstractConsumer $commandConsumer;

    /** @var array<string, Core> */
    protected array $cores = [];

    /** @var array<string, Channel> */
    protected array $channels = [];

    /** @var array<string, Conference> */
    protected array $conferences = [];

    /** @var array<string, Conference> */
    protected array $conferencesByRoom = [];

    /** @var array<string, OriginateJob> */
    protected array $originateJobs = [];

    /** @var array<string, ScheduledHangup> */
    protected array $scheduledHangups = [];

    /** @var array<string, ScheduledPlay> */
    protected array $scheduledPlays = [];

    /** @var InfiniteIterator */
    protected InfiniteIterator $coreIterator;

    protected Logger $statsLogger;

    public function setConfig(Config\AbstractSet $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function addConfigResolver(Config\ResolverInterface $resolver): static
    {
        $this->configResolvers[] = $resolver;

        return $this;
    }

    public function resolveConfig(): static
    {
        foreach ($this->configResolvers as $resolver) {
            $resolver->resolve($this->config);
        }

        return $this;
    }

    public function setEventConsumer(ESL\Event\AbstractConsumer $consumer): static
    {
        $this->eventConsumer = $consumer;

        $consumer->setApp($this);

        return $this;
    }

    public function setPlanConsumer(Plan\AbstractConsumer $consumer): static
    {
        $this->planConsumer = $consumer;

        $consumer->setApp($this);

        return $this;
    }

    public function setPlanProducer(Plan\AbstractProducer $producer): static
    {
        $this->planProducer = $producer;

        $producer->setApp($this);

        return $this;
    }

    public function setSignalProducer(Signal\AbstractProducer $producer): static
    {
        $this->signalProducer = $producer;

        $producer->setApp($this);

        return $this;
    }

    public function setEslClient(Switch\ESL\AbstractClient $client): static
    {
        $this->eslClient = $client;

        $client->setApp($this);

        return $this;
    }

    public function setEslServer(Switch\ESL\AbstractServer $server): static
    {
        $this->eslServer = $server;

        $server->setApp($this);

        return $this;
    }

    public function setCommandConsumer(Command\AbstractConsumer $consumer): static
    {
        $this->commandConsumer = $consumer;

        $consumer->setApp($this);

        return $this;
    }

    public function addCore(Core $core): void
    {
        $this->cores[$core->uuid] = $core;
        $core->app = $this;

        $this->buildCoreIterator();
    }

    public function getCore(string $uuid): ?Core
    {
        return isset($this->cores[$uuid]) ? $this->cores[$uuid] : null;
    }

    public function removeCore(string $uuid): void
    {
        if (isset($this->cores[$uuid])) {
            unset($this->cores[$uuid]);
        }

        $this->buildCoreIterator();
    }

    public function allocateCore(): Core
    {
        if (!isset($this->coreIterator)) {
            throw new Exception\CoreException('No cores available');
        }

        $this->coreIterator->next();

        if (!$this->coreIterator->valid()) {
            throw new Exception\CoreException('No cores available');
        }

        $core = $this->coreIterator->current();

        assert($core instanceof Core);

        return $core;
    }

    /**
     * Returns all active FreeSWITCH cores
     *
     * @return array<string, Core>
     */
    public function getAllCores(): array
    {
        return $this->cores;
    }

    protected function buildCoreIterator(): void
    {
        $this->coreIterator = new InfiniteIterator(new ArrayIterator($this->cores));
        $this->coreIterator->rewind();
    }

    public function addChannel(Channel $channel): void
    {
        $this->channels[$channel->uuid] = $channel;
    }

    public function getChannel(string $uuid): ?Channel
    {
        return isset($this->channels[$uuid]) ? $this->channels[$uuid] : null;
    }

    public function removeChannel(string $uuid): void
    {
        if (isset($this->channels[$uuid])) {
            unset($this->channels[$uuid]);
        }
    }

    public function addConference(Conference $conference): void
    {
        $this->conferences[$conference->uuid] = $conference;
        $this->conferencesByRoom[$conference->room] = $conference;
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

            unset($this->conferences[$uuid]);
        }
    }

    public function addOriginateJob(OriginateJob $originateJob): void
    {
        $this->originateJobs[$originateJob->uuid] = $originateJob;
    }

    public function getOriginateJob(string $uuid): ?OriginateJob
    {
        return isset($this->originateJobs[$uuid]) ? $this->originateJobs[$uuid] : null;
    }

    public function removeOriginateJob(string $uuid): void
    {
        if (isset($this->originateJobs[$uuid])) {
            unset($this->originateJobs[$uuid]);
        }
    }

    public function addScheduledHangup(ScheduledHangup $scheduledHangup): void
    {
        $this->scheduledHangups[$scheduledHangup->uuid] = $scheduledHangup;
    }

    public function getScheduledHangup(string $uuid): ?ScheduledHangup
    {
        return isset($this->scheduledHangups[$uuid]) ? $this->scheduledHangups[$uuid] : null;
    }

    public function removeScheduledHangup(string $uuid): void
    {
        if (isset($this->scheduledHangups[$uuid])) {
            unset($this->scheduledHangups[$uuid]);
        }
    }

    public function addScheduledPlay(ScheduledPlay $scheduledPlay): void
    {
        $this->scheduledPlays[$scheduledPlay->uuid] = $scheduledPlay;
    }

    public function getScheduledPlay(string $uuid): ?ScheduledPlay
    {
        return isset($this->scheduledPlays[$uuid]) ? $this->scheduledPlays[$uuid] : null;
    }

    public function removeScheduledPlay(string $uuid): void
    {
        if (isset($this->scheduledPlays[$uuid])) {
            unset($this->scheduledPlays[$uuid]);
        }
    }

    public function createLogger(string $name): Logger
    {
        $logger = new Logger($name);
        $logger->pushHandler((new PsrHandler($this->stdioLogger))->setFormatter(new LineFormatter()));

        return $logger;
    }

    public function enableStatsReporter(float $interval): void
    {
        $this->statsLogger = $this->createLogger('stats');

        Loop::addPeriodicTimer($interval, [$this, 'statsReporter']);
    }

    public function statsReporter(): void
    {
        $this->statsLogger->debug('Overall resident object instance count', [
            'OriginateJob' => OriginateJob::$instances,
            'Conference' => Conference::$instances,
            'Job' => Job::$instances,
            'ScheduledHangup' => ScheduledHangup::$instances,
            'ScheduledPlay' => ScheduledPlay::$instances,
            'Channel' => Channel::$instances,
        ]);

        $this->statsLogger->debug('App object instance count', [
            'OriginateJob' => count($this->originateJobs),
            'Conference' => count($this->conferences),
            'ScheduledHangup' => count($this->scheduledHangups),
            'ScheduledPlay' => count($this->scheduledPlays),
            'Channel' => count($this->channels),
        ]);

        foreach ($this->cores as $core) {
            $this->statsLogger->debug("Core {$core->uuid} object instance count", $core->gatherStats());
        }
    }

    public function prepare(): void
    {
        if ($this->config->daemonize) {
            $this->daemonize();
        }

        cli_set_process_title('ficore');

        if (isset($this->config->groupName)) {
            $this->setGroup($this->config->groupName);
        }

        if (isset($this->config->userName)) {
            $this->setUser($this->config->userName);
        }

        $this->writePidFile();
        $this->setupSignalHandlers();

        $this->stdioLogger = StdioLogger::create()->withHideLevel(true);
    }

    abstract public function run(): void;

    abstract public function shutdown(?int $signal = null): void;

    public function exit(int $code = 0): never
    {
        Loop::stop();
        exit($code);
    }

    protected function daemonize(): void
    {
        if (!function_exists('pcntl_fork')) {
            fwrite(STDERR, 'Cannot daemonize without pcntl extension' . PHP_EOL);
            $this->exit(1);
        }

        if (($pid = pcntl_fork()) < 0) {
            fwrite(STDERR, 'Cannot fork' . PHP_EOL);
            $this->exit(1);
        }

        if ($pid > 0) {
            echo 'ficore running in background' . PHP_EOL;
            $this->exit(0);
        }
    }

    protected function setUser(string $userName): void
    {
        if (!extension_loaded('posix')) {
            fwrite(STDERR, 'Cannot set user without posix extension' . PHP_EOL);
            $this->exit(1);
        }

        $user = posix_getpwnam($userName);

        if ($user === false) {
            fwrite(STDERR, 'Unknown user: ' . $userName . PHP_EOL);
            $this->exit(1);
        }

        if (!posix_setuid($user['uid'])) {
            fwrite(STDERR, 'Cannot set UID to ' . $user['uid'] . PHP_EOL);
            $this->exit(1);
        }
    }

    protected function setGroup(string $groupName): void
    {
        if (!extension_loaded('posix')) {
            fwrite(STDERR, 'Cannot set group without posix extension' . PHP_EOL);
            $this->exit(1);
        }

        $group = posix_getgrnam($groupName);

        if ($group === false) {
            fwrite(STDERR, 'Unknown group: ' . $groupName . PHP_EOL);
            $this->exit(1);
        }

        if (!posix_setgid($group['gid'])) {
            fwrite(STDERR, 'Cannot set GID to ' . $group['gid'] . PHP_EOL);
            $this->exit(1);
        }
    }

    protected function writePidFile(): void
    {
        $pid = getmypid();

        if ($pid === false) {
            fwrite(STDERR, 'Cannot determine my own PID' . PHP_EOL);
            return;
        }

        $dir = dirname($this->config->pidFile);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                fwrite(STDERR, 'Cannot create PID file parent directory' . PHP_EOL);
                return;
            }
        }

        $fp = fopen($this->config->pidFile, 'w');

        if ($fp === false) {
            fwrite(STDERR, 'Cannot open PID file for writing' . PHP_EOL);
            return;
        }

        fwrite($fp, (string)$pid);
        fclose($fp);
    }

    protected function setupSignalHandlers(): void
    {
        if (defined('SIGINT')) {
            Loop::addSignal(SIGINT, [$this, 'shutdown']);
            Loop::addSignal(SIGTERM, [$this, 'shutdown']);
        }
    }
}
