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

        // 5. Resolve against the Redis-cached redirect table.
        //    resolve() internally dispatches IncrementRedirectHits as a background job.
        $redirect = $this->redirectCache->resolve($path);

        // 6. If a matching active redirect is found, issue the HTTP redirect.
        if ($redirect !== null && $redirect->is_active) {
            return redirect($redirect->to_path, $redirect->type->value);
        }

        // 7. No match — continue normal request lifecycle.
        return $next($request);
    }
}
