<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || ! $user->roles->contains('name', 'admin')) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بالوصول إلى هذه الصفحة'], 403);
        }

        return $next($request);
    }
}
