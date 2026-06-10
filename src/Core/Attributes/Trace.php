<?php

namespace AdelinFeraru\NestedFlowTracker\Core\Attributes;

use Attribute;

/**
 * Marks a method, function/closure, or a whole class to be traced as a span.
 *
 * PHP attributes are inert metadata — unlike TypeScript decorators they carry
 * no behavior — so something that owns the call site must read this via
 * reflection and wrap the call. The Laravel adapter does that for route
 * actions ({@see \AdelinFeraru\NestedFlowTracker\Laravel\Http\Middleware\TraceAction})
 * and queued jobs (the queue listeners), gated by `flow.attributes`.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final class Trace
{
    public function __construct(
        /** Span name; defaults to a name derived from the call site. */
        public readonly ?string $name = null,
    ) {
    }
}
