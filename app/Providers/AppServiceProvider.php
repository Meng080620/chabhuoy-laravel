<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * The OrderPlaced -> DecrementProductStock binding is wired automatically
     * by Laravel's event auto-discovery (listeners in app/Listeners typed on
     * their event), so no manual Event::listen call is needed here.
     */
    public function boot(): void
    {
        // Throttle login attempts per email + IP. Keying on both means a
        // single attacker hammering one IP can't lock a victim out, and a
        // distributed attack still can't grind a single account.
        RateLimiter::for('login', function (Request $request) {
            $key = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });
    }
}
