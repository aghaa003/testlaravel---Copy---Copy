<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // For SPA: return null so unauthenticated requests get 401 JSON not a redirect
        Authenticate::redirectUsing(function (Request $request) {
            return null;
        });

        // Global API rate limiter — 120 req/min per user or IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Serve files via BinaryFileResponse so HTTP Range requests
        // (required for <video> seeking) get proper 206 responses.
        Storage::disk('public')->serveUsing(function (Request $request, $path, $headers) {
            return response()->file(Storage::disk('public')->path($path), $headers);
        });
    }

    public function register(): void {}
}
