<?php

use AdelinFeraru\NestedFlowTracker\Core\FlowTracker;

if (! function_exists('flow')) {
    /**
     * Resolve the flow tracker for the current request/job.
     */
    function flow(): FlowTracker
    {
        return app(FlowTracker::class);
    }
}
