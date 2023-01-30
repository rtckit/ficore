<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Conference\Playback;

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

        $response = new Response();
        $response->successful = true;

        $command = "conference {$request->conference->room} {$request->action->value} '{$request->medium}' ";

        if ($request->member === 'all') {
            $command .= 'async';
        } else {
            $command .= $request->member;
        }

        return $request->conference->core->client->api((new ESL\Request\Api())->setParameters($command))
            ->then(function () use ($response): Response {
                return $response;
            });
    }
}
