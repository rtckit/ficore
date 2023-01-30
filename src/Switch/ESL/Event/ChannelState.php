<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch\ESL\Event;

use RTCKit\ESL;

use RTCKit\FiCore\Switch\{
    ChannelStateEnum,
    Core,
    EventEnum
};
use stdClass as Event;

class ChannelState implements HandlerInterface
{
    use HandlerTrait;

    /** @var EventEnum */
    public const EVENT = EventEnum::CHANNEL_STATE;

    public function execute(Core $core, Event $event): void
    {
        switch ($event->{'Channel-State'}) {
            case ChannelStateEnum::CS_RESET->value:
                $channel = $core->getChannel($event->{'Unique-ID'});

                if (!isset($channel, $channel->transferInProgress)) {
                    return;
                }

                unset($channel->transferInProgress);

                $this->app->eslClient->logger->info('TransferCall In Progress for ' . $channel->uuid);

                $core->client->api(
                    (new ESL\Request\Api())->setParameters("uuid_setvar {$channel->uuid} {$this->app->config->appPrefix}_transfer_progress false")
                )
                    ->then(function () use ($core, $channel) {
                        return $core->client->api(
                            (new ESL\Request\Api())
                                ->setParameters("uuid_transfer {$channel->uuid} 'socket:{$this->app->config->eslServerAdvertisedIp}:{$this->app->config->eslServerAdvertisedPort} async full' inline")
                        );
                    })
                    ->then(function (?ESL\Response\ApiResponse $response = null) use ($channel) {
                        if (!isset($response) || !$response->isSuccessful()) {
                            $body = isset($response) ? ($response->getBody() ?? '') : '';

                            $this->app->eslClient->logger->info('TransferCall Failed for ' . $channel->uuid . ' ' . $body);
                        } else {
                            $this->app->eslClient->logger->info('TransferCall Done for ' . $channel->uuid);
                        }
                    });
                break;

            case ChannelStateEnum::CS_HANGUP->value:
                $channel = $core->getChannel($event->{'Unique-ID'});

                if (!isset($channel, $channel->transferInProgress)) {
                    return;
                }

                unset($channel->transferInProgress);

                $this->app->eslClient->logger->warning('TransferCall Aborted (hangup) for ' . $channel->uuid);

                break;
        }
    }
}
