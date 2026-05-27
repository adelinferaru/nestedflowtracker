<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\TraceContext;

class TraceContextTest extends TestCase
{
    public function test_parses_a_valid_traceparent(): void
    {
        $context = TraceContext::parse('00-' . str_repeat('a', 32) . '-' . str_repeat('b', 16) . '-01');

        $this->assertNotNull($context);
        $this->assertSame(str_repeat('a', 32), $context->traceId);
        $this->assertSame(str_repeat('b', 16), $context->parentId);
        $this->assertTrue($context->sampled);
    }

    public function test_rejects_malformed_or_all_zero_values(): void
    {
        $this->assertNull(TraceContext::parse(null));
        $this->assertNull(TraceContext::parse('garbage'));
        $this->assertNull(TraceContext::parse('00-short-' . str_repeat('b', 16) . '-01'));
        $this->assertNull(TraceContext::parse('00-' . str_repeat('0', 32) . '-' . str_repeat('b', 16) . '-01'));
    }

    public function test_builds_a_header(): void
    {
        $header = (new TraceContext(str_repeat('a', 32), str_repeat('b', 16)))->toHeader();

        $this->assertSame('00-' . str_repeat('a', 32) . '-' . str_repeat('b', 16) . '-01', $header);
    }

    public function test_span_id_is_16_hex_derived_from_an_id(): void
    {
        $this->assertSame('0000000000000010', TraceContext::spanId(16));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', TraceContext::spanId(null));
    }
}
