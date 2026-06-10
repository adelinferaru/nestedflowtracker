<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\Fixtures\TracedController;
use AdelinFeraru\NestedFlowTracker\Tests\Fixtures\TracedJob;

class AttributeDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.attributes', false);
        $app['config']->set('queue.default', 'sync');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/traced', [TracedController::class, 'show'])->middleware('web');
    }

    public function test_kill_switch_disables_attribute_tracing_entirely(): void
    {
        $this->get('/traced')->assertOk();
        TracedJob::dispatch();

        $this->assertSame(0, FlowSpan::query()->count());
    }
}
