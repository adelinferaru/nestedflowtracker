<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

class TraceIdTest extends TestCase
{
    public function test_first_span_generates_a_32_char_hex_trace_id(): void
    {
        Flow::span('root', fn () => null);

        $traceId = Flow::traceId();
        $this->assertNotNull($traceId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $traceId);
        $this->assertSame($traceId, FlowSpan::query()->firstOrFail()->trace_id);
    }

    public function test_an_explicit_inbound_trace_id_is_continued(): void
    {
        Flow::setTraceId('cafebabecafebabecafebabecafebabe');

        $span = Flow::start('root');

        $this->assertSame('cafebabecafebabecafebabecafebabe', $span->trace_id);
        $this->assertSame('cafebabecafebabecafebabecafebabe', Flow::traceId());
    }

    public function test_trace_id_can_be_supplied_via_options(): void
    {
        $span = Flow::start('root', ['trace_id' => 'feedfeedfeedfeedfeedfeedfeedfeed']);

        $this->assertSame('feedfeedfeedfeedfeedfeedfeedfeed', $span->trace_id);
    }

    public function test_children_inherit_the_parent_trace_id(): void
    {
        Flow::span('root', function () {
            Flow::span('child', fn () => null);
        });

        $traceIds = FlowSpan::query()->pluck('trace_id')->unique();
        $this->assertCount(1, $traceIds);
    }
}
