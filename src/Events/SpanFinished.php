<?php

namespace AdelinFeraru\NestedFlowTracker\Events;

use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;

class SpanFinished
{
    public function __construct(
        public readonly FlowSpan $span
    ) {
    }
}
