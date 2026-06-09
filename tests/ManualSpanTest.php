<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Core\Span;
use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;

class ManualSpanTest extends TestCase
{
    public function test_start_returns_a_running_span(): void
    {
        $span = Flow::start('task');

        $this->assertInstanceOf(Span::class, $span);
        $this->assertSame(SpanStatus::Running, $span->status);
        $this->assertNull($span->duration);
    }

    public function test_end_closes_the_open_span_and_records_duration(): void
    {
        $span = Flow::start('task');
        $closed = Flow::end();

        $this->assertSame($span->span_id, $closed?->span_id);
        $this->assertSame(SpanStatus::Ok, $closed->status);
        $this->assertNotNull($closed->duration);
    }

    public function test_end_on_an_empty_stack_is_a_noop(): void
    {
        $this->assertNull(Flow::end());
        $this->assertSame(0, FlowSpan::query()->count());
    }

    public function test_manual_nesting_uses_the_open_stack(): void
    {
        $root = Flow::start('root');
        $child = Flow::start('child');
        Flow::end();
        Flow::end();

        $childRow = FlowSpan::query()->where('span_id', $child->span_id)->firstOrFail();
        $this->assertSame($root->span_id, $childRow->parent_span_id);
    }

    public function test_end_options_override_fields(): void
    {
        Flow::start('task');
        Flow::end(['result' => ['ok' => 1], 'message' => 'done']);

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame(['ok' => 1], $span->result);
        $this->assertSame('done', $span->message);
    }

    public function test_current_span_reflects_the_open_span(): void
    {
        $this->assertNull(Flow::currentSpan());

        $span = Flow::start('task');
        $this->assertSame($span->span_id, Flow::currentSpan()?->span_id);

        Flow::end();
        $this->assertNull(Flow::currentSpan());
    }

    public function test_end_accepts_a_string_status_for_2x_compatibility(): void
    {
        Flow::start('task');
        Flow::end(['status' => 'failed']);

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame(SpanStatus::Failed, $span->status);
    }

    public function test_end_accepts_a_span_status_enum(): void
    {
        Flow::start('task');
        Flow::end(['status' => SpanStatus::Failed]);

        $span = FlowSpan::query()->firstOrFail();
        $this->assertSame(SpanStatus::Failed, $span->status);
    }

    public function test_invalid_status_override_throws_and_leaves_the_span_open(): void
    {
        Flow::start('task');

        try {
            Flow::end(['status' => 'bogus']);
            $this->fail('Expected a ValueError for the unknown status.');
        } catch (\ValueError) {
            // expected
        }

        // The span was not half-closed: it is still open and can be ended normally.
        $this->assertNotNull(Flow::currentSpan());
        $closed = Flow::end();
        $this->assertSame(SpanStatus::Ok, $closed?->status);
    }
}
