<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Command\Channel\Redirect;

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

        return all([
            'transfer_progress' => $request->channel->core->client->api(
                (new ESL\Request\Api())->setParameters("uuid_setvar {$request->channel->uuid} {$this->app->config->appPrefix}_transfer_progress true")
            ),
            'transfer_url' => $request->channel->core->client->api(
                (new ESL\Request\Api())->setParameters("uuid_setvar {$request->channel->uuid} {$this->app->config->appPrefix}_transfer_seq " . $request->sequence)
            ),
            $this->app->config->appPrefix . '_destination_number' => $request->channel->core->client->api(
                (new ESL\Request\Api())->setParameters("uuid_getvar {$request->channel->uuid} {$this->app->config->appPrefix}_destination_number")
            ),
            'destination_number' => $request->channel->core->client->api(
                (new ESL\Request\Api())->setParameters("uuid_getvar {$request->channel->uuid} destination_number")
            ),
        ])
            ->then(function (array $args) use ($request): PromiseInterface {
                $prefixedDestNumber = $args[$this->app->config->appPrefix . '_destination_number'];

                assert($prefixedDestNumber instanceof ESL\Response\ApiResponse);

                $destNumber = $prefixedDestNumber->getBody();

                if (is_string($destNumber) && (($destNumber === '_undef_') || (strpos($destNumber, '-ERR') === 0))) {
                    assert($args['destination_number'] instanceof ESL\Response\ApiResponse);

                    $destNumber = $args['destination_number']->getBody();

                    assert(is_string($destNumber));

                    return $request->channel->core->client->api(
                        (new ESL\Request\Api())->setParameters(
                            "uuid_setvar {$request->channel->uuid} {$this->app->config->appPrefix}_destination_number {$destNumber}"
                        )
                    );
                }

                return resolve(null);
            })
            ->then(function () use ($request): PromiseInterface {
                $request->channel->transferInProgress = true;

                return $request->channel->core->client->api(
                    (new ESL\Request\Api())->setParameters("uuid_transfer {$request->channel->uuid} 'sleep:5000' inline")
                );
            })
            ->then(function () use ($response): Response {
                return $response;
            });
    }
}
