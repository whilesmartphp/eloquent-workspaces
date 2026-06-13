<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Roles\Models\Role;
use Whilesmart\Roles\Models\RoleAssignment;
use Whilesmart\Workspaces\Events\MemberInvited;
use Whilesmart\Workspaces\Events\MemberJoined;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;
use Workbench\App\Models\User;

class WorkspaceApiTest extends TestCase
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
    public function user_can_list_their_workspaces()
    {
        $user = $this->createUser();
        $workspace1 = $this->createWorkspaceWithOwner($user, ['name' => 'Workspace 1']);
        $workspace2 = $this->createWorkspaceWithOwner($user, ['name' => 'Workspace 2']);

        $response = $this->actingAs($user)->getJson('/workspaces');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'slug', 'type', 'role'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function workspace_list_includes_user_role()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $member = $this->createUser(['email' => 'member@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member->joinWorkspace($workspace, 'member');

        $ownerResponse = $this->actingAs($owner)->getJson('/workspaces');
        $ownerResponse->assertStatus(200);
        $this->assertEquals('owner', $ownerResponse->json('data.0.role'));

        $memberResponse = $this->actingAs($member)->getJson('/workspaces');
        $memberResponse->assertStatus(200);
        $this->assertEquals('member', $memberResponse->json('data.0.role'));
    }

    #[Test]
    public function user_can_create_workspace()
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/workspaces', [
            'name' => 'New Workspace',
            'description' => 'A new workspace',
            'type' => 'team',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'success' => true,
                'name' => 'New Workspace',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'name' => 'New Workspace',
            'type' => 'team',
        ]);
    }

    #[Test]
    public function user_can_get_workspace_details()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $response = $this->actingAs($user)->getJson("/workspaces/{$workspace->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'type',
                    'is_active',
                    'role',
                    'created_at',
                ],
            ]);

        $this->assertEquals($workspace->id, $response->json('data.id'));
        $this->assertEquals($workspace->name, $response->json('data.name'));
        $this->assertEquals($workspace->slug, $response->json('data.slug'));
    }

    #[Test]
    public function workspace_details_includes_user_role()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $member = $this->createUser(['email' => 'member@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member->joinWorkspace($workspace, 'member');

        $ownerResponse = $this->actingAs($owner)->getJson("/workspaces/{$workspace->slug}");
        $ownerResponse->assertStatus(200);
        $this->assertEquals('owner', $ownerResponse->json('data.role'));

        $memberResponse = $this->actingAs($member)->getJson("/workspaces/{$workspace->slug}");
        $memberResponse->assertStatus(200);
        $this->assertEquals('member', $memberResponse->json('data.role'));
    }

    #[Test]
    public function user_can_update_workspace_they_own()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $response = $this->actingAs($user)->putJson("/workspaces/{$workspace->slug}", [
            'name' => 'Updated Workspace',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
                'name' => 'Updated Workspace',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Updated Workspace',
        ]);
    }

    #[Test]
    public function user_can_delete_workspace_they_own()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $response = $this->actingAs($user)->deleteJson("/workspaces/{$workspace->slug}");

        $response->assertNoContent();

        $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
    }

    #[Test]
    public function user_cannot_access_workspace_they_dont_belong_to()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $otherUser = $this->createUser(['email' => 'other@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $response = $this->actingAs($otherUser)->getJson("/workspaces/{$workspace->slug}");

        $response->assertStatus(403);
    }

    #[Test]
    public function user_can_get_workspace_members()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $response = $this->actingAs($user)->getJson("/workspaces/{$workspace->slug}/members");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    #[Test]
    public function owner_can_invite_member()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $response = $this->actingAs($user)->postJson("/workspaces/{$workspace->slug}/members/invite", [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'newmember@example.com',
        ]);
    }

    #[Test]
    public function invitation_requires_valid_email()
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($user);

        $response = $this->actingAs($user)->postJson("/workspaces/{$workspace->slug}/members/invite", [
            'email' => 'invalid-email',
            'role' => 'member',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function owner_can_remove_member()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $member = $this->createUser(['email' => 'member@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member->joinWorkspace($workspace, 'member');

        $response = $this->actingAs($owner)->deleteJson("/workspaces/{$workspace->slug}/members/{$member->id}");

        $response->assertStatus(200);
        $this->assertFalse($member->fresh()->belongsToWorkspace($workspace));
    }

    #[Test]
    public function member_can_leave_workspace()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $member = $this->createUser(['email' => 'member@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member->joinWorkspace($workspace, 'member');

        $response = $this->actingAs($member)->postJson("/workspaces/{$workspace->slug}/leave");

        $response->assertStatus(200);
        $this->assertFalse($member->fresh()->belongsToWorkspace($workspace));
    }

    #[Test]
    public function unauthenticated_user_cannot_access_workspaces()
    {
        $response = $this->getJson('/workspaces');

        $response->assertStatus(401);
    }

    #[Test]
    public function workspace_creation_requires_name()
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson('/workspaces', [
            'type' => 'team',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    private function createInvitation(Workspace $workspace, string $email, array $attributes = []): WorkspaceInvitation
    {
        return WorkspaceInvitation::create(array_merge([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'role' => 'member',
        ], $attributes));
    }

    #[Test]
    public function invited_user_can_accept_invitation()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);
        $invitee = $this->createUser(['email' => 'invitee@example.com']);
        $invitation = $this->createInvitation($workspace, 'invitee@example.com');

        $response = $this->actingAs($invitee)->postJson("/workspaces/invitations/{$invitation->token}/accept");

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertTrue($invitee->fresh()->belongsToWorkspace($workspace));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    #[Test]
    public function user_cannot_accept_invitation_sent_to_another_email()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);
        $other = $this->createUser(['email' => 'other@example.com']);
        $invitation = $this->createInvitation($workspace, 'invitee@example.com');

        $response = $this->actingAs($other)->postJson("/workspaces/invitations/{$invitation->token}/accept");

        $response->assertStatus(403);
        $this->assertFalse($other->fresh()->belongsToWorkspace($workspace));
    }

    #[Test]
    public function expired_invitation_cannot_be_accepted()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);
        $invitee = $this->createUser(['email' => 'invitee@example.com']);
        $invitation = $this->createInvitation($workspace, 'invitee@example.com', ['expires_at' => now()->subDay()]);

        $response = $this->actingAs($invitee)->postJson("/workspaces/invitations/{$invitation->token}/accept");

        $response->assertStatus(422);
        $this->assertFalse($invitee->fresh()->belongsToWorkspace($workspace));
    }

    #[Test]
    public function already_accepted_invitation_cannot_be_accepted_again()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);
        $invitee = $this->createUser(['email' => 'invitee@example.com']);
        $invitation = $this->createInvitation($workspace, 'invitee@example.com', ['accepted_at' => now()]);

        $response = $this->actingAs($invitee)->postJson("/workspaces/invitations/{$invitation->token}/accept");

        $response->assertStatus(422);
    }

    #[Test]
    public function accepting_an_unknown_token_returns_not_found()
    {
        $invitee = $this->createUser(['email' => 'invitee@example.com']);

        $response = $this->actingAs($invitee)->postJson('/workspaces/invitations/does-not-exist/accept');

        $response->assertStatus(404);
    }

    #[Test]
    public function invited_user_can_decline_invitation()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);
        $invitee = $this->createUser(['email' => 'invitee@example.com']);
        $invitation = $this->createInvitation($workspace, 'invitee@example.com');

        $response = $this->actingAs($invitee)->postJson("/workspaces/invitations/{$invitation->token}/decline");

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNotNull($invitation->fresh()->declined_at);
        $this->assertFalse($invitee->fresh()->belongsToWorkspace($workspace));
    }

    #[Test]
    public function inviting_a_member_dispatches_member_invited_event()
    {
        Event::fake([MemberInvited::class]);

        $owner = $this->createUser(['email' => 'owner@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $this->actingAs($owner)->postJson("/workspaces/{$workspace->slug}/members/invite", [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ])->assertStatus(201);

        Event::assertDispatched(MemberInvited::class);
    }

    #[Test]
    public function accepting_an_invitation_dispatches_member_joined_event()
    {
        Event::fake([MemberJoined::class]);

        $owner = $this->createUser(['email' => 'owner@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);
        $invitee = $this->createUser(['email' => 'invitee@example.com']);
        $invitation = $this->createInvitation($workspace, 'invitee@example.com');

        $this->actingAs($invitee)->postJson("/workspaces/invitations/{$invitation->token}/accept")
            ->assertStatus(200);

        Event::assertDispatched(MemberJoined::class);
    }
}
