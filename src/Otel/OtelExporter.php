<?php

namespace AdelinFeraru\NestedFlowTracker\Otel;

use AdelinFeraru\NestedFlowTracker\Core\Span;
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

    /**
     * Export a trace stored in the database (used by the queued ExportTrace job).
     */
    public function export(string $traceId): void
    {
        $rows = FlowSpan::query()->where('trace_id', $traceId)->orderBy('started_at')->get();
        if ($rows->isEmpty()) {
            return;
        }

        $spans = $rows->map(fn (FlowSpan $row) => $this->fromEloquent($row))->all();
        $this->exportSpans($spans);
    }

    /**
     * Export a set of spans to the OTLP collector.
     *
     * @param iterable<Span> $spans
     */
    public function exportSpans(iterable $spans): void
    {
        $endpoint = $this->config->get('flow.otel.endpoint');
        if (empty($endpoint)) {
            return;
        }

        /** @var array<string, mixed> $headers */
        $headers = $this->config->get('flow.otel.headers', []) ?: [];

        Http::withHeaders($headers)
            ->timeout((int) $this->config->get('flow.otel.timeout', 5))
            ->post(rtrim((string) $endpoint, '/') . '/v1/traces', $this->toOtlp($spans));
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

    /**
     * Hydrate a framework-agnostic Span from a persisted Eloquent row so the
     * exporter only needs to walk one shape.
     */
    private function fromEloquent(FlowSpan $row): Span
    {
        $span = new Span();
        $span->trace_id = (string) $row->trace_id;
        $span->span_id = $row->span_id;
        $span->parent_span_id = $row->parent_span_id;
        $span->name = (string) $row->name;
        $span->component = (string) $row->component;
        $span->user_id = $row->user_id;
        $span->status = $row->status;
        $span->message = $row->message;
        $span->duration = $row->duration;
        $span->started_at = $row->started_at;
        $span->context = $row->context;
        $span->result = $row->result;

        return $span;
    }
}
