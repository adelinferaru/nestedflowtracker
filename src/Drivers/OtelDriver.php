<?php

namespace AdelinFeraru\NestedFlowTracker\Drivers;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use AdelinFeraru\NestedFlowTracker\Otel\OtelExporter;

/**
 * Sends spans straight to an OTLP/HTTP collector with no database. Spans are
 * buffered in memory and exported as one trace when the root span closes.
 */
class OtelDriver implements SpanDriver
{
    /** @var list<FlowSpan> */
    private array $buffer = [];

    public function __construct(
        private readonly OtelExporter $exporter,
    ) {
    }

    public function opening(FlowSpan $span, ?FlowSpan $parent): void
    {
    }

    public function closing(FlowSpan $span): void
    {
        $this->buffer[] = $span;

        // A root span (no parent) closing means the whole flow is complete.
        if ($span->parent_span_id === null) {
            $this->exporter->exportSpans($this->buffer);
            $this->buffer = [];
        }
    }
}
