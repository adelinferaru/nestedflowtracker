<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Drivers;

use AdelinFeraru\NestedFlowTracker\Core\Span;

/**
 * Discards spans. Tracking stays "on" (the API works, events fire) but nothing
 * is persisted or emitted.
 */
class NullDriver implements SpanDriver
{
    public function opening(Span $span): void
    {
    }

    public function closing(Span $span): void
    {
    }

    public function flush(): void
    {
    }
}
