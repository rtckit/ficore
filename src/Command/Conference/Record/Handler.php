<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Conference\Record;

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

        return $request->conference->core->client->api(
            (new ESL\Request\Api())->setParameters("conference {$request->conference->room} {$request->action->value} {$request->medium}")
        )
            ->then(function (ESL\Response\ApiResponse $eslResponse) use ($request, $response): Response {
                if ($request->action === ActionEnum::Stop) {
                    /* Cannot use Response::isSuccessful() here as the response is non-standard, e.g.
                     * "Stopped recording file /tmp/test.wav\n+OK Stopped recording 0 files\n"
                     */
                    $body = $eslResponse->getBody();
                    $response->successful = isset($body) && (strpos($body, '+OK Stopped recording') !== false);
                } else {
                    $response->successful = (bool)$eslResponse->isSuccessful();
                }

                return $response;
            });
    }
}
