<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Laravel\Facades\Flow;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class OutboundTraceTest extends TestCase
{
    public function test_with_flow_trace_injects_the_traceparent_header(): void
    {
        Http::fake();

        $traceId = null;
        Flow::span('call downstream', function () use (&$traceId) {
            $traceId = Flow::traceId();
            Http::withFlowTrace()->get('https://example.test/api');
        });

        Http::assertSent(function (Request $request) use ($traceId) {
            $header = $request->header('traceparent')[0] ?? '';

            return str_starts_with($header, '00-' . $traceId . '-');
        });
    }

    public function test_no_header_is_added_without_an_active_trace(): void
    {
        Http::fake();

        Http::withFlowTrace()->get('https://example.test/api');

        Http::assertSent(fn (Request $request) => ! $request->hasHeader('traceparent'));
    }
}
