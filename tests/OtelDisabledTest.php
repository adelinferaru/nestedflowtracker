<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use Illuminate\Support\Facades\Http;

class OtelDisabledTest extends TestCase
{
    public function test_nothing_is_exported_when_otel_is_off(): void
    {
        // flow.otel.enabled is off by default.
        Http::fake();

        Flow::span('checkout', fn () => null);

        Http::assertNothingSent();
    }
}
