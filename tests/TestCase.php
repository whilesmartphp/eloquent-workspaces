<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as BaseTestCase;

use function Orchestra\Testbench\workbench_path;

#[WithMigration]
class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(workbench_path('database/migrations'));
    }

    protected function seedRoles(): void
    {
        if (class_exists('Whilesmart\\Roles\\Models\\Role')) {
            \Whilesmart\Roles\Models\Role::firstOrCreate([
                'slug' => 'owner',
            ], [
                'name' => 'Owner',
                'description' => 'Full access to workspace',
                'level' => 100,
            ]);

            \Whilesmart\Roles\Models\Role::firstOrCreate([
                'slug' => 'admin',
            ], [
                'name' => 'Admin',
                'description' => 'Admin access to workspace',
                'level' => 50,
            ]);

            \Whilesmart\Roles\Models\Role::firstOrCreate([
                'slug' => 'member',
            ], [
                'name' => 'Member',
                'description' => 'Basic workspace access',
                'level' => 10,
            ]);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Cviebrock\EloquentSluggable\ServiceProvider::class,
            'Whilesmart\Roles\RolesServiceProvider',
            'Whilesmart\Workspaces\WorkspacesServiceProvider',
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('workspaces.user_model', \Workbench\App\Models\User::class);
        $app['config']->set('workspaces.route_prefix', '');
        $app['config']->set('workspaces.route_middleware', ['api']);
    }
}
