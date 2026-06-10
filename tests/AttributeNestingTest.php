<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\Fixtures\TracedController;

class AttributeNestingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.auto.http', true);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/traced', [TracedController::class, 'show'])->middleware('web');
    }

    public function test_attributed_action_nests_under_the_request_root_span(): void
    {
        $this->get('/traced')->assertOk();

        $root = FlowSpan::query()->whereNull('parent_span_id')->firstOrFail();
        $action = FlowSpan::query()->where('name', 'TracedController@show')->firstOrFail();

        $this->assertSame('GET traced', $root->name);
        $this->assertSame($root->span_id, $action->parent_span_id);
        $this->assertSame($root->trace_id, $action->trace_id);
    }
}
