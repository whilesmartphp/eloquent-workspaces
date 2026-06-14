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
}
