<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Roles\Models\Role;
use Whilesmart\Roles\Models\RoleAssignment;
use Whilesmart\Workspaces\Models\Workspace;
use Workbench\App\Models\User;

class SubWorkspace extends Workspace
{
    protected $table = 'workspaces';
}

class RenamedWorkspace extends Workspace
{
    protected $table = 'workspaces';

    public function getMorphClass(): string
    {
        return Workspace::class;
    }
}

class SubclassedWorkspaceTest extends TestCase
{
    #[Test]
    public function members_resolve_when_the_workspace_model_is_subclassed(): void
    {
        config(['workspaces.workspace_model' => SubWorkspace::class]);

        $user = User::create([
            'name' => 'Sub Owner',
            'email' => 'sub-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = SubWorkspace::create([
            'name' => 'Sub Workspace',
            'type' => 'team',
            'owner_type' => User::class,
            'owner_id' => $user->id,
        ]);

        RoleAssignment::create([
            'assignable_type' => User::class,
            'assignable_id' => $user->id,
            'role_id' => Role::where('slug', 'owner')->first()->id,
            'context_type' => SubWorkspace::class,
            'context_id' => $workspace->id,
        ]);

        // Before the fix this returned 0, because the lookup filtered on the
        // base Workspace class while the assignment stores the subclass.
        $this->assertCount(1, $workspace->members()->get());
    }

    #[Test]
    public function members_resolve_when_a_subclass_keeps_the_name_already_stored(): void
    {
        config(['workspaces.workspace_model' => RenamedWorkspace::class]);

        $user = User::create([
            'name' => 'Renamed Owner',
            'email' => 'renamed-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = RenamedWorkspace::create([
            'name' => 'Renamed Workspace',
            'type' => 'team',
            'owner_type' => User::class,
            'owner_id' => $user->id,
        ]);

        RoleAssignment::create([
            'assignable_type' => User::class,
            'assignable_id' => $user->id,
            'role_id' => Role::where('slug', 'owner')->first()->id,
            'context_type' => Workspace::class,
            'context_id' => $workspace->id,
        ]);

        $this->assertCount(1, $workspace->members()->get());
        $this->assertContains($workspace->id, $user->workspaces()->pluck('workspaces.id')->all());
    }
}
