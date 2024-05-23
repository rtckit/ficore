<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Playback;

use Ramsey\Uuid\Uuid;
use function React\Promise\{
    all,
    resolve
};
use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL;
use RTCKit\FiCore\Command\{
    AbstractHandler,
    RequestInterface,
};
use RTCKit\FiCore\Switch\{
    CallLegEnum,
    Core,
    ScheduledPlay,
};

class Handler extends AbstractHandler
{
    public function execute(RequestInterface $request): PromiseInterface
    {
        assert($request instanceof Request);

        $response = new Response();
        $response->successful = true;

        switch ($request->action) {
            case ActionEnum::Schedule:
                $response->schedPlay = new ScheduledPlay();
                $response->schedPlay->uuid = Uuid::uuid4()->toString();
                $response->schedPlay->timeout = $request->delay;

                $request->channel->core->addScheduledPlay($response->schedPlay);

                // no break
            case ActionEnum::Play:
                $action = $this->getPlayCommands($request)
                    ->then(function (array $commands) use ($request, $response): PromiseInterface {
                        $promises = [];

                        foreach ($commands as $command) {
                            if ($request->action === ActionEnum::Schedule) {
                                $command = "sched_api +{$request->delay} {$response->schedPlay->uuid} {$command}";
                            }

                            $promises[] = $request->channel->core->client->api((new ESL\Request\Api())->setParameters($command))
                                ->then(function (ESL\Response\ApiResponse $eslResponse) use ($response, $command): PromiseInterface {
                                    if (!$eslResponse->isSuccessful()) {
                                        $response->successful = false;

                                        $this->app->commandConsumer->logger->error("Play Failed '{$command}': " . ($eslResponse->getBody() ?? '<null>'));
                                    }

                                    return resolve(null);
                                });
                        }

                        return all($promises);
                    });
                break;

            case ActionEnum::Stop:
                $action = $this->getDisplaceMediaList($request->channel->core, $request->channel->uuid)
                    ->then(function (array $stopList) use ($request, $response): PromiseInterface {
                        if (!isset($stopList[0])) {
                            $this->app->commandConsumer->logger->warning('PlayStop -- Nothing to stop');

                            return resolve([]);
                        }

                        $promises = [];

                        foreach ($stopList as $target) {
                            $command = "uuid_displace {$request->channel->uuid} stop {$target}";

                            $promises[] = $request->channel->core->client->bgApi((new ESL\Request\BgApi())->setParameters($command))
                                ->then(function (ESL\Response $eslResponse) use ($response, $command): PromiseInterface {
                                    $uuid = null;

                                    if ($eslResponse instanceof ESL\Response\CommandReply) {
                                        $uuid = $eslResponse->getHeader('job-uuid');
                                    }

                                    if (!isset($uuid)) {
                                        $response->successful = false;

                                        $this->app->commandConsumer->logger->error("PlayStop Failed '{$command}': " . ($eslResponse->getBody() ?? '<null>'));
                                    }

                                    return resolve(null);
                                });
                        }

                        return all($promises);
                    });
                break;

            case ActionEnum::Cancel:
                $action = $request->schedPlay->core->client->api((new ESL\Request\Api())->setParameters("sched_del {$request->schedPlay->uuid}"));
                break;
        }

        assert(isset($action));

        return $action
            ->then(function () use ($response): Response {
                return $response;
            });
    }

