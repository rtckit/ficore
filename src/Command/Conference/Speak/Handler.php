<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Conference\Speak;

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

        if (!isset($request->action)) {
            $response->successful = false;

            return resolve($response);
        }

        $response->successful = true;

        $command = "conference {$request->conference->room} say";

        if ($request->member === 'all') {
            $command .= " '{$request->text}'";
        } else {
            $command .= "member {$request->member} '{$request->text}'";
        }

        return $request->conference->core->client->api((new ESL\Request\Api())->setParameters($command))
            ->then(function () use ($response): Response {
                return $response;
            });
    }
}
