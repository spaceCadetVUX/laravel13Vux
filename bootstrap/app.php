<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum cookie auth for SPA (stateful domains)
        $middleware->statefulApi();

        // CORS — allow Nuxt 3 frontend origin
        $middleware->web(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\HandleRedirects::class,
        ]);

        // Force JSON on all API requests (ForceJsonResponse)
        $middleware->api(append: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);

        // Alias for Sanctum token guard used in route definitions
        $middleware->alias([
            'auth.sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
