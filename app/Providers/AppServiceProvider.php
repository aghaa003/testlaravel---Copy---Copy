<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // يجب وضع هذا هنا داخل دالة boot
        Authenticate::redirectUsing(function (Request $request) {
            // بما أن تطبيقنا SPA، فنحن دائماً نريد JSON عند فشل المصادقة
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
