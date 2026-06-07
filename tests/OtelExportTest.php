<?php

namespace AdelinFeraru\NestedFlowTracker\Tests;

use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Tests\Support\RecordingHttpClient;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

class OtelExportTest extends TestCase
{
    private RecordingHttpClient $http;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('flow.otel.enabled', true);
        $app['config']->set('flow.otel.endpoint', 'http://collector:4318');

        $this->http = new RecordingHttpClient();
        $app->instance(ClientInterface::class, $this->http);
    }

    public function test_span_records_a_span_id_and_start_time(): void
    {
        $span = Flow::start('task');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', (string) $span->span_id);
        $this->assertNotNull($span->started_at);
    }

    public function test_completed_flow_is_exported_as_otlp(): void
    {
        Flow::span('checkout', function () {
            try {
                Flow::span('charge card', fn () => throw new RuntimeException('declined'));
            } catch (RuntimeException) {
                // swallow so the parent flow completes
            }
        });
        $trace = Flow::traceId();

        $this->assertCount(1, $this->http->sent);
        $request = $this->http->sent[0];
        $this->assertSame('http://collector:4318/v1/traces', (string) $request->getUri());

        $payload = json_decode((string) $request->getBody(), true);
        $spans = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'] ?? [];
        $this->assertCount(2, $spans);
        $this->assertTrue(collect($spans)->every(fn ($s) => $s['traceId'] === $trace));
        $this->assertTrue(collect($spans)->contains(fn ($s) => $s['name'] === 'charge card' && $s['status']['code'] === 2));
        $this->assertTrue(collect($spans)->contains(fn ($s) => $s['name'] === 'checkout' && $s['status']['code'] === 1));
    }

    public function test_only_one_export_per_flow(): void
    {
        Flow::span('root', function () {
            Flow::span('child', fn () => null);
        });

        $this->assertCount(1, $this->http->sent);
    }
}
