<?php

namespace App\Providers;

use App\Services\Broker\BrokerClient;
use App\Services\Broker\FakeBroker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FakeBroker::class, fn () => new FakeBroker());
        $this->app->singleton(BrokerClient::class, fn ($app) => new BrokerClient($app->make(FakeBroker::class)));
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $max = (int) config('lacmp.login.max_attempts', 5);
            return Limit::perMinute($max)->by(strtolower((string) $request->input('email')) . '|' . $request->ip());
        });
    }
}
