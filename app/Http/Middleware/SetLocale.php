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

        // A nursery and primary school has pupils, not students. When the
        // school runs only that level, its English word list applies on top
        // of the usual one; a secondary school keeps "student".
        try {
            if (\App\Support\LevelContext::options() === ['nursery_primary']) {
                app('translator')->addJsonPath(lang_path('nursery-primary'));
            }
        } catch (\Throwable) {
            // No settings table yet (a fresh install): nothing to adjust.
        }

        return $next($request);
    }
}
