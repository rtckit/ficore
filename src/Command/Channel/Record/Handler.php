<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Record;

use React\Promise\PromiseInterface;

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

        $response = new Response();
        $response->medium = $request->medium;
        $response->successful = true;

        $command = "uuid_record {$request->channel->uuid} {$request->action->value} {$request->medium}";

        if ($request->action === ActionEnum::Start) {
            $command .= " {$request->duration}";
        }

        return $request->channel->core->client->api((new ESL\Request\Api())->setParameters($command))
            ->then(function () use ($response): Response {
                return $response;
            });
    }
}
