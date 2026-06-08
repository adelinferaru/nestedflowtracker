<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Otel;

use AdelinFeraru\NestedFlowTracker\Core\Otel\OtelExporter;
use AdelinFeraru\NestedFlowTracker\Core\Span;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Queued export of one completed flow to the OTLP collector. Reads the trace
 * back out of the Eloquent flow_spans store (this path is only reachable when
 * the `database` driver is active), converts the rows into framework-agnostic
 * Span POPOs, and hands them to the Core exporter.
 */
class ExportTrace implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly string $traceId,
    ) {
    }

    public function handle(OtelExporter $exporter): void
    {
        $rows = FlowSpan::query()
            ->where('trace_id', $this->traceId)
            ->orderBy('started_at')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $exporter->exportSpans($rows->map(fn (FlowSpan $row) => $this->toSpan($row))->all());
    }

    private function toSpan(FlowSpan $row): Span
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
