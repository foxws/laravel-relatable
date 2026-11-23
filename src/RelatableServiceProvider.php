<?php

declare(strict_types=1);

namespace Foxws\Relatable;

use Illuminate\Support\ServiceProvider;

class RelatableServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/relatable.php', 'relatable');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config()->boolean('relatable.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/relatable.php' => config_path('relatable.php'),
        ], ['relatable', 'relatable-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['relatable', 'relatable-migrations']);
    }
}
