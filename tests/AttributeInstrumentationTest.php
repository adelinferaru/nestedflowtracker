<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Core\Attributes\Trace;
use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\Fixtures\ClassTracedController;
use AdelinFeraru\NestedFlowTracker\Tests\Fixtures\TracedController;

class AttributeInstrumentationTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/traced', [TracedController::class, 'show'])->middleware('web');
        $router->get('/named', [TracedController::class, 'named'])->middleware('web');
        $router->get('/untraced', [TracedController::class, 'untraced'])->middleware('web');
        $router->get('/failing', [TracedController::class, 'failing'])->middleware('web');
        $router->get('/class-traced', [ClassTracedController::class, 'index'])->middleware('web');
        $router->get('/closure', #[Trace('closure span')] function () {
            return ['ok' => true];
        })->middleware('web');
    }

    public function test_attributed_action_records_a_span(): void
    {
        $this->get('/traced')->assertOk();

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame('TracedController@show', $span->name);
        $this->assertSame(SpanStatus::Ok, $span->status);
        $this->assertNull($span->parent_span_id);
        $this->assertNotNull($span->duration);
    }

    public function test_attribute_name_overrides_the_default(): void
    {
        $this->get('/named')->assertOk();

        $this->assertSame('named span', FlowSpan::query()->firstOrFail()->name);
    }

    public function test_action_without_attribute_records_nothing(): void
    {
        $this->get('/untraced')->assertOk();

        $this->assertSame(0, FlowSpan::query()->count());
    }

    public function test_5xx_response_marks_the_span_failed(): void
    {
        $this->get('/failing')->assertStatus(500);

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame(SpanStatus::Failed, $span->status);
    }

    public function test_class_level_attribute_traces_every_action(): void
    {
        $this->get('/class-traced')->assertOk();

        $this->assertSame('ClassTracedController@index', FlowSpan::query()->firstOrFail()->name);
    }

    public function test_closure_route_with_attribute_records_a_span(): void
    {
        $this->get('/closure')->assertOk();

        $this->assertSame('closure span', FlowSpan::query()->firstOrFail()->name);
    }
}
