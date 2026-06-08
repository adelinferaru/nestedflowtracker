<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Events;

use AdelinFeraru\NestedFlowTracker\Core\Span;

class SpanStarted
{
    public function __construct(
        public readonly Span $span
    ) {
    }
}
