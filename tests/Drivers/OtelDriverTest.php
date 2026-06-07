<?php

namespace AdelinFeraru\NestedFlowTracker\Tests\Drivers;

use AdelinFeraru\NestedFlowTracker\Facades\Flow;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Tests\Support\RecordingHttpClient;
use AdelinFeraru\NestedFlowTracker\Tests\TestCase;
use Psr\Http\Client\ClientInterface;

class OtelDriverTest extends TestCase
{
    private RecordingHttpClient $http;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('flow.driver', 'otel');
        $app['config']->set('flow.otel.endpoint', 'http://collector:4318');

        $this->http = new RecordingHttpClient();
        $app->instance(ClientInterface::class, $this->http);
    }

    public function test_flow_is_exported_directly_without_touching_the_database(): void
    {
        Flow::span('checkout', function () {
            Flow::span('charge card', fn () => null);
        });
        $trace = Flow::traceId();

        // Nothing is stored in the database with the otel driver.
        $this->assertSame(0, FlowSpan::query()->count());

        $this->assertCount(1, $this->http->sent);
        $request = $this->http->sent[0];
        $this->assertSame('http://collector:4318/v1/traces', (string) $request->getUri());

        $payload = json_decode((string) $request->getBody(), true);
        $spans = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'] ?? [];
        $names = collect($spans)->pluck('name')->all();
        $child = collect($spans)->firstWhere('name', 'charge card');

        $this->assertCount(2, $spans);
        $this->assertContains('checkout', $names);
        $this->assertTrue(collect($spans)->every(fn ($s) => $s['traceId'] === $trace));
        $this->assertNotNull($child);
        $this->assertArrayHasKey('parentSpanId', $child);
    }
}