    /**
     * Merges `uuid_displace` commands for both legs
     *
     * @param Request $request
     *
     * @return PromiseInterface
     */
    private function getPlayCommands(Request $request): PromiseInterface
    {
        $aLegFlags = $bLegFlags = '';

        if ($request->loop) {
            $aLegFlags .= 'l';
            $bLegFlags .= 'l';
        }

        if ($request->mix) {
            $aLegFlags .= 'm';
            $bLegFlags .= 'mr';
        } else {
            $bLegFlags .= 'r';
        }

        $playStr = implode('!', $request->media);
        $playStringALeg = 'file_string://' . $playStr;
        $playStringBLeg = 'file_string://silence_stream://1!' . $playStr;


        $promises = [];

        if (($request->leg === CallLegEnum::ALEG) || ($request->leg === CallLegEnum::BOTH)) {
            $promises[] = $this->getPlayCommandsALeg($request, $playStringALeg, $aLegFlags);
        }

        if (($request->leg === CallLegEnum::BLEG) || ($request->leg === CallLegEnum::BOTH)) {
            $promises[] = $this->getPlayCommandsBLeg($request, $playStringBLeg, $bLegFlags);
        }

        return all($promises)
            ->then(function (array $results) {
                $ret = [];

                foreach ($results as $commands) {
                    assert(is_array($commands));
                    $ret = array_merge($ret, $commands);
                }

                return resolve($ret);
            });
    }

    /**
     * Prepares `uuid_displace` commands for A-Leg
     *
     * @param Request $request
     * @param string $playString
     * @param string $flags
     *
     * @return PromiseInterface
     */
    private function getPlayCommandsALeg(Request $request, string $playString, string $flags): PromiseInterface
    {
        return $this->getDisplaceMediaList($request->channel->core, $request->channel->uuid)
            ->then(function (array $stopList) use ($request, $playString, $flags): PromiseInterface {
                $ret = [];

                foreach ($stopList as $target) {
                    $ret[] = "uuid_displace {$request->channel->uuid} stop {$target}";
                }

                $ret[] = "uuid_displace {$request->channel->uuid} start '{$playString}' {$request->duration} {$flags}";

                return resolve($ret);
            });
    }

    /**
     * Prepares `uuid_displace` commands for B-Leg
     *
     * @param Request $request
     * @param string $playString
     * @param string $flags
     *
     * @return PromiseInterface
     */
    private function getPlayCommandsBLeg(Request $request, string $playString, string $flags): PromiseInterface
    {
        return $request->channel->core->client->api(
            (new ESL\Request\Api())->setParameters("uuid_getvar {$request->channel->uuid} bridge_uuid")
        )
            ->then(function (?ESL\Response\ApiResponse $response = null) use ($request, $playString, $flags): PromiseInterface {
                $uuid = null;

                if (isset($response)) {
                    $body = $response->getBody();

                    if (isset($body)) {
                        if (($body !== '_undef_') && (strpos($body, '-ERR') !== 0)) {
                            $uuid = $body;
                        }
                    }
                }

                if (is_null($uuid)) {
                    $this->app->commandConsumer->logger->warning('No BLeg found');

                    return resolve([]);
                }

                return $this->getDisplaceMediaList($request->channel->core, $uuid)
                    ->then(function (array $stopList) use ($request, $playString, $flags): PromiseInterface {
                        $ret = [];

                        foreach ($stopList as $target) {
                            $ret[] = "uuid_displace {$request->channel->uuid} stop {$target}";
                        }

                        $ret[] = "uuid_displace {$request->channel->uuid} start '{$playString}' {$request->duration} {$flags}";

                        return resolve($ret);
                    });
            });
    }

    private function getDisplaceMediaList(Core $core, string $uuid): PromiseInterface
    {
        return $core->client->api(
            (new ESL\Request\Api())->setParameters("uuid_buglist {$uuid}")
        )
            ->then(function (?ESL\Response\ApiResponse $response = null): PromiseInterface {
                $xml = null;

                if (isset($response)) {
                    $body = $response->getBody();

                    if (isset($body)) {
                        $xml = simplexml_load_string($body);
                    }
                }

                if (is_null($xml) || ($xml === false)) {
                    $this->app->commandConsumer->logger->warning('cannot get displace_media_list: no list');

                    return resolve([]);
                }

                $ret = [];

                foreach ($xml as $node) {
                    if ($node->getName() !== 'media-bug') {
                        continue;
                    }

                    if (!isset($node->function, $node->target)) {
                        continue;
                    }

                    if ((string)$node->function === 'displace') {
                        $ret[] = (string)$node->target;
                    }
                }

                return resolve($ret);
            });
    }
}
