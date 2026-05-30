<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMcpTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): mixed
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token) {
            abort(401, 'Unauthenticated.');
        }

        foreach ($abilities as $ability) {
            if (! $token->can($ability)) {
                abort(403, "Token missing required ability: {$ability}");
            }
        }

        return $next($request);
    }
}
