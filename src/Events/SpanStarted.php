<?php

namespace AdelinFeraru\NestedFlowTracker\Events;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

class SpanStarted
{
    public function __construct(
        public readonly FlowSpan $span
    ) {
    }
}
