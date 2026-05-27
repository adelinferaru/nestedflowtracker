<?php

namespace AdelinFeraru\NestedFlowTracker\Drivers;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

/**
 * Discards spans. Tracking stays "on" (the API works, events fire) but nothing
 * is persisted or emitted.
 */
class NullDriver implements SpanDriver
{
    public function opening(FlowSpan $span, ?FlowSpan $parent): void
    {
    }

    public function closing(FlowSpan $span): void
    {
    }
}
