<?php

namespace App\Providers;

use App\Services\Auth\GatewayUserResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
     */
    public function boot(): void
    {
        RateLimiter::for('checkout', function (Request $request): Limit {
            $userId = app(GatewayUserResolver::class)->rawUserId($request);
            $eventId = $request->route('event');

            if (is_object($eventId) && method_exists($eventId, 'getKey')) {
                $eventId = $eventId->getKey();
            }

            return Limit::perMinute(20)->by(
                implode(':', [
                    'checkout',
                    $userId ?: 'guest',
                    $eventId ?: 'unknown-event',
                    $request->ip(),
                ])
            );
        });
    }
}
