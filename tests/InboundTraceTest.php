<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;

class InboundTraceTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.auto.http', true);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function ($router) {
            $router->get('/ping', fn () => 'pong');
        });
    }

    public function test_request_continues_an_inbound_trace(): void
    {
        $traceId = str_repeat('a', 32);

        $this->withHeaders([
            'traceparent' => '00-' . $traceId . '-' . str_repeat('b', 16) . '-01',
        ])->get('/ping')->assertOk();

        $this->assertSame($traceId, FlowSpan::query()->firstOrFail()->trace_id);
    }

    public function test_request_without_a_header_starts_a_fresh_trace(): void
    {
        $this->get('/ping')->assertOk();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', FlowSpan::query()->firstOrFail()->trace_id);
    }
}
