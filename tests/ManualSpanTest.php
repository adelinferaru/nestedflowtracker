<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

class ManualSpanTest extends TestCase
{
    public function test_start_returns_a_running_span(): void
    {
        $span = Flow::start('task');

        $this->assertInstanceOf(FlowSpan::class, $span);
        $this->assertSame(SpanStatus::Running, $span->status);
        $this->assertNull($span->duration);
    }

    public function test_end_closes_the_open_span_and_records_duration(): void
    {
        $span = Flow::start('task');
        $closed = Flow::end();

        $this->assertSame($span->id, $closed?->id);
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

        $this->assertSame($root->id, FlowSpan::find($child->id)->parent_id);
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
        $this->assertSame($span->id, Flow::currentSpan()?->id);

        Flow::end();
        $this->assertNull(Flow::currentSpan());
    }
}
