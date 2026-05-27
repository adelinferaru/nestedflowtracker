<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use Illuminate\Support\Facades\Gate;

class ViewerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.viewer.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewFlow', fn ($user = null) => true);
    }

    public function test_index_lists_recorded_flows(): void
    {
        Flow::span('checkout', fn () => null);

        $this->get('/flow')
            ->assertOk()
            ->assertSee('checkout');
    }

    public function test_index_can_filter_by_status(): void
    {
        Flow::span('good', fn () => null);

        $this->get('/flow?status=failed')
            ->assertOk()
            ->assertDontSee('good');
    }

    public function test_show_renders_the_span_tree(): void
    {
        Flow::span('checkout', function () {
            Flow::span('charge card', fn () => null);
        });
        $trace = Flow::traceId();

        $this->get('/flow/' . $trace)
            ->assertOk()
            ->assertSee('checkout')
            ->assertSee('charge card');
    }

    public function test_unknown_trace_returns_404(): void
    {
        $this->get('/flow/does-not-exist')->assertNotFound();
    }
}
