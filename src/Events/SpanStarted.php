<?php

namespace AdelinFeraru\NestedFlowTracker\Events;

use AdelinFeraru\NestedFlowTracker\Core\Span;

class SpanStarted
{
    public function __construct(
        public readonly Span $span
    ) {
    }
}
