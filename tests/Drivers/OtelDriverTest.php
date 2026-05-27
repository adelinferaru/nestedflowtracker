<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Drivers;

use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class OtelDriverTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.driver', 'otel');
        $app['config']->set('flow.otel.endpoint', 'http://collector:4318');
    }

    public function test_flow_is_exported_directly_without_touching_the_database(): void
    {
        Http::fake();

        Flow::span('checkout', function () {
            Flow::span('charge card', fn () => null);
        });
        $trace = Flow::traceId();

        // Nothing is stored in the database with the otel driver.
        $this->assertSame(0, FlowSpan::query()->count());

        Http::assertSent(function (Request $request) use ($trace) {
            if ($request->url() !== 'http://collector:4318/v1/traces') {
                return false;
            }

            $spans = $request->data()['resourceSpans'][0]['scopeSpans'][0]['spans'] ?? [];
            $names = collect($spans)->pluck('name')->all();
            $child = collect($spans)->firstWhere('name', 'charge card');

            return count($spans) === 2
                && in_array('checkout', $names, true)
                && collect($spans)->every(fn ($s) => $s['traceId'] === $trace)
                // the child carries the root's span id as its parent
                && isset($child['parentSpanId']);
        });
    }
}
