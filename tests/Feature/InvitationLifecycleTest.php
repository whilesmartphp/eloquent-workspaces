<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;
use Whilesmart\Workspaces\Contracts\InvitationGuard;
use Whilesmart\Workspaces\Events\MemberInvited;
use Whilesmart\Workspaces\Events\MemberRoleChanged;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;
use Workbench\App\Models\User;

class InvitationLifecycleTest extends TestCase
{
    use CreatesWorkspaces;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('workspaces.invitations.url', 'https://app.example/invitations/{token}');
    }

    private function invite(User $as, Workspace $workspace, string $email, string $role = 'member'): string
    {
        $url = $this->actingAs($as)->postJson("/workspaces/{$workspace->slug}/members/invite", ['email' => $email, 'role' => $role])
            ->assertCreated()->json('data.url');

        return basename($url);
    }

    private function join(User $owner, Workspace $workspace, string $email, string $role = 'member'): User
    {
        $token = $this->invite($owner, $workspace, $email, $role);
        $user = $this->createUser(['email' => $email]);
        $this->actingAs($user)->postJson("/workspaces/invitations/{$token}/accept")->assertOk();

        return $user;
    }

    #[Test]
    public function no_email_is_sent_unless_enabled(): void
    {
        Mail::fake();
        $owner = $this->createUser();
        $this->invite($owner, $this->createWorkspaceWithOwner($owner), 'new@example.com');

        Mail::assertNothingQueued();
    }

    #[Test]
    public function hashed_tokens_keep_the_plain_token_out_of_the_database(): void
    {
        config(['workspaces.invitations.hash_tokens' => true]);
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);
        $token = $this->invite($owner, $workspace, 'new@example.com');

        $this->assertDatabaseMissing('workspace_invitations', ['token' => $token]);
        $this->assertDatabaseHas('workspace_invitations', ['token' => hash('sha256', $token)]);
        $this->getJson("/workspaces/invitations/{$token}")->assertOk()->assertJsonPath('data.status', 'pending');

        $invitee = $this->createUser(['email' => 'new@example.com']);
        $this->actingAs($invitee)->postJson('/workspaces/invitations/'.hash('sha256', $token).'/accept')->assertNotFound();
        $this->actingAs($invitee)->postJson("/workspaces/invitations/{$token}/accept")->assertOk();
        $this->assertTrue($invitee->fresh()->belongsToWorkspace($workspace));
    }

    #[Test]
    public function anyone_with_the_link_can_preview_it_without_signing_in(): void
    {
        $owner = $this->createUser(['name' => 'Ada Okafor']);
        $workspace = $this->createWorkspaceWithOwner($owner);
        $token = $this->invite($owner, $workspace, 'samuel@example.com', 'admin');
        $this->app['auth']->forgetGuards();

        $this->getJson("/workspaces/invitations/{$token}")->assertOk()
            ->assertJsonPath('data.workspace.name', 'Studio')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.invited_by', 'Ada Okafor')
            ->assertJsonPath('data.email', 'sa****@example.com')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.addressed_to_viewer', null);
        $this->getJson('/workspaces/invitations/'.str_repeat('x', 64))->assertNotFound();
    }

    #[Test]
    public function resending_issues_a_new_link_and_retires_the_old_one(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);
        $old = $this->invite($owner, $workspace, 'late@example.com');
        $invitation = WorkspaceInvitation::where('email', 'late@example.com')->first();
        Event::fake([MemberInvited::class]);

        $new = basename($this->actingAs($owner)->postJson("/workspaces/{$workspace->slug}/invitations/{$invitation->id}/resend")->assertOk()->json('data.url'));

        $this->assertNotSame($old, $new);
        Event::assertDispatched(MemberInvited::class, fn ($event) => $event->resent && $event->token === $new);
        $invitee = $this->createUser(['email' => 'late@example.com']);
        $this->actingAs($invitee)->postJson("/workspaces/invitations/{$old}/accept")->assertNotFound();
        $this->actingAs($invitee)->postJson("/workspaces/invitations/{$new}/accept")->assertOk();
        $this->actingAs($owner)->postJson("/workspaces/{$workspace->slug}/invitations/{$invitation->id}/resend")->assertUnprocessable();
    }

    #[Test]
    public function people_can_be_invited_again_after_declining_but_members_cannot(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);
        $token = $this->invite($owner, $workspace, 'maybe@example.com');
        $this->actingAs($owner)->postJson("/workspaces/{$workspace->slug}/members/invite", ['email' => 'maybe@example.com', 'role' => 'member'])->assertUnprocessable();
        $this->actingAs($this->createUser(['email' => 'maybe@example.com']))->postJson("/workspaces/invitations/{$token}/decline")->assertOk();

        $this->invite($owner, $workspace, 'maybe@example.com');
        $this->join($owner, $workspace, 'member@example.com');
        $this->actingAs($owner)->postJson("/workspaces/{$workspace->slug}/members/invite", ['email' => 'MEMBER@example.com', 'role' => 'member'])
            ->assertUnprocessable()->assertJsonPath('error', 'This email already belongs to the workspace');
    }

    #[Test]
    public function the_invited_email_matches_regardless_of_case(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);
        $token = $this->invite($owner, $workspace, 'Mixed.Case@Example.com');
        $invitee = $this->createUser(['email' => 'mixed.case@EXAMPLE.com']);

        $this->actingAs($invitee)->postJson("/workspaces/invitations/{$token}/accept")->assertOk();
        $this->assertTrue($invitee->fresh()->belongsToWorkspace($workspace));
    }

    #[Test]
    public function a_verified_email_can_be_required(): void
    {
        config(['workspaces.invitations.require_verified_email' => true]);
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);
        $token = $this->invite($owner, $workspace, 'new@example.com');
        $invitee = $this->createUser(['email' => 'new@example.com']);

        $this->actingAs($invitee)->postJson("/workspaces/invitations/{$token}/accept")->assertForbidden();
        $invitee->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($invitee)->postJson("/workspaces/invitations/{$token}/accept")->assertOk();
    }

    #[Test]
    public function managers_change_roles_but_not_the_owners(): void
    {
        Event::fake([MemberRoleChanged::class]);
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);
        $admin = $this->join($owner, $workspace, 'admin@example.com', 'admin');
        $member = $this->join($owner, $workspace, 'member@example.com');

        $this->actingAs($admin)->patchJson("/workspaces/{$workspace->slug}/members/{$member->id}", ['role' => 'admin'])
            ->assertOk()->assertJsonPath('data.role', 'admin');
        $this->assertTrue($member->fresh()->hasRole('admin', Workspace::class, $workspace->id));
        $this->assertFalse($member->fresh()->hasRole('member', Workspace::class, $workspace->id));
        Event::assertDispatched(MemberRoleChanged::class, fn ($event) => $event->member->is($member) && $event->from === 'member' && $event->to === 'admin');

        $this->actingAs($admin)->patchJson("/workspaces/{$workspace->slug}/members/{$owner->id}", ['role' => 'member'])->assertUnprocessable();
        $this->actingAs($admin)->patchJson("/workspaces/{$workspace->slug}/members/{$member->id}", ['role' => 'owner'])->assertUnprocessable();
        $this->actingAs($admin)->patchJson("/workspaces/{$workspace->slug}/members/{$this->createUser()->id}", ['role' => 'admin'])->assertNotFound();
        $this->actingAs($admin)->patchJson("/workspaces/{$workspace->slug}/members/{$member->id}", ['role' => 'member'])->assertOk();
        $this->actingAs($member)->patchJson("/workspaces/{$workspace->slug}/members/{$admin->id}", ['role' => 'member'])->assertForbidden();

        $members = $this->actingAs($owner)->getJson("/workspaces/{$workspace->slug}/members")->assertOk()->json('data');
        $this->assertSame(['owner', 'admin', 'member'], collect($members)->sortBy('user_id')->pluck('role')->all());
    }

    #[Test]
    public function owners_and_admins_are_found_by_their_stored_role_names(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);
        $admin = $this->join($owner, $workspace, 'admin@example.com', 'admin');
        $this->join($owner, $workspace, 'member@example.com');

        $this->assertSame([$owner->id], $workspace->owners->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$owner->id, $admin->id], $workspace->admins->pluck('id')->unique()->all());
    }

    #[Test]
    public function the_host_can_refuse_an_invitation_or_an_acceptance(): void
    {
        $guard = new class implements InvitationGuard
        {
            public bool $full = false;

            public function beforeInvite(Workspace $workspace, string $email, string $role, ?Authenticatable $inviter): void
            {
                if ($email === 'blocked@example.com') {
                    throw new HttpException(402, 'No seats left.');
                }
            }

            public function beforeAccept(WorkspaceInvitation $invitation, Authenticatable $user): void
            {
                if ($this->full) {
                    throw new HttpException(402, 'No seats left.');
                }
            }
        };
        $this->app->instance(InvitationGuard::class, $guard);
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $this->actingAs($owner)->postJson("/workspaces/{$workspace->slug}/members/invite", ['email' => 'blocked@example.com', 'role' => 'member'])->assertStatus(402);
        $this->assertDatabaseMissing('workspace_invitations', ['email' => 'blocked@example.com']);

        $token = $this->invite($owner, $workspace, 'new@example.com');
        $guard->full = true;
        $invitee = $this->createUser(['email' => 'new@example.com']);
        $this->actingAs($invitee)->postJson("/workspaces/invitations/{$token}/accept")->assertStatus(402);
        $this->assertFalse($invitee->fresh()->belongsToWorkspace($workspace));
        $this->assertNull(WorkspaceInvitation::where('email', 'new@example.com')->first()->accepted_at);
    }
}
