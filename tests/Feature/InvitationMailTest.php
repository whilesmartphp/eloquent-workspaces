<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;
use Whilesmart\Workspaces\Mail\WorkspaceInvitationMail;

class InvitationMailTest extends TestCase
{
    use CreatesWorkspaces;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('workspaces.invitations.url', 'https://app.example/invitations/{token}');
        $app['config']->set('workspaces.invitations.mail.enabled', true);
    }

    #[Test]
    public function an_invitation_returns_its_link_and_is_emailed_when_enabled(): void
    {
        Mail::fake();
        $owner = $this->createUser(['name' => 'Ada Okafor', 'email' => 'ada@example.com']);
        $workspace = $this->createWorkspaceWithOwner($owner);

        $response = $this->actingAs($owner)->postJson("/workspaces/{$workspace->slug}/members/invite", ['email' => ' New@Example.com ', 'role' => 'admin'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new@example.com');
        $url = $response->json('data.url');

        $this->assertMatchesRegularExpression('#^https://app\.example/invitations/[A-Za-z0-9]{64}$#', $url);
        Mail::assertQueued(WorkspaceInvitationMail::class, function (WorkspaceInvitationMail $mail) use ($url) {
            $mail->assertSeeInHtml('Studio')->assertSeeInHtml($url);

            return $mail->hasTo('new@example.com') && $mail->inviterName === 'Ada Okafor' && $mail->role === 'admin';
        });
    }
}
