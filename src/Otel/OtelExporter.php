<?php

namespace AdelinFeraru\NestedFlowTracker\Otel;

use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Http;

/**
 * Exports a recorded flow to an OTLP/HTTP collector as OTLP-JSON. No OpenTelemetry
 * SDK is required — we build the payload and POST it to {endpoint}/v1/traces.
 */
class OtelExporter
{
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function export(string $traceId): void
    {
        $endpoint = $this->config->get('flow.otel.endpoint');
        if (empty($endpoint)) {
            return;
        }

        $spans = FlowSpan::query()->where('trace_id', $traceId)->orderBy('_lft')->get();
        if ($spans->isEmpty()) {
            return;
        }

        /** @var array<string, mixed> $headers */
        $headers = $this->config->get('flow.otel.headers', []) ?: [];

        Http::withHeaders($headers)
            ->timeout((int) $this->config->get('flow.otel.timeout', 5))
            ->post(rtrim((string) $endpoint, '/') . '/v1/traces', $this->toOtlp($spans));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, FlowSpan> $spans
     * @return array<string, mixed>
     */
    private function toOtlp($spans): array
    {
        $spanIdById = [];
        foreach ($spans as $span) {
            $spanIdById[$span->id] = $span->span_id;
        }

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

            $parentSpanId = $span->parent_id !== null ? ($spanIdById[$span->parent_id] ?? null) : null;
            if ($parentSpanId !== null) {
                $otlpSpan['parentSpanId'] = $parentSpanId;
            }

            $otlpSpans[] = $otlpSpan;
        }

        $service = (string) $this->config->get('flow.component', 'app');

        return [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => $service]],
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
    private function attributes(FlowSpan $span): array
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
