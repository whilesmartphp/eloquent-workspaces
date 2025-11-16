<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\Test;
use Whilesmart\Workspaces\Models\Workspace;
use Workbench\App\Models\User;

use function Orchestra\Testbench\workbench_path;

#[WithMigration]
class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_get_workspace_details()
    {
        $user = $this->createAuthenticatedUser();
        $workspace = $user->workspaces()->first();
        $this->overrideConfigs();

        $response = $this->actingAs($user)->getJson("/workspaces/{$workspace->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'type',
                    'is_active',
                    'created_at',
                ],
            ]);

        $responseData = $response->json('data');
        $this->assertEquals($workspace->id, $responseData['id']);
        $this->assertEquals($workspace->name, $responseData['name']);
    }

    private function createAuthenticatedUser(?array $data = null)
    {
        if (! $data) {
            $data = ['name' => 'Test User', 'email' => 'test@mail.com'];
        }

        $data['password'] = Hash::make('secret');

        $user = User::create($data);

        // Create a personal workspace for the user
        $workspace = Workspace::create([
            'name' => $user->name."'s Workspace",
            'description' => 'Personal workspace for '.$user->name,
            'type' => 'personal',
            'is_active' => true,
        ]);

        // Assign owner role
        if (class_exists('Whilesmart\\Roles\\Models\\Role')) {
            $ownerRole = \Whilesmart\Roles\Models\Role::firstOrCreate([
                'slug' => 'workspace-owner',
            ], [
                'name' => 'Workspace Owner',
                'description' => 'Full access to workspace',
                'level' => 100,
            ]);

            \Whilesmart\Roles\Models\RoleAssignment::create([
                'assignable_type' => get_class($user),
                'assignable_id' => $user->id,
                'role_id' => $ownerRole->id,
                'context_type' => Workspace::class,
                'context_id' => $workspace->id,
            ]);
        }

        return $user;
    }

    #[Test]
    public function user_can_update_workspace_they_own()
    {
        $user = $this->createAuthenticatedUser();
        $workspace = $user->workspaces()->first();

        $updateData = [
            'name' => 'Updated Workspace Name',
            'description' => 'Updated description',
        ];
        $this->overrideConfigs();

        $response = $this->actingAs($user)->putJson("/workspaces/{$workspace->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'success' => true,
                'name' => 'Updated Workspace Name',
                'description' => 'Updated description',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Updated Workspace Name',
            'description' => 'Updated description',
        ]);
    }

    #[Test]
    public function user_cannot_access_workspace_they_dont_belong_to()
    {
        $user1 = $this->createAuthenticatedUser(['name' => 'Test User', 'email' => 'user1@example.com']);
        $user2 = $this->createAuthenticatedUser(['name' => 'Test User', 'email' => 'user2@example.com']);

        $user1Workspace = $user1->workspaces()->first();

        $response = $this->actingAs($user2)->getJson("/workspaces/{$user1Workspace->id}");

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized',
            ]);
    }

    #[Test]
    public function user_can_get_workspace_members()
    {
        $user = $this->createAuthenticatedUser();
        $workspace = $user->workspaces()->first();
        $this->overrideConfigs();

        $response = $this->actingAs($user)->getJson("/workspaces/{$workspace->id}/members");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'user_id',
                        'email',
                        'first_name',
                        'last_name',
                        'role',
                        'joined_at',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        $this->assertCount(1, $responseData); // Owner should be listed
        $this->assertEquals($user->id, $responseData[0]['user_id']);
        $this->assertEquals('workspace-owner', $responseData[0]['role']);
    }

    #[Test]
    public function workspace_owner_can_invite_member()
    {
        $user = $this->createAuthenticatedUser();
        $workspace = $user->workspaces()->first();

        $inviteData = [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ];

        $response = $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/members/invite", $inviteData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invitation sent successfully',
            ]);

        // Verify invitation was created
        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'newmember@example.com',
            'role' => 'member',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function non_owner_cannot_invite_members()
    {
        $owner = $this->createAuthenticatedUser(['name' => 'Test User', 'email' => 'owner@example.com']);
        $member = $this->createAuthenticatedUser(['name' => 'Test User', 'email' => 'member@example.com']);
        $workspace = $owner->workspaces()->first();

        // Add member to workspace (but not as owner)
        if (class_exists('Whilesmart\\Roles\\Models\\Role')) {
            $memberRole = \Whilesmart\Roles\Models\Role::firstOrCreate([
                'slug' => 'workspace-member',
            ], [
                'name' => 'Workspace Member',
                'description' => 'Basic workspace access',
                'level' => 10,
            ]);

            \Whilesmart\Roles\Models\RoleAssignment::create([
                'assignable_type' => get_class($member),
                'assignable_id' => $member->id,
                'role_id' => $memberRole->id,
                'context_type' => Workspace::class,
                'context_id' => $workspace->id,
            ]);
        }

        $inviteData = [
            'email' => 'newmember@example.com',
            'role' => 'member',
        ];

        $response = $this->actingAs($member)->postJson("/workspaces/{$workspace->id}/members/invite", $inviteData);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized',
            ]);
    }

    #[Test]
    public function workspace_invitation_requires_valid_email()
    {
        $user = $this->createAuthenticatedUser();
        $workspace = $user->workspaces()->first();

        $inviteData = [
            'email' => 'invalid-email',
            'role' => 'member',
        ];

        $response = $this->actingAs($user)->postJson("/workspaces/{$workspace->id}/members/invite", $inviteData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function user_must_be_authenticated_to_access_workspaces()
    {
        $user = $this->createAuthenticatedUser();
        $workspace = $user->workspaces()->first();
        $this->overrideConfigs();

        // No authentication headers
        $response = $this->getJson("/workspaces/{$workspace->id}");

        $response->assertStatus(401);
    }

    private function overrideConfigs()
    {
        config(['eloquent-workspaces.user_model' => User::class]);
        //        config(['eloquent-workspaces.route_middleware' => []]);
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(
            workbench_path('database/migrations')
        );
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            'Whilesmart\Workspaces\WorkspacesServiceProvider',
        ];
    }
}
