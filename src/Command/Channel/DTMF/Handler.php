<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\DTMF;

use React\Promise\PromiseInterface;
use function React\Promise\{
    all,
    resolve
};

use RTCKit\ESL;
use RTCKit\FiCore\Command\{
    AbstractHandler,
    RequestInterface,
};

use RTCKit\FiCore\Switch\CallLegEnum;

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

        $command = (($request->leg === CallLegEnum::ALEG) ? 'uuid_send_dtmf' : 'uuid_recv_dtmf') .
            " {$request->channel->uuid} {$request->tones}";

        return $request->channel->core->client->bgApi((new ESL\Request\BgApi())->setParameters($command))
            ->then(function (ESL\Response $eslResponse) use ($response): Response {
                $uuid = null;

                if ($eslResponse instanceof ESL\Response\CommandReply) {
                    $uuid = $eslResponse->getHeader('job-uuid');
                }

                if (!isset($uuid)) {
                    $response->successful = false;
                }

                return $response;
            });
    }
}
