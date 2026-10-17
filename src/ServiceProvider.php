<?php

namespace SameOldNick\OpenObserve;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register the OpenObserve logger extension with Laravel's logging system
        Log::extend('openobserve', function ($app, array $config) {
            return $app->make(Factories\CreateOpenObserveLogger::class)->build($config);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\TestConnection::class,
            ]);
        }
    }
}
