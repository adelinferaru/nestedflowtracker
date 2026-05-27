<?php

namespace AdelinFeraru\NestedFlowTracker\Facades;

use AdelinFeraru\NestedFlowTracker\FlowTracker;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed span(string $name, \Closure $callback, array<string, mixed> $options = [])
 * @method static \AdelinFeraru\NestedFlowTracker\Models\FlowSpan|null start(string $name, array<string, mixed> $options = [])
 * @method static \AdelinFeraru\NestedFlowTracker\Models\FlowSpan|null end(array<string, mixed> $options = [])
 * @method static void fail(\Throwable $e, array<string, mixed> $context = [])
 * @method static \AdelinFeraru\NestedFlowTracker\Models\FlowSpan|null currentSpan()
 * @method static string|null traceId()
 * @method static \AdelinFeraru\NestedFlowTracker\FlowTracker setTraceId(string $traceId)
 * @method static \AdelinFeraru\NestedFlowTracker\FlowTracker setUser(int|string|null $userId)
 * @method static bool enabled()
 * @method static void flush()
 *
 * @see \AdelinFeraru\NestedFlowTracker\FlowTracker
 */
class Flow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FlowTracker::class;
    }
}
