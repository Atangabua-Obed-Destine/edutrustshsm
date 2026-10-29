<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user() || !in_array($request->user()->role, $roles)) {
            abort(403, 'Unauthorized access.');
        }

        // Deactivation used to be checked only at login, so a live session
        // outlasted it. Now it ends at the next request.
        if (! $request->user()->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            abort(403, __('This account has been deactivated.'));
        }

        return $next($request);
    }
}
