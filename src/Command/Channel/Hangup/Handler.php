<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Hangup;

use Ramsey\Uuid\Uuid;
use React\Promise\PromiseInterface;

use function React\Promise\{
    all,
    resolve,
};
use RTCKit\ESL;
use RTCKit\FiCore\Command\{
    AbstractHandler,
    RequestInterface,
};

use RTCKit\FiCore\Switch\ScheduledHangup;

class Handler extends AbstractHandler
{
    public function execute(RequestInterface $request): PromiseInterface
    {
        assert($request instanceof Request);

        $response = new Response();
        $response->successful = true;

        switch ($request->action) {
            case ActionEnum::Schedule:
                $response->schedHangup = new ScheduledHangup();
                $response->schedHangup->uuid = Uuid::uuid4()->toString();
                $response->schedHangup->timeout = $request->delay;

                $request->channel->core->addScheduledHangup($response->schedHangup);

                // no break
            case ActionEnum::Channel:
                $command = "uuid_kill {$request->channel->uuid} {$request->cause->value}";

                if ($request->action === ActionEnum::Schedule) {
                    $command = "sched_api +{$request->delay} {$response->schedHangup->uuid} {$command}";
                }

                $action = $request->channel->core->client->api((new ESL\Request\Api())->setParameters($command));
                break;

            case ActionEnum::Job:
                $action = $request->originateJob->core->client->api(
                    (new ESL\Request\Api())->setParameters(
                        "hupall {$request->cause->value} {$this->app->config->appPrefix}_request_uuid {$request->originateJob->uuid}"
                    )
                );
                break;

            case ActionEnum::All:
                $cores = $this->app->getAllCores();
                $promises = [];

                foreach ($cores as $core) {
                    $promises[] = $core->client->bgApi((new ESL\Request\BgApi())->setParameters("hupall {$request->cause->value}"))
                        ->then(function (ESL\Response $eslResponse) use ($response): PromiseInterface {
                            $uuid = null;

                            if ($eslResponse instanceof ESL\Response\CommandReply) {
                                $uuid = $eslResponse->getHeader('job-uuid');
                            }

                            if (!isset($uuid)) {
                                $response->successful = false;
                            }

                            return resolve(null);
                        });
                }

                $action = all($promises);
                break;

            case ActionEnum::Cancel:
                $action = $request->schedHangup->core->client->api(
                    (new ESL\Request\Api())->setParameters("sched_del {$request->schedHangup->uuid}")
                );
                break;
        }

        assert(isset($action));

        return $action
            ->then(function () use ($response): Response {
                return $response;
            });
    }
}
