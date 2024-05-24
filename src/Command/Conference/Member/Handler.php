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
            $confCmd = "{$request->action->value} {$member}";

            switch ($request->action) {
                case ActionEnum::ExtHold:
                    $promises[] = $this->executeExtHold($request, $member);
                    break;

                case ActionEnum::ExtUnhold:
                    $promises[] = $this->executeExtUnhold($request, $member);
                    break;

                case ActionEnum::Play:
                case ActionEnum::Stop:
                    $confCmd = "{$request->action->value}";

                    if (isset($request->medium)) {
                        $confCmd .= " {$request->medium}";
                    }

                    if ($member !== 'all') {
                        $confCmd .= " {$member}";
                    }

                default:
                    $apiCmd = "conference {$request->conference->room} {$confCmd}";
                    $promises[] = $request->conference->core->client->api((new ESL\Request\Api())->setParameters($apiCmd))
                        ->then(function (ESL\Response\ApiResponse $eslResponse) use ($member, $response, $apiCmd): PromiseInterface {
                            if (!$eslResponse->isSuccessful()) {
                                $response->successful = false;

                                $this->app->commandConsumer->logger->warning("`{$apiCmd}` failed");
                            } else {
                                $response->members[] = $member;

                                $this->app->commandConsumer->logger->debug("`{$apiCmd}` success");
                            }

                            return resolve(null);
                        });
                    break;
            }
        }

        return all($promises)
            ->then(function () use ($response): Response {
                return $response;
            });
    }

    private function executeExtHold(Request $request, string $member): PromiseInterface
    {
        $deafRequest = clone $request;
        $deafRequest->action = ActionEnum::Deaf;
        $deafRequest->members = [$member];

        $muteRequest = clone $request;
        $muteRequest->action = ActionEnum::Mute;
        $muteRequest->members = [$member];

        $promises = [
            'deaf' => $this->execute($deafRequest),
            'mute' => $this->execute($muteRequest),
        ];

        if (isset($request->medium)) {
            $playRequest = clone $request;
            $playRequest->action = ActionEnum::Play;
            $playRequest->members = [$member];

            $promises['play'] = $this->execute($playRequest);
        }

        return all($promises);
    }

    private function executeExtUnhold(Request $request, string $member): PromiseInterface
    {
        $unmuteRequest = clone $request;
        $unmuteRequest->action = ActionEnum::Unmute;
        $unmuteRequest->members = [$member];

        $undeafRequest = clone $request;
        $undeafRequest->action = ActionEnum::Undeaf;
        $undeafRequest->members = [$member];

        $stopRequest = clone $request;
        $stopRequest->action = ActionEnum::Stop;
        $stopRequest->members = [$member];
        $stopRequest->medium = 'current';

        $promises = [
            'unmute' => $this->execute($unmuteRequest),
            'undeaf' => $this->execute($undeafRequest),
            'stop' => $this->execute($stopRequest),
        ];

        return all($promises);
    }
}
