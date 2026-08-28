<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clears the BranchContext memo at the start of each request.
 *
 * BranchContext caches the accessible branch ids and the active branch for the
 * lifetime of a request, which removes hundreds of redundant lookups. That memo
 * lives in static state, so it must be reset per request — otherwise a
 * long-lived process (the test suite, Octane, a queue worker) would carry one
 * user's branch context into the next request.
 */
class ResolveBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        BranchContext::flush();

        return $next($request);
    }
}
