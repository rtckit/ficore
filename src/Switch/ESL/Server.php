<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\PsrHandler;

use Monolog\Logger;
use RTCKit\FiCore\AbstractApp;
use RTCKit\FiCore\Plan\{
    AbstractElement,
    ConsumerInterface,
    HandlerInterface,
};
use RTCKit\React\ESL\OutboundServer;

class Server extends AbstractServer
{
    protected AbstractApp $app;

    protected OutboundServer $server;

    public function setApp(AbstractApp $app): static
    {
        $this->app = $app;

        return $this;
    }

    public function run(): void
    {
        $this->logger = new Logger('esl.server');
        $this->logger->pushHandler(
            (new PsrHandler($this->app->stdioLogger, $this->app->config->eslServerLogLevel))->setFormatter(new LineFormatter())
        );
        $this->logger->debug('Starting ...');

        if (!isset($this->app->config->eslServerAdvertisedPort)) {
            $this->app->config->eslServerAdvertisedPort = $this->app->config->eslServerBindPort;

            $this->logger->notice("eslServerAdvertisedPort configuration parameter set to {$this->app->config->eslServerAdvertisedPort}");
        }

        if (!isset($this->app->config->defaultAnswerUrl)) {
            $this->logger->alert("defaultAnswerUrl configuration parameter is not set, inbound calls will fail!");
        }

        $this->server = new OutboundServer($this->app->config->eslServerBindIp, $this->app->config->eslServerBindPort);
        $this->server->on('connect', [$this->app->planConsumer, 'onConnect']);

        $this->server->on('error', function (\Throwable $t) {
            $t = $t->getPrevious() ?: $t;
            $this->logger->error('ESL Server exception: ' . $t->getMessage());
        });

        $this->server->listen();

        $address = $this->server->getAddress();

        assert(!is_null($address));
        $this->logger->debug('Listening @ ' . $address);
    }

    public function shutdown(): void
    {
        $this->server->close();
    }
}
