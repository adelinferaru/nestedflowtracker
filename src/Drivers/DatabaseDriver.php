<?php

namespace AdelinFeraru\NestedFlowTracker\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Drivers\SpanDriver;
use AdelinFeraru\NestedFlowTracker\Core\Span;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

/**
 * Stores spans in the database as a nested-set tree. This is the full-featured
 * driver: the viewer, the artisan commands, and the OTLP export all read from it.
 *
 * The tracker hands us framework-agnostic Span POPOs; we hydrate an Eloquent
 * FlowSpan per span for persistence, and keep a span_id -> model map so the
 * `closing()` write updates the same row and child spans can locate their parent
 * row for nested-set linkage.
 */
class DatabaseDriver implements SpanDriver
{
    /** @var array<string, FlowSpan> Persisted Eloquent records, keyed by 16-hex span_id. */
    private array $byId = [];

    public function opening(Span $span, ?Span $parent): void
    {
        $eloquent = new FlowSpan();
        $this->fillFromSpan($eloquent, $span);

        $parentRow = $parent !== null && $parent->span_id !== null
            ? ($this->byId[$parent->span_id] ?? null)
            : null;

        // Nest under the open parent unless an explicit parent_id was already set.
        if ($span->parent_id === null && $parentRow !== null) {
            $eloquent->appendToNode($parentRow);
        } elseif ($span->parent_id !== null) {
            $eloquent->parent_id = $span->parent_id;
        }

        $eloquent->save();

        if ($span->span_id !== null) {
            $this->byId[$span->span_id] = $eloquent;
        }
    }

    public function closing(Span $span): void
    {
        $eloquent = $span->span_id !== null ? ($this->byId[$span->span_id] ?? null) : null;
        if ($eloquent === null) {
            return;
        }

        $this->fillFromSpan($eloquent, $span);
        $eloquent->save();

        // Release per-flow state once the root closes.
        if ($span->parent_span_id === null) {
            $this->byId = [];
        }
    }

    private function fillFromSpan(FlowSpan $eloquent, Span $span): void
    {
        $eloquent->trace_id = $span->trace_id;
        $eloquent->span_id = $span->span_id;
        $eloquent->parent_span_id = $span->parent_span_id;
        $eloquent->name = $span->name;
        $eloquent->component = $span->component;
        $eloquent->user_id = $span->user_id;
        $eloquent->status = $span->status;
        $eloquent->message = $span->message;
        $eloquent->duration = $span->duration;
        $eloquent->started_at = $span->started_at;
        $eloquent->context = $span->context;
        $eloquent->result = $span->result;
    }
}
