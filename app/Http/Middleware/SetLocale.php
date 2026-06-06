<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Accept locale two ways:
     *   - middleware('set.locale:vi')  — hardcoded prefix routes (no {locale} in URI)
     *   - middleware('set.locale')     — dynamic {locale} param in URI (llms, legacy)
     *
     * In both cases the locale is injected as a virtual route parameter so
     * controllers can still type-hint `string $locale` normally.
     */
    public function handle(Request $request, Closure $next, string $localeParam = ''): Response
    {
        $locale = $localeParam ?: $request->route('locale');

        if (! in_array($locale, config('app.supported_locales'), true)) {
            abort(404);
        }

        // Prepend locale so it's the first route parameter — controllers declare
        // `string $locale` as the first argument and Laravel injects positionally.
        $route = $request->route();
        $route->parameters = array_merge(['locale' => $locale], $route->parameters());

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
