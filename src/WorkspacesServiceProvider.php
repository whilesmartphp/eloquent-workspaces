<?php

namespace Whilesmart\Workspaces;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Whilesmart\Roles\Models\Role;
use Whilesmart\Workspaces\Console\WorkspaceSetupCommand;
use Whilesmart\Workspaces\Enums\Role as RoleEnum;
use Whilesmart\Workspaces\Exceptions\WorkspaceSetupException;
use Whilesmart\Workspaces\Models\Workspace;

class WorkspacesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workspaces.php', 'workspaces');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([WorkspaceSetupCommand::class]);
        }

        $this->publishes([
            __DIR__.'/../config/workspaces.php' => config_path('workspaces.php'),
        ], 'workspaces-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'workspaces-migrations');

        Route::model('workspace', Workspace::class);

        if (config('workspaces.register_routes', true)) {
            $this->registerRoutes();
        }

        $this->app->booted(function () {
            $this->validateRolesExist();
        });
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

    protected function validateRolesExist(): void
    {
        if ($this->app->runningInConsole() || $this->app->environment('testing')) {
            return;
        }

        if (! Schema::hasTable('roles')) {
            return;
        }

        $requiredRoles = array_map(fn ($role) => $role->value, RoleEnum::cases());
        $existingRoles = Role::whereIn('slug', $requiredRoles)->pluck('slug')->toArray();
        $missingRoles = array_diff($requiredRoles, $existingRoles);

        if (! empty($missingRoles)) {
            throw new WorkspaceSetupException(
                'Workspace roles not configured. Missing roles: '.implode(', ', $missingRoles).
                '. Run: php artisan workspace:setup'
            );
        }
    }
}
