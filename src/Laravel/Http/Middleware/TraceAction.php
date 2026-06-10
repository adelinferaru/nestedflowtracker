<?php

namespace AdelinFeraru\NestedFlowTracker\Laravel\Http\Middleware;

use AdelinFeraru\NestedFlowTracker\Core\Attributes\Trace;
use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Core\FlowTracker;
use AdelinFeraru\NestedFlowTracker\Core\Span;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps a route action in a span when the action method (or its controller
 * class) carries the #[Trace] attribute. Registered on the web + api groups
 * when `flow.attributes` is enabled; routes without the attribute pass through
 * with a single reflection lookup and no span.
 *
 * Nests under the request root span when `flow.auto.http` is also on.
 */
class TraceAction
{
    public function __construct(
        private readonly FlowTracker $flow,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $route = $request->route();

        if (! $this->flow->enabled() || ! $route instanceof Route) {
            return $next($request);
        }

        $trace = $this->traceAttribute($route);

        if ($trace === null) {
            return $next($request);
        }

        return $this->flow->span($trace->name ?? $this->defaultName($route), function (?Span $span) use ($next, $request) {
            $response = $next($request);

            // An exception thrown by the action is rendered to a response by the
            // routing pipeline before it reaches this middleware — mirror
            // TrackRequest and flag 5xx responses as failed.
            if ($span !== null && $response instanceof Response && $response->getStatusCode() >= 500) {
                $span->status = SpanStatus::Failed;
            }

            return $response;
        });
    }

    private function traceAttribute(Route $route): ?Trace
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof Closure) {
            $attributes = (new ReflectionFunction($uses))->getAttributes(Trace::class);

            return $attributes === [] ? null : $attributes[0]->newInstance();
        }

        if (! is_string($uses)) {
            return null;
        }

        // Laravel normalizes controller actions (including invokables) to
        // "Class@method" before the route runs.
        [$class, $method] = array_pad(explode('@', $uses, 2), 2, '__invoke');

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        $attributes = (new ReflectionMethod($class, $method))->getAttributes(Trace::class);

        if ($attributes === []) {
            // A class-level attribute traces every action on the controller.
            $attributes = (new ReflectionClass($class))->getAttributes(Trace::class);
        }

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    private function defaultName(Route $route): string
    {
        $action = $route->getActionName(); // "App\…\Controller@method" or "Closure"

        if ($action === 'Closure') {
            return 'closure: ' . $route->uri();
        }

        $basename = strrchr($action, '\\');

        return $basename === false ? $action : substr($basename, 1);
    }
}
