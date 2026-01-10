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

class InvitationTest extends TestCase
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
    public function it_can_create_invitation()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'token' => WorkspaceInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
        ]);
    }

    #[Test]
    public function it_generates_unique_token()
    {
        $token1 = WorkspaceInvitation::generateToken();
        $token2 = WorkspaceInvitation::generateToken();

        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1));
    }

    #[Test]
    public function it_auto_generates_token_on_create()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertNotNull($invitation->token);
        $this->assertEquals(64, strlen($invitation->token));
    }

    #[Test]
    public function it_can_check_if_expired()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $validInvitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'valid@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);

        $expiredInvitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'expired@example.com',
            'role' => 'member',
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($validInvitation->isExpired());
        $this->assertTrue($expiredInvitation->isExpired());
    }

    #[Test]
    public function it_can_check_if_pending()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $pendingInvitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'pending@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);

        $acceptedInvitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'accepted@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);

        $this->assertTrue($pendingInvitation->isPending());
        $this->assertFalse($acceptedInvitation->isPending());
    }

    #[Test]
    public function it_can_be_accepted()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->accept();

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    #[Test]
    public function it_can_be_declined()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->decline();

        $this->assertNotNull($invitation->fresh()->declined_at);
    }

    #[Test]
    public function it_belongs_to_workspace()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertEquals($workspace->id, $invitation->workspace->id);
    }

    #[Test]
    public function workspace_has_many_invitations()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invite1@example.com',
            'role' => 'member',
            'expires_at' => now()->addDays(7),
        ]);

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invite2@example.com',
            'role' => 'admin',
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertCount(2, $workspace->invitations);
    }
}
