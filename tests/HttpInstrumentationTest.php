<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

class HttpInstrumentationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.auto.http', true);
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function ($router) {
            $router->get('/ping', function () {
                // A manual span inside the request should nest under the request root.
                Flow::span('inner work', fn () => null);

                return 'pong';
            });

            $router->get('/err', fn () => response('boom', 500));
        });
    }

    public function test_successful_request_records_a_root_span(): void
    {
        $this->get('/ping')->assertOk();

        $root = FlowSpan::query()->whereNull('parent_id')->firstOrFail();
        $this->assertSame('GET ping', $root->name);
        $this->assertSame(SpanStatus::Ok, $root->status);
        $this->assertSame('GET', $root->context['method']);
        $this->assertSame(200, $root->context['status']);
    }

    public function test_inner_span_nests_under_the_request_root(): void
    {
        $this->get('/ping')->assertOk();

        $root = FlowSpan::query()->where('name', 'GET ping')->firstOrFail();
        $inner = FlowSpan::query()->where('name', 'inner work')->firstOrFail();

        $this->assertSame($root->id, $inner->parent_id);
        $this->assertSame($root->trace_id, $inner->trace_id);
    }

    public function test_server_error_marks_the_span_failed(): void
    {
        $this->get('/err')->assertStatus(500);

        $root = FlowSpan::query()->whereNull('parent_id')->firstOrFail();
        $this->assertSame(SpanStatus::Failed, $root->status);
        $this->assertSame(500, $root->context['status']);
    }
}
