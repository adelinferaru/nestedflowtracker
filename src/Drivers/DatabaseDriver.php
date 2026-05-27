<?php

namespace AdelinFeraru\NestedFlowTracker\Drivers;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

/**
 * Stores spans in the database as a nested-set tree. This is the full-featured
 * driver: the viewer, the artisan commands, and the OTLP export all read from it.
 */
class DatabaseDriver implements SpanDriver
{
    public function opening(FlowSpan $span, ?FlowSpan $parent): void
    {
        // Nest under the open parent unless an explicit parent_id was already set.
        if ($span->parent_id === null && $parent !== null) {
            $span->appendToNode($parent);
        }

        $span->save();
    }

    public function closing(FlowSpan $span): void
    {
        $span->save();
    }
}
