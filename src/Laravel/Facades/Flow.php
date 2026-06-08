<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Facades;

use AdelinFeraru\NestedFlowTracker\Core\FlowTracker;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed span(string $name, \Closure $callback, array<string, mixed> $options = [])
 * @method static \AdelinFeraru\NestedFlowTracker\Core\Span|null start(string $name, array<string, mixed> $options = [])
 * @method static \AdelinFeraru\NestedFlowTracker\Core\Span|null end(array<string, mixed> $options = [])
 * @method static void fail(\Throwable $e, array<string, mixed> $context = [])
 * @method static \AdelinFeraru\NestedFlowTracker\Core\Span|null currentSpan()
 * @method static string|null traceId()
 * @method static \AdelinFeraru\NestedFlowTracker\Core\FlowTracker setTraceId(string $traceId)
 * @method static \AdelinFeraru\NestedFlowTracker\Core\FlowTracker setUser(int|string|null $userId)
 * @method static bool enabled()
 * @method static void flush()
 *
 * @see \AdelinFeraru\NestedFlowTracker\Core\FlowTracker
 */
class Flow extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FlowTracker::class;
    }
}
