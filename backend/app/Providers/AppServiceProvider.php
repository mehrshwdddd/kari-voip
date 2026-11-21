<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use App\Services\AI\Adapters\OpenAICompatibleAdapter;
use App\Services\AI\Contracts\AIAdapterInterface;
use App\Services\AI\IntentDetector;
use App\Services\Telephony\Contracts\TelephonyAdapterInterface;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelephonyManager::class);

        $this->app->bind(TelephonyAdapterInterface::class, function ($app) {
            return $app->make(TelephonyManager::class)->adapter();
        });

        $this->app->bind(AIAdapterInterface::class, function ($app) {
            return new OpenAICompatibleAdapter($app->make(IntentDetector::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super-admin bypass for Owner role
        Gate::before(function ($user, string $ability) {
            return $user->hasRole(RoleEnum::OWNER->value) ? true : null;
        });

        // Centralized Queue routing (Laravel 13)
        if (method_exists(Queue::getFacadeRoot(), 'route')) {
            Queue::route(ShouldBroadcast::class, queue: 'events');
        }

        // Rate Limiters
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });
    }
}
