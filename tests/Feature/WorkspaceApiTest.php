<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Roles\Models\Role;
use Whilesmart\Roles\Models\RoleAssignment;
use Whilesmart\Workspaces\Models\Workspace;
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
                    '*' => ['id', 'name', 'slug', 'type'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
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
                    'created_at',
                ],
            ]);

        $this->assertEquals($workspace->id, $response->json('data.id'));
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
}
