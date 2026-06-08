<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Span;

/**
 * Where spans go. Implementations decide how (or whether) a span is persisted/emitted.
 * The tracker builds the in-memory Span and calls these around the timed work.
 */
interface SpanDriver
{
    /**
     * A span has opened. $parent is the enclosing open span, if any.
     */
    public function opening(Span $span, ?Span $parent): void;

    /**
     * A span has closed (duration and status are finalized).
     */
    public function closing(Span $span): void;

    /**
     * Reset per-flow state. Called by FlowTracker::flush() between requests / jobs
     * so a worker that fails to close a flow root (exception above the tracker,
     * SIGTERM mid-flow, etc.) doesn't leak per-flow buffers or model maps across
     * subsequent flows on the same scoped instance.
     */
    public function flush(): void;
}
