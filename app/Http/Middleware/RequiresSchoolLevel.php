<?php

namespace App\Http\Middleware;

use App\Support\LevelContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a screen to the school levels it makes sense at.
 *
 * Some parts of the system describe one kind of school. GCE is a secondary
 * qualification; streams are Science and Arts, not Nursery 2; choosing
 * elective subjects is something a secondary pupil does. A school that runs
 * only Nursery / Primary should not be offered any of them.
 *
 * The menu hides these already, but hiding a link is not a rule — the address
 * still works, and a bookmark from before the mode changed still resolves.
 * This is the rule.
 */
class RequiresSchoolLevel
{
    public function handle(Request $request, Closure $next, string ...$levels): Response
    {
        foreach ($levels as $level) {
            if (LevelContext::runs($level)) {
                return $next($request);
            }
        }

        abort(404);
    }
}
