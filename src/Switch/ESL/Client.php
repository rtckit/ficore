<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use React\EventLoop\Loop;

use React\Promise\PromiseInterface;
use RTCKit\ESL;
use RTCKit\FiCore\Exception\CoreException;
use RTCKit\FiCore\Switch\Core;
use RTCKit\FiCore\Switch\ESL\Event\{
    ConsumerInterface,
    HandlerInterface,
};
use RTCKit\FiCore\{
    AbstractApp,
    Config,
};
use RTCKit\React\ESL\InboundClient;
use stdClass as Event;
use Throwable;

class Client extends AbstractClient
{
    public const RECONNECT_DEBOUNCE_FACTOR = 10;

    public const RECONNECT_DEBOUNCE_LIMIT = 30;

    public function setApp(AbstractApp $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function setEventHandler(HandlerInterface $handler): Client
    {
        $this->handlers[$handler::EVENT->value] = $handler;

        $this->handlers[$handler::EVENT->value]->setApp($this->app);

        return $this;
    }

    public function run(): void
    {
        $this->logger = new Logger('esl.client');
        $this->logger->pushHandler(
            (new PsrHandler($this->app->stdioLogger, $this->app->config->eslClientLogLevel))->setFormatter(new LineFormatter())
        );
        $this->logger->debug('Starting ...');

        foreach ($this->app->config->cores as $coreConfig) {
            $this->logger->info("Trying to connect to FreeSWITCH @ {$coreConfig->eslHost}:{$coreConfig->eslPort}");
            $this->connect($coreConfig);
        }
    }

    protected function connect(Config\Core $config): void
    {
        $eslClient = isset($config->eslUser)
            ? new InboundClient($config->eslHost, $config->eslPort, $config->eslUser, $config->eslPassword)
            : new InboundClient($config->eslHost, $config->eslPort, $config->eslPassword);

        $eslClient
            ->connect()
            ->then(function (InboundClient $client): PromiseInterface {
                return $client->api((new ESL\Request\Api())->setParameters('global_getvar'));
            })
            ->then(function (ESL\Response\ApiResponse $response) use ($eslClient, $config): PromiseInterface {
                $lines = explode("\n", $response->getBody() ?? '');
                $vars = [];

                foreach ($lines as $line) {
                    if (!isset($line[0])) {
                        continue;
                    }

                    $parts = explode('=', $line, 2);

                    if (count($parts) !== 2) {
                        continue;
                    }

                    $vars[$parts[0]] = $parts[1];
                }

                if (!isset($vars['core_uuid'])) {
                    throw new CoreException('Cannot read FreeSWITCH Core-UUID');
                }

                $config->uuid = $vars['core_uuid'];
                $config->retries = 0;
                $config->connected = true;

                $core = $this->app->getCore($vars['core_uuid']);

                if (!isset($core)) {
                    $core = new Core();
                    $core->uuid = $vars['core_uuid'];
                }

                $core->vars = $vars;

                $core->setClient($eslClient);
                $this->app->addCore($core);

                /* Advertise the Outbound server as a global variable for highly dynamic FreeSWITCH configurations.
                 * For example, you can use the following entry in your dialplan(s):
                 *
                 *     <!-- ficore_outbound_socket_address holds the advertised Outbound Server host:port -->
                 *     <action application="socket" data="${ficore_outbound_socket_address} async full" />
                 *
                 *     <!-- ficore_local_outbound_socket_address holds the host:port used by this running FiCore instance -->
                 *     <action application="socket" data="${ficore_local_outbound_socket_address} async full" />
                 *
                 * Since FreeSWITCH will not resolve any FQDNs, this is useful in environments where FiCore's IP
                 * address is not predictable (e.g. orchestrated containers).
                 */
                $localSocketAddr = $core->client->getAddress();

                if (!is_null($localSocketAddr)) {
                    $localIp = parse_url($localSocketAddr, PHP_URL_HOST);

                    if (is_string($localIp)) {
                        $core->client->api((new ESL\Request\Api())->setParameters(
                            "global_setvar ficore_local_outbound_socket_address={$localIp}:{$this->app->config->eslServerBindPort}"
                        ));

                        if ($this->app->config->eslServerAdvertisedIp === $this->app->config::INBOUND_SOCKET_ADDRESS) {
                            $this->app->config->eslServerAdvertisedIp = $localIp;
                        }
                    }
                }

                $core->client->api((new ESL\Request\Api())->setParameters(
                    "global_setvar ficore_outbound_socket_address={$this->app->config->eslServerAdvertisedIp}:{$this->app->config->eslServerAdvertisedPort}"
                ));

                $core->client->on('event', function (ESL\Response\TextEventJson $response) use ($core) {
                    $event = json_decode($response->getBody() ?? '');
                    assert($event instanceof Event);

                    try {
                        $this->app->eventConsumer->onEvent($core, $event);
                    } catch (\Throwable $t) {
                        $t = $t->getPrevious() ?: $t;

                        $this->logger->error('Processing inbound event failure: ' . $t->getMessage(), [
                            'file' => $t->getFile(),
                            'line' => $t->getLine(),
                        ]);
                    }
                });

                $core->client->on('disconnect', function (?ESL\Response\TextDisconnectNotice $response = null) use ($core, $config) {
                    /* PHPStan says `Negated boolean expression is always false` ... that's not correct */
                    if (!$config->connected) { /** @phpstan-ignore-line */
                        return;
                    }

                    $this->app->removeCore($core->uuid);
                    $config->connected = false;
                    $reason = isset($response) ? trim($response->getBody() ?? 'Unspecified') : 'Unexpected';

                    $this->logger->error("Disconnected FreeSWITCH {$config->uuid} {$config->eslHost}:{$config->eslPort}: {$reason}");
                    $this->reconnect($config);
                });

                return $this->app->eventConsumer->subscribe($core);
            })
            ->then(function () use ($config) {
                $this->logger->info("Connected to FreeSWITCH @ {$config->eslHost}:{$config->eslPort}");
            })
            ->catch(function (Throwable $t) use ($config) {
                $t = $t->getPrevious() ?: $t;
                $this->logger->error("Cannot connect to FreeSWITCH @ {$config->eslHost}:{$config->eslPort}: " . $t->getMessage());
                $this->reconnect($config);
            });
    }

    protected function reconnect(Config\Core $config): void
    {
        if (!$config->retries) {
            $config->retries++;
            $this->connect($config);
        } else {
            $debounce = min($config->retries++ * self::RECONNECT_DEBOUNCE_FACTOR, self::RECONNECT_DEBOUNCE_LIMIT);
            $this->logger->error("Reconnecting in {$debounce} seconds");

            Loop::addTimer($debounce, function () use ($config) {
                $this->connect($config);
            });
        }
    }

    public function shutdown(): void
    {
    }
}
