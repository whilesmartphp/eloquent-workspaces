<?php

namespace Whilesmart\Workspaces\Listeners;

use Illuminate\Support\Facades\Mail;
use Whilesmart\Workspaces\Events\MemberInvited;
use Whilesmart\Workspaces\Mail\WorkspaceInvitationMail;
use Whilesmart\Workspaces\Support\Names;

class SendInvitationMail
{
    public function handle(MemberInvited $event): void
    {
        $url = $event->url();
        if (! $url) {
            report(new \LogicException('Set workspaces.invitations.url to send invitation emails.'));

            return;
        }
        $class = config('workspaces.invitations.mail.mailable', WorkspaceInvitationMail::class);
        if (! is_a($class, WorkspaceInvitationMail::class, true)) {
            throw new \InvalidArgumentException('The invitation mailable must extend '.WorkspaceInvitationMail::class.'.');
        }
        $invitation = $event->invitation;

        Mail::to($invitation->email)->queue((new $class(
            $event->workspace->name,
            Names::of($invitation->invitedBy),
            $invitation->role,
            $url,
            $invitation->expires_at,
        ))->afterCommit());
    }
}
