<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Development-də səhvləri erkən tutmaq üçün:
        //  - lazy loading (N+1 sorğu problemi) xəta verir
        //  - fillable-da olmayan sahəyə mass assignment səssizcə atılmır, xəta verir
        Model::shouldBeStrict(! $this->app->isProduction());

        // Login formu: IP üzrə dəqiqədə 20 cəhd (bir hesab üzrə limit LoginRequest-dədir)
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Public rezervasiya: bir IP-dən dəqiqədə 5, saatda 20 rezervasiya (spam / bot qoruması)
        RateLimiter::for('booking', fn (Request $request) => [
            Limit::perMinute(5)->by('booking-min:'.$request->ip()),
            Limit::perHour(20)->by('booking-hour:'.$request->ip()),
        ]);

        // Boş saatlar: istifadəçi tarixləri tez-tez dəyişir, amma skript ilə sorğu yağışı olmasın
        RateLimiter::for('slots', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Token səhifələri: 48 simvolluq tokeni təxmin etmək praktiki mümkün deyil, amma yenə də limit
        RateLimiter::for('booking-manage', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
