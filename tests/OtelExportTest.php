<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OtelExportTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('flow.otel.enabled', true);
        $app['config']->set('flow.otel.endpoint', 'http://collector:4318');
    }

    public function test_span_records_a_span_id_and_start_time(): void
    {
        $span = Flow::start('task');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) $span->span_id);
        $this->assertNotNull($span->started_at);
    }

    public function test_completed_flow_is_exported_as_otlp(): void
    {
        Http::fake();

        Flow::span('checkout', function () {
            try {
                Flow::span('charge card', fn () => throw new RuntimeException('declined'));
            } catch (RuntimeException) {
                // swallow so the parent flow completes
            }
        });
        $trace = Flow::traceId();

        Http::assertSent(function (Request $request) use ($trace) {
            if ($request->url() !== 'http://collector:4318/v1/traces') {
                return false;
            }

            $spans = $request->data()['resourceSpans'][0]['scopeSpans'][0]['spans'] ?? [];

            return count($spans) === 2
                && collect($spans)->every(fn ($s) => $s['traceId'] === $trace)
                && collect($spans)->contains(fn ($s) => $s['name'] === 'charge card' && $s['status']['code'] === 2)
                && collect($spans)->contains(fn ($s) => $s['name'] === 'checkout' && $s['status']['code'] === 1);
        });
    }

    public function test_only_one_export_per_flow(): void
    {
        Http::fake();

        Flow::span('root', function () {
            Flow::span('child', fn () => null);
        });

        Http::assertSentCount(1);
    }
}
