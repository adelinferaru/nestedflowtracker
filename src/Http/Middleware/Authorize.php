<?php

namespace AdelinFeraru\NestedFlowTracker\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the viewer routes. Access is granted automatically in the local
 * environment; otherwise a `viewFlow` gate must allow the current user.
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local') || Gate::check('viewFlow', [$request->user()])) {
            return $next($request);
        }

        abort(403);
    }
}
