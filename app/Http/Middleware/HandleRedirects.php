<?php

namespace App\Http\Middleware;

use App\Services\Seo\RedirectCacheService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    public function __construct(
        private readonly RedirectCacheService $redirectCache,
    ) {}

    /**
     * Intercept GET web requests and perform any matching redirect.
     *
     * Skipped paths:
     *   - Non-GET methods   (POST, PUT, PATCH, DELETE …)
     *   - /api/*            (API routes handle their own logic)
     *   - /admin/*          (Filament admin panel)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Only handle GET requests.
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // 2. Skip /api/* paths.
        if ($request->is('api/*')) {
            return $next($request);
        }

        // 3. Skip /admin/* paths.
        if ($request->is('admin/*')) {
            return $next($request);
        }

        // 4. Build the canonical path including the leading slash.
        $path = '/' . ltrim($request->path(), '/');

        // 5. Detect locale from the URL prefix — route params are not yet bound
        //    at middleware time, so we parse the path directly.
        $detectedLocale = $this->parseLocale($path);

        // 6. Resolve against the Redis-cached redirect table.
        //    Locale is passed so we skip entries that belong to a different locale.
        //    resolve() internally dispatches IncrementRedirectHits as a background job.
        $redirect = $this->redirectCache->resolve($path, $detectedLocale);

        // 7. If a matching active redirect is found, issue the HTTP redirect.
        if ($redirect !== null && $redirect->is_active) {
            return redirect($redirect->to_path, $redirect->type->value);
        }

        // 8. No match — continue normal request lifecycle.
        return $next($request);
    }

    /**
     * Extract the locale segment from a path like /vi/products/foo → "vi".
     * Returns null when no supported locale prefix is found.
     */
    private function parseLocale(string $path): ?string
    {
        $segment = explode('/', ltrim($path, '/'))[0] ?? '';

        $supported = config('app.supported_locales', []);

        return in_array($segment, $supported, true) ? $segment : null;
    }
}
