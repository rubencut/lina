<?php

namespace App\Http\Middleware;

use Closure;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        if (!$request->user() || !in_array($request->user()->role, $roles, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
