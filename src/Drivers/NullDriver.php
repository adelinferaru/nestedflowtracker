<?php

namespace AdelinFeraru\NestedFlowTracker\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Span;

/**
 * Discards spans. Tracking stays "on" (the API works, events fire) but nothing
 * is persisted or emitted.
 */
class NullDriver implements SpanDriver
{
    public function opening(Span $span, ?Span $parent): void
    {
    }

    public function closing(Span $span): void
    {
    }
}
