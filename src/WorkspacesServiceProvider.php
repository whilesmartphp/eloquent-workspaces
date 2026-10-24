<?php

namespace Whilesmart\Workspaces;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Whilesmart\Roles\Models\Role;
use Whilesmart\Workspaces\Console\WorkspaceSetupCommand;
use Whilesmart\Workspaces\Contracts\InvitationGuard;
use Whilesmart\Workspaces\Enums\Role as RoleEnum;
use Whilesmart\Workspaces\Events\MemberInvited;
use Whilesmart\Workspaces\Exceptions\WorkspaceSetupException;
use Whilesmart\Workspaces\Guards\AllowAllInvitationGuard;
use Whilesmart\Workspaces\Http\Controllers\WorkspaceController;
use Whilesmart\Workspaces\Listeners\SendInvitationMail;

class WorkspacesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workspaces.php', 'workspaces');
        $this->app->bindIf(InvitationGuard::class, AllowAllInvitationGuard::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'workspaces');

        if (config('workspaces.invitations.mail.enabled', false)) {
            Event::listen(MemberInvited::class, SendInvitationMail::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([WorkspaceSetupCommand::class]);
        }

        $this->publishes([
            __DIR__.'/../config/workspaces.php' => config_path('workspaces.php'),
        ], 'workspaces-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'workspaces-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/workspaces'),
        ], 'workspaces-views');

        Route::model('workspace', config('workspaces.workspace_model', \Whilesmart\Workspaces\Models\Workspace::class));

        if (config('workspaces.register_routes', true)) {
            $this->registerRoutes();
        }

        $this->app->booted(function () {
            $this->validateRolesExist();
        });
    }

    protected function registerRoutes(): void
    {
        $middleware = config('workspaces.route_middleware', []);

        if (! in_array(\Illuminate\Routing\Middleware\SubstituteBindings::class, $middleware)
            && ! in_array('bindings', $middleware)
            && ! in_array('api', $middleware)) {
            $middleware[] = \Illuminate\Routing\Middleware\SubstituteBindings::class;
        }

        Route::group([
            'prefix' => config('workspaces.route_prefix', 'api'),
            'middleware' => $middleware,
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/workspaces.php');
        });

        Route::group([
            'prefix' => config('workspaces.route_prefix', 'api'),
            'middleware' => config('workspaces.public_route_middleware', ['api']),
        ], function () {
            Route::get('/workspaces/invitations/{token}', [config('workspaces.controller', WorkspaceController::class), 'previewInvitation']);
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
