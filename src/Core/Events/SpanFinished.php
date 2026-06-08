<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Events;

use AdelinFeraru\NestedFlowTracker\Core\Span;

class SpanFinished
{
    public function __construct(
        public readonly Span $span
    ) {
    }
}
