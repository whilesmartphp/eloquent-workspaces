<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Roles\Models\Role;
use Whilesmart\Roles\Models\RoleAssignment;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;
use Workbench\App\Models\User;

class HasWorkspacesTraitTest extends TestCase
{
    private function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ], $attributes));
    }

    private function createWorkspaceWithOwner(User $user, array $workspaceData = []): Workspace
    {
        $workspace = Workspace::create(array_merge([
            'name' => 'Test Workspace',
            'type' => 'team',
            'owner_type' => User::class,
            'owner_id' => $user->id,
        ], $workspaceData));

        $ownerRole = Role::where('slug', 'owner')->first();

        RoleAssignment::create([
            'assignable_type' => User::class,
            'assignable_id' => $user->id,
            'role_id' => $ownerRole->id,
            'context_type' => Workspace::class,
            'context_id' => $workspace->id,
        ]);

        return $workspace;
    }

    #[Test]
    public function user_can_have_workspaces()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $userWorkspaces = $user->workspaces;

        $this->assertCount(1, $userWorkspaces);
        $this->assertEquals($workspace->id, $userWorkspaces->first()->id);
    }

    #[Test]
    public function user_can_own_workspaces()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $ownedWorkspaces = $user->ownedWorkspaces;

        $this->assertCount(1, $ownedWorkspaces);
        $this->assertEquals($workspace->id, $ownedWorkspaces->first()->id);
    }

    #[Test]
    public function user_can_join_workspace_with_role()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $member = $this->createUser(['email' => 'member@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member->joinWorkspace($workspace, 'member');

        $this->assertTrue($member->fresh()->workspaces->contains($workspace));
    }

    #[Test]
    public function user_can_leave_workspace()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $member = $this->createUser(['email' => 'member@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member->joinWorkspace($workspace, 'member');
        $this->assertTrue($member->fresh()->workspaces->contains($workspace));

        $member->leaveWorkspace($workspace);
        $this->assertFalse($member->fresh()->workspaces->contains($workspace));
    }

    #[Test]
    public function user_can_accept_invitation()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $invitee = $this->createUser(['email' => 'invitee@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'token' => WorkspaceInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $invitee->acceptInvitation($invitation);

        $this->assertTrue($invitee->fresh()->workspaces->contains($workspace));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    #[Test]
    public function user_can_have_multiple_workspaces()
    {
        $user = $this->createUser();
        $workspace1 = $this->createWorkspaceWithOwner($user, ['name' => 'Workspace 1']);
        $workspace2 = $this->createWorkspaceWithOwner($user, ['name' => 'Workspace 2']);

        $this->assertCount(2, $user->fresh()->workspaces);
    }

    #[Test]
    public function user_belongs_to_workspace_returns_true_for_member()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $member = $this->createUser(['email' => 'member@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member->joinWorkspace($workspace, 'member');

        $this->assertTrue($member->belongsToWorkspace($workspace));
    }

    #[Test]
    public function user_belongs_to_workspace_returns_false_for_non_member()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $nonMember = $this->createUser(['email' => 'nonmember@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $this->assertFalse($nonMember->belongsToWorkspace($workspace));
    }
}
