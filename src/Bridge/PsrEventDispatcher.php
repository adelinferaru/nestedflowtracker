<?php

namespace AdelinFeraru\NestedFlowTracker\Bridge;

use Illuminate\Contracts\Events\Dispatcher as LaravelDispatcher;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Adapts Laravel's event dispatcher to PSR-14 so FlowTracker can depend on the
 * standard interface and stay framework-agnostic. Laravel's dispatcher accepts
 * objects already; this just enforces the PSR-14 return contract.
 */
final readonly class PsrEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private LaravelDispatcher $events,
    ) {
    }

    public function dispatch(object $event): object
    {
        $this->events->dispatch($event);

        return $event;
    }
}
