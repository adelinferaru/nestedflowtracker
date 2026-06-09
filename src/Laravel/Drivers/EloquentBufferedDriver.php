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
    /**
     * Spans per INSERT batch. Mirrors {@see \AdelinFeraru\NestedFlowTracker\Core\Drivers\BufferedPdoDriver}'s
     * batch size — with 14 columns/row, 60 rows stays well under SQLite's default
     * 999-placeholder limit and Postgres/MySQL's 65535 cap, so a single flow with
     * thousands of spans flushes in a few statements instead of throwing
     * "too many SQL variables".
     */
    private const BATCH_SIZE = 60;

    /** @var list<Span> */
    private array $buffer = [];

    /**
     * Open spans not yet closed. The flow is complete when this returns to zero —
     * checking the closed span's parent_span_id instead would never fire for a
     * flow continued via options['parent_span_id'], whose outermost span has a
     * non-null parent.
     */
    private int $depth = 0;

    public function opening(Span $span): void
    {
        $this->depth++;
    }

    public function closing(Span $span): void
    {
        $this->buffer[] = $span;

        if (--$this->depth <= 0) {
            $this->depth = 0;
            $this->writeBuffer();
        }
    }

    public function flush(): void
    {
        // Drop the buffer without writing — the surrounding flow is being abandoned.
        $this->buffer = [];
        $this->depth = 0;
    }

    private function writeBuffer(): void
    {
        // Detach before writing so a failed INSERT can't leave spans behind to be
        // replayed (or partially duplicated) into the next flow's write.
        $spans = $this->buffer;
        $this->buffer = [];

        $now = now()->toDateTimeString();

        foreach (array_chunk($spans, self::BATCH_SIZE) as $batch) {
            $rows = [];
            foreach ($batch as $span) {
                $rows[] = $span->toRow() + ['created_at' => $now, 'updated_at' => $now];
            }
            FlowSpan::query()->insert($rows);
        }
    }
}
