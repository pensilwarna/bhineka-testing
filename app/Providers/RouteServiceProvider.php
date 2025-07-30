<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home'; // Atau rute home Anda

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting(); // Pastikan ini terpanggil

        $this->routes(function () {
            // ... (bagian lain dari definisi rute Anda)
            // Contoh default web routes
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Contoh default api routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Pastikan rate limiter 'login' ini ada!
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->email ?: $request->ip())->response(function (Request $request, array $headers) {
                // Opsional: Kustomisasi respons ketika rate limit tercapai
                throw new \Illuminate\Http\Exceptions\ThrottleRequestsException(
                    'Too many login attempts. Please try again in ' . ceil($headers['Retry-After'] / 60) . ' minutes.',
                    null,
                    $headers
                );
            });
        });

        // Tambahkan rate limiter lainnya jika diperlukan
    }
}