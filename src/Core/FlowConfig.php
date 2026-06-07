<?php

namespace AdelinFeraru\NestedFlowTracker\Core;

/**
 * Framework-agnostic configuration that FlowTracker reads. The Laravel package
 * builds one of these from config('flow.*') in its service provider; non-Laravel
 * callers construct it directly when instantiating FlowTracker.
 */
final readonly class FlowConfig
{
    public function __construct(
        public bool $enabled = true,
        public string $component = 'app',
    ) {
    }
}
