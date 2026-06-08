<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Minimal PSR-18 client used in tests: records every request, returns a 200.
 */
class RecordingHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $sent = [];

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sent[] = $request;

        return new Response(200);
    }
}
