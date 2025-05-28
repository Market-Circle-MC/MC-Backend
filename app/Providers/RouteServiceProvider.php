<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home'; // Or '/' if you prefer

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Define rate limiting for API requests (optional, but good practice)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // Web routes (for your traditional web pages if any)
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // API routes (crucial for your backend API)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Console routes (for Artisan commands)
            Route::middleware('web') // Console routes typically use web middleware for session access if needed
                ->group(base_path('routes/console.php')); // If console.php is only for artisan commands, it might not need middleware.
        });
    }
}