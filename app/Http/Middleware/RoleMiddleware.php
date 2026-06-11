<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Gate a route by user role.
     *
     * Usage in routes: ->middleware('role:admin') or ->middleware('role:employer,admin').
     * Runs after auth:sanctum, so an authenticated user is expected; if the role
     * doesn't match the allowed list, respond 403.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json(['error' => 'غير مصرح'], 403);
        }

        return $next($request);
    }
}
