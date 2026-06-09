<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Drivers;

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

    /**
     * Open spans not yet closed. The flow is complete when this returns to zero —
     * checking the closed span's parent_span_id instead would never fire for a
     * flow continued via options['parent_span_id'], whose outermost span has a
     * non-null parent.
     */
    private int $depth = 0;

    public function __construct(
        private readonly OtelExporter $exporter,
    ) {
    }

    public function opening(Span $span): void
    {
        $this->depth++;
    }

    public function closing(Span $span): void
    {
        $this->buffer[] = $span;

        if (--$this->depth <= 0) {
            $this->depth = 0;
            // Detach before exporting so a failed POST can't leave spans behind
            // to be replayed into the next flow's export.
            $spans = $this->buffer;
            $this->buffer = [];
            $this->exporter->exportSpans($spans);
        }
    }

    public function flush(): void
    {
        // Drop the buffer without exporting — the surrounding flow is being abandoned.
        $this->buffer = [];
        $this->depth = 0;
    }
}
