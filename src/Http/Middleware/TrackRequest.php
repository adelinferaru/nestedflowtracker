<?php

namespace AdelinFeraru\NestedFlowTracker\Http\Middleware;

use AdelinFeraru\NestedFlowTracker\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\FlowTracker;
use AdelinFeraru\NestedFlowTracker\Models\FlowSpan;
use AdelinFeraru\NestedFlowTracker\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps each HTTP request in a root span. Registered on the web + api middleware
 * groups when `flow.auto.http` is enabled.
 */
class TrackRequest
{
    public function __construct(
        private readonly FlowTracker $flow,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->flow->enabled()) {
            return $next($request);
        }

        // Continue an upstream trace if the caller propagated one.
        if ($context = TraceContext::parse($request->headers->get('traceparent'))) {
            $this->flow->setTraceId($context->traceId);
        }

        $route = $request->route();
        $name = $request->method() . ' ' . ($route?->uri() ?? $request->path());

        return $this->flow->span($name, function (?FlowSpan $span) use ($next, $request) {
            $response = $next($request);

            if ($span !== null && $response instanceof Response) {
                $status = $response->getStatusCode();
                $span->context = array_merge($span->context ?? [], ['status' => $status]);

                if ($status >= 500) {
                    $span->status = SpanStatus::Failed;
                }
            }

            return $response;
        }, [
            'root' => true,
            'context' => [
                'method' => $request->method(),
                'path' => $request->path(),
            ],
        ]);
    }
}
