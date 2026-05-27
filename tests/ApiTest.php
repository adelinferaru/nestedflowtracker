<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use Illuminate\Support\Facades\Gate;

class ApiTest extends TestCase
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

    public function test_lists_flows_as_json(): void
    {
        Flow::span('checkout', fn () => null);

        $this->getJson('/flow/api/flows')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'checkout')
            ->assertJsonPath('data.0.status', 'ok')
            ->assertJsonStructure(['data' => [['trace_id', 'name', 'component', 'status', 'duration']], 'meta' => ['total']]);
    }

    public function test_returns_a_flow_as_a_nested_tree(): void
    {
        Flow::span('checkout', function () {
            Flow::span('charge card', fn () => null);
        });
        $trace = Flow::traceId();

        $this->getJson('/flow/api/flows/' . $trace)
            ->assertOk()
            ->assertJsonPath('trace_id', $trace)
            ->assertJsonPath('spans.0.name', 'checkout')
            ->assertJsonPath('spans.0.children.0.name', 'charge card');
    }

    public function test_unknown_trace_returns_404(): void
    {
        $this->getJson('/flow/api/flows/nope')->assertNotFound();
    }
}
