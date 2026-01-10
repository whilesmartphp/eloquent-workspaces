<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Workspaces\Models\Workspace;
use Workbench\App\Models\User;

class WorkspaceModelTest extends TestCase
{
    #[Test]
    public function it_can_create_a_workspace()
    {
        $workspace = Workspace::create([
            'name' => 'Test Workspace',
            'description' => 'A test workspace',
            'type' => 'team',
        ]);

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Test Workspace',
            'description' => 'A test workspace',
            'type' => 'team',
        ]);
    }

    #[Test]
    public function it_generates_slug_automatically()
    {
        $workspace = Workspace::create([
            'name' => 'My Team Workspace',
            'type' => 'team',
        ]);

        $this->assertEquals('my-team-workspace', $workspace->slug);
    }

    #[Test]
    public function it_generates_unique_slugs()
    {
        $workspace1 = Workspace::create([
            'name' => 'Team Alpha',
            'type' => 'team',
        ]);

        $workspace2 = Workspace::create([
            'name' => 'Team Alpha',
            'type' => 'team',
        ]);

        $this->assertEquals('team-alpha', $workspace1->slug);
        $this->assertNotEquals($workspace1->slug, $workspace2->slug);
        $this->assertStringStartsWith('team-alpha', $workspace2->slug);
    }

    #[Test]
    public function it_can_have_polymorphic_owner()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
        ]);

        $workspace = Workspace::create([
            'name' => 'John\'s Workspace',
            'type' => 'personal',
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'is_personal' => true,
        ]);

        $this->assertEquals($user->id, $workspace->owner->id);
        $this->assertEquals(User::class, $workspace->owner_type);
    }

    #[Test]
    public function it_can_store_settings_as_json()
    {
        $workspace = Workspace::create([
            'name' => 'Settings Test',
            'type' => 'team',
            'settings' => [
                'notifications' => true,
                'theme' => 'dark',
            ],
        ]);

        $this->assertEquals(true, $workspace->settings['notifications']);
        $this->assertEquals('dark', $workspace->settings['theme']);
    }

    #[Test]
    public function it_can_store_metadata_as_json()
    {
        $workspace = Workspace::create([
            'name' => 'Metadata Test',
            'type' => 'team',
            'metadata' => [
                'created_from' => 'api',
                'plan' => 'pro',
            ],
        ]);

        $this->assertEquals('api', $workspace->metadata['created_from']);
        $this->assertEquals('pro', $workspace->metadata['plan']);
    }

    #[Test]
    public function it_uses_slug_for_route_key()
    {
        $workspace = Workspace::create([
            'name' => 'Route Key Test',
            'type' => 'team',
        ]);

        $this->assertEquals('slug', $workspace->getRouteKeyName());
    }

    #[Test]
    public function it_can_be_soft_deleted()
    {
        $workspace = Workspace::create([
            'name' => 'Soft Delete Test',
            'type' => 'team',
        ]);

        $workspace->delete();

        $this->assertSoftDeleted('workspaces', [
            'name' => 'Soft Delete Test',
        ]);

        $this->assertNull(Workspace::find($workspace->id));
        $this->assertNotNull(Workspace::withTrashed()->find($workspace->id));
    }

    #[Test]
    public function it_can_be_marked_as_personal()
    {
        $workspace = Workspace::create([
            'name' => 'Personal Workspace',
            'type' => 'personal',
            'is_personal' => true,
        ]);

        $this->assertTrue($workspace->is_personal);
    }

    #[Test]
    public function it_is_active_by_default()
    {
        $workspace = Workspace::create([
            'name' => 'Active Test',
            'type' => 'team',
        ]);

        $this->assertTrue($workspace->is_active);
    }

    #[Test]
    public function it_can_be_deactivated()
    {
        $workspace = Workspace::create([
            'name' => 'Deactivate Test',
            'type' => 'team',
            'is_active' => false,
        ]);

        $this->assertFalse($workspace->is_active);
    }
}
