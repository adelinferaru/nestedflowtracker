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

    public function opening(Span $span): void
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

        foreach (array_chunk($this->buffer, self::BATCH_SIZE) as $batch) {
            $rows = [];
            foreach ($batch as $span) {
                $rows[] = $span->toRow() + ['created_at' => $now, 'updated_at' => $now];
            }
            FlowSpan::query()->insert($rows);
        }

        $this->buffer = [];
    }
}
