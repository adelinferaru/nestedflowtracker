<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Drivers\SpanDriver;
use AdelinFeraru\NestedFlowTracker\Core\Span;
use AdelinFeraru\NestedFlowTracker\Laravel\Eloquent\FlowSpan;
use DateTimeImmutable;

/**
 * Stores spans in the flow_spans table via Eloquent's query builder. The tree is
 * reconstructed from parent_span_id at read time; no nested-set bookkeeping.
 *
 * Insert on opening(), UPDATE WHERE span_id = ? on closing() — same shape as
 * {@see \AdelinFeraru\NestedFlowTracker\Core\Drivers\PdoDriver}, just on Laravel's
 * connection so flow.connection / Eloquent's casts work for the viewer reads.
 */
class EloquentDatabaseDriver implements SpanDriver
{
    public function opening(Span $span, ?Span $parent): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        FlowSpan::query()->insert(
            $span->toRow() + ['created_at' => $now, 'updated_at' => $now]
        );
    }

    public function closing(Span $span): void
    {
        if ($span->span_id === null) {
            return;
        }

        FlowSpan::query()
            ->where('span_id', $span->span_id)
            ->update(
                $span->toRowMutable() + ['updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s')]
            );
    }

    public function flush(): void
    {
        // The driver holds no per-flow state — span_id is the canonical identity
        // and each opening()/closing() pair is independent.
    }
}
