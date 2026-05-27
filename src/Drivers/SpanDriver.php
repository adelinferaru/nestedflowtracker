<?php

namespace AdelinFeraru\NestedFlowTracker\Drivers;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

/**
 * Where spans go. Implementations decide how (or whether) a span is persisted/emitted.
 * The tracker builds the in-memory FlowSpan and calls these around the timed work.
 */
interface SpanDriver
{
    /**
     * A span has opened. $parent is the enclosing open span, if any.
     */
    public function opening(FlowSpan $span, ?FlowSpan $parent): void;

    /**
     * A span has closed (duration and status are finalized).
     */
    public function closing(FlowSpan $span): void;
}
