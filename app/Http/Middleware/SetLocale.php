<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', config('app.locale'));

        if (in_array($locale, ['en', 'fr'])) {
            App::setLocale($locale);
        }

        // A nursery and primary school has pupils and a head teacher, not
        // students and a principal. When the school runs only that level,
        // its word list applies on top of the usual one, in either language;
        // a secondary school keeps its own words.
        try {
            $loader = app('translator')->getLoader();
            if ($loader instanceof \App\Support\LevelFileLoader && \App\Support\LevelContext::options() === ['nursery_primary']) {
                $loader->addOverridePath(lang_path('nursery-primary'));
            }
        } catch (\Throwable) {
            // No settings table yet (a fresh install): nothing to adjust.
        }

        return $next($request);
    }
}
