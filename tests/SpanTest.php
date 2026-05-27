<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use RuntimeException;

class SpanTest extends TestCase
{
    public function test_span_returns_the_callback_value(): void
    {
        $value = Flow::span('compute', fn () => 21 * 2);

        $this->assertSame(42, $value);
    }

    public function test_span_persists_a_completed_span(): void
    {
        Flow::span('compute', fn () => null);

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame('compute', $span->name);
        $this->assertSame('test-component', $span->component);
        $this->assertSame(SpanStatus::Ok, $span->status);
        $this->assertNotNull($span->duration);
        $this->assertGreaterThanOrEqual(0.0, $span->duration);
    }

    public function test_span_passes_the_span_to_the_callback(): void
    {
        Flow::span('enrich', function (?FlowSpan $span) {
            $span->result = ['handled' => true];
        });

        $this->assertSame(['handled' => true], FlowSpan::query()->firstOrFail()->result);
    }

    public function test_failing_span_is_marked_failed_and_rethrows(): void
    {
        try {
            Flow::span('boom', function () {
                throw new RuntimeException('kaboom');
            });
            $this->fail('Exception was not rethrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('kaboom', $e->getMessage());
        }

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame(SpanStatus::Failed, $span->status);
        $this->assertNotNull($span->duration);
        $this->assertSame(RuntimeException::class, $span->result['exception'] ?? null);
        $this->assertSame('kaboom', $span->result['message'] ?? null);
    }

    public function test_nested_spans_build_a_tree_under_one_trace(): void
    {
        Flow::span('root', function () {
            Flow::span('child-a', fn () => null);
            Flow::span('child-b', fn () => null);
        });

        $root = FlowSpan::query()->where('name', 'root')->firstOrFail();
        $childA = FlowSpan::query()->where('name', 'child-a')->firstOrFail();
        $childB = FlowSpan::query()->where('name', 'child-b')->firstOrFail();

        $this->assertSame($root->id, $childA->parent_id);
        $this->assertSame($root->id, $childB->parent_id);
        $this->assertSame(2, $root->children()->count());

        // The whole flow shares a single trace id.
        $this->assertSame(1, FlowSpan::query()->distinct()->pluck('trace_id')->count());
    }

    public function test_disabled_tracker_is_a_transparent_passthrough(): void
    {
        config(['flow.enabled' => false]);

        $value = Flow::span('compute', fn () => 'still-runs');

        $this->assertSame('still-runs', $value);
        $this->assertSame(0, FlowSpan::query()->count());
        $this->assertNull(Flow::traceId());
    }
}
