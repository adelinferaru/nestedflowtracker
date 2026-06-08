<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Drivers\SpanDriver;
use AdelinFeraru\NestedFlowTracker\Core\Span;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;

/**
 * Like {@see EloquentDatabaseDriver}, but buffers a whole flow in memory and
 * writes it in a single bulk insert when the root span closes — roughly one
 * write per flow instead of two per span.
 *
 * Trade-off: spans are not persisted until the flow completes (a crash mid-flow
 * loses it). Enable with `flow.buffer = true`.
 */
class EloquentBufferedDriver implements SpanDriver
{
    /** @var list<Span> */
    private array $buffer = [];

    public function opening(Span $span, ?Span $parent): void
    {
    }

    public function closing(Span $span): void
    {
        $this->buffer[] = $span;

        // A root span (no parent) closing means the whole flow is complete.
        if ($span->parent_span_id === null) {
            $this->writeBuffer();
        }
    }

    public function flush(): void
    {
        // Drop the buffer without writing — the surrounding flow is being abandoned.
        $this->buffer = [];
    }

    private function writeBuffer(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $now = now()->toDateTimeString();
        $rows = [];
        foreach ($this->buffer as $span) {
            $rows[] = $span->toRow() + ['created_at' => $now, 'updated_at' => $now];
        }

        FlowSpan::query()->insert($rows);

        $this->buffer = [];
    }
}
