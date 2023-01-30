<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Conference\Member;

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

class Handler extends AbstractHandler
{
    public function execute(RequestInterface $request): PromiseInterface
    {
        assert($request instanceof Request);

        $promises = [];
        $response = new Response();
        $response->successful = true;

        foreach ($request->members as $member) {
            $promises[] = $request->conference->core->client->api(
                (new ESL\Request\Api())->setParameters("conference {$request->conference->room} {$request->action->value} {$member}")
            )
                ->then(function (ESL\Response\ApiResponse $eslResponse) use ($request, $member, $response): PromiseInterface {
                    if (!$eslResponse->isSuccessful()) {
                        $response->successful = false;

                        $this->app->commandConsumer->logger->warning("`conference {$request->conference->room} {$request->action->value} {$member}` failed");
                    } else {
                        $response->members[] = $member;

                        $this->app->commandConsumer->logger->debug("`conference {$request->conference->room} {$request->action->value} {$member}` success");
                    }

                    return resolve();
                });
        }

        return all($promises)
            ->then(function () use ($response): Response {
                return $response;
            });
    }
}
