<?php

namespace AdelinFeraru\NestedFlowTracker\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Span;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

/**
 * Like the database driver, but buffers a whole flow in memory and writes it in
 * a single bulk insert when the root span closes — roughly one write per flow
 * instead of two per span.
 *
 * Trade-off: spans are not persisted until the flow completes (a crash mid-flow
 * loses it), and the tree is reconstructed from parent_span_id (not the nested
 * set). Enable with `flow.buffer = true`.
 */
class BufferedDatabaseDriver implements SpanDriver
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
            $this->flush();
        }
    }

    private function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $now = now()->toDateTimeString();
        $rows = [];
        foreach ($this->buffer as $span) {
            $rows[] = [
                'trace_id' => $span->trace_id,
                'span_id' => $span->span_id,
                'parent_span_id' => $span->parent_span_id,
                'name' => $span->name,
                'component' => $span->component,
                'user_id' => $span->user_id,
                'status' => $span->status->value,
                'message' => $span->message,
                'duration' => $span->duration,
                'started_at' => $span->started_at,
                'context' => $span->context !== null ? json_encode($span->context) : null,
                'result' => $span->result !== null ? json_encode($span->result) : null,
                // Nested-set columns are unused by the buffered path (reads use parent_span_id).
                '_lft' => 0,
                '_rgt' => 0,
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        FlowSpan::query()->insert($rows);

        $this->buffer = [];
    }
}
