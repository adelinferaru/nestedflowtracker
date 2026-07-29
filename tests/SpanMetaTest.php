<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Laravel\Support\SpanMeta;
use PHPUnit\Framework\TestCase as BaseTestCase;

class SpanMetaTest extends BaseTestCase
{
    public function test_formats_scalars_null_and_structures(): void
    {
        $pairs = SpanMeta::pairs([
            'boot_ms' => 129.3,
            'q_type' => 'MULTIPLE_CHOICE',
            'cached' => false,
            'batch' => null,
            'ids' => [1, 2],
        ]);

        $this->assertSame(
            ['boot_ms=129.3', 'q_type=MULTIPLE_CHOICE', 'cached=false', 'batch=null', 'ids=[1,2]'],
            $pairs,
        );
    }

    public function test_null_and_empty_context_yield_no_pairs(): void
    {
        $this->assertSame([], SpanMeta::pairs(null));
        $this->assertSame([], SpanMeta::pairs([]));
    }

    public function test_long_values_are_truncated(): void
    {
        $pairs = SpanMeta::pairs(['sql' => str_repeat('x', 500)]);

        $this->assertSame('sql=' . str_repeat('x', 119) . '…', $pairs[0]);
    }
}
