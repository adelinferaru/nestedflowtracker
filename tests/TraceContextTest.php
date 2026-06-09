<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Core\TraceContext;

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

    public function test_only_the_sampled_bit_of_the_flags_byte_is_read(): void
    {
        $header = '00-' . str_repeat('a', 32) . '-' . str_repeat('b', 16) . '-';

        // 0x02 is the level-2 random-trace-id flag — sampled bit clear.
        $this->assertFalse(TraceContext::parse($header . '02')?->sampled);
        $this->assertTrue(TraceContext::parse($header . '03')?->sampled);
        $this->assertFalse(TraceContext::parse($header . '00')?->sampled);
    }

    public function test_span_id_handles_non_numeric_ids_without_zeroing(): void
    {
        // 16-hex ids pass through (lowercased).
        $this->assertSame(str_repeat('ab', 8), TraceContext::spanId(strtoupper(str_repeat('ab', 8))));

        // Non-numeric keys (uuid/ulid) derive a stable id instead of (int)-casting
        // into the W3C-invalid all-zero id or a truncated leading-digits value.
        $uuid = '9b2f6f4e-1111-2222-3333-444455556666';
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', TraceContext::spanId($uuid));
        $this->assertNotSame(str_repeat('0', 16), TraceContext::spanId($uuid));
        $this->assertSame(TraceContext::spanId($uuid), TraceContext::spanId($uuid));

        $this->assertNotSame(str_repeat('0', 16), TraceContext::spanId(0));
    }
}
