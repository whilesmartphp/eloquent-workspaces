<?php

namespace Whilesmart\Workspaces;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WorkspacesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workspaces.php', 'workspaces');
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/workspaces.php' => config_path('workspaces.php'),
        ], 'workspaces-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'workspaces-migrations');

        // Register routes if enabled
        if (config('workspaces.register_routes', true)) {
            $this->registerRoutes();
        }
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('workspaces.route_prefix', 'api'),
            'middleware' => config('workspaces.route_middleware', []),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/workspaces.php');
        });
    }
}
