<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;

/**
 * With flow.auto.http off (the default), requests are not instrumented.
 */
class HttpAutoDisabledTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function ($router) {
            $router->get('/ping', fn () => 'pong');
        });
    }

    public function test_no_span_is_recorded_when_auto_http_is_off(): void
    {
        $this->get('/ping')->assertOk();

        $this->assertSame(0, FlowSpan::query()->count());
    }
}
