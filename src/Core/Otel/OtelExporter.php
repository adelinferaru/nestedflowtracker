<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Otel;

use AdelinFeraru\NestedFlowTracker\Core\Span;
use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Exports recorded flows to an OTLP/HTTP collector as OTLP-JSON. No OpenTelemetry
 * SDK is required — we build the payload and POST it to {endpoint}/v1/traces via
 * a PSR-18 HTTP client and PSR-17 request/stream factories. Use any compliant
 * implementation (Guzzle, Symfony HttpClient, etc.).
 */
class OtelExporter
{
    public function __construct(
        private readonly OtelExporterConfig $config,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param iterable<Span> $spans
     */
    public function exportSpans(iterable $spans): void
    {
        if ($this->config->endpoint === '') {
            return;
        }

        $payload = (string) json_encode($this->toOtlp($spans));
        $url = rtrim($this->config->endpoint, '/') . '/v1/traces';

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($payload));

        foreach ($this->config->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $this->client->sendRequest($request);
    }

    /**
     * @param iterable<Span> $spans
     * @return array<string, mixed>
     */
    private function toOtlp(iterable $spans): array
    {
        $otlpSpans = [];
        foreach ($spans as $span) {
            $start = $this->toNanos($span->started_at);
            $end = $start + (int) round(($span->duration ?? 0.0) * 1_000_000_000);

            $otlpSpan = [
                'traceId' => $span->trace_id,
                'spanId' => $span->span_id,
                'name' => $span->name,
                'kind' => 1, // SPAN_KIND_INTERNAL
                'startTimeUnixNano' => (string) $start,
                'endTimeUnixNano' => (string) $end,
                'status' => ['code' => $this->statusCode($span->status)],
                'attributes' => $this->attributes($span),
            ];

            if ($span->parent_span_id !== null) {
                $otlpSpan['parentSpanId'] = $span->parent_span_id;
            }

            $otlpSpans[] = $otlpSpan;
        }

        return [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => $this->config->serviceName]],
                    ],
                ],
                'scopeSpans' => [[
                    'scope' => ['name' => 'nestedflowtracker'],
                    'spans' => $otlpSpans,
                ]],
            ]],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attributes(Span $span): array
    {
        $attributes = [
            ['key' => 'flow.component', 'value' => ['stringValue' => $span->component]],
        ];

        if (! empty($span->context)) {
            $attributes[] = ['key' => 'flow.context', 'value' => ['stringValue' => (string) json_encode($span->context)]];
        }

        return $attributes;
    }

    private function statusCode(SpanStatus $status): int
    {
        return match ($status) {
            SpanStatus::Ok => 1,      // STATUS_CODE_OK
            SpanStatus::Failed => 2,  // STATUS_CODE_ERROR
            SpanStatus::Running => 0, // STATUS_CODE_UNSET
        };
    }

    /**
     * Convert a "seconds.microseconds" timestamp to integer nanoseconds without
     * float rounding (the value exceeds float's safe-integer range).
     */
    private function toNanos(?string $startedAt): int
    {
        if ($startedAt === null || $startedAt === '') {
            return 0;
        }

        [$seconds, $micros] = array_pad(explode('.', $startedAt, 2), 2, '0');
        $micros = str_pad(substr($micros, 0, 6), 6, '0');

        return ((int) $seconds) * 1_000_000_000 + ((int) $micros) * 1_000;
    }
}
