<?php

namespace AdelinFeraru\NestedFlowTracker\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Drivers\SpanDriver;
use AdelinFeraru\NestedFlowTracker\Core\Otel\OtelExporter;
use AdelinFeraru\NestedFlowTracker\Core\Span;

/**
 * Sends spans straight to an OTLP/HTTP collector with no database. Spans are
 * buffered in memory and exported as one trace when the root span closes.
 */
class OtelDriver implements SpanDriver
{
    /** @var list<Span> */
    private array $buffer = [];

    public function __construct(
        private readonly OtelExporter $exporter,
    ) {
    }

    public function opening(Span $span, ?Span $parent): void
    {
    }

    public function closing(Span $span): void
    {
        $this->buffer[] = $span;

        // A root span (no parent) closing means the whole flow is complete.
        if ($span->parent_span_id === null) {
            $this->exporter->exportSpans($this->buffer);
            $this->buffer = [];
        }
    }
}
