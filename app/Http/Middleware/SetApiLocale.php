<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('X-Locale')
               ?? $request->query('locale')
               ?? config('app.fallback_locale', 'vi');

        if (! in_array($locale, config('app.supported_locales', ['vi', 'en']), true)) {
            $locale = config('app.fallback_locale', 'vi');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
