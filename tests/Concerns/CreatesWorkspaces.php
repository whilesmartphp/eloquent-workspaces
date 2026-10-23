<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Hash;
use Whilesmart\Roles\Models\Role;
use Whilesmart\Roles\Models\RoleAssignment;
use Whilesmart\Workspaces\Models\Workspace;
use Workbench\App\Models\User;

trait CreatesWorkspaces
{
    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ], $attributes));
    }

    protected function createWorkspaceWithOwner(User $user): Workspace
    {
        $workspace = Workspace::create(['name' => 'Studio', 'type' => 'team', 'owner_type' => User::class, 'owner_id' => $user->id]);
        RoleAssignment::create([
            'assignable_type' => User::class,
            'assignable_id' => $user->id,
            'role_id' => Role::where('slug', 'owner')->first()->id,
            'context_type' => Workspace::class,
            'context_id' => $workspace->id,
        ]);

        return $workspace;
    }
}
