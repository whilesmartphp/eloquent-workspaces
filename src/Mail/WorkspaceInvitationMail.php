<?php

namespace Whilesmart\Workspaces\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $workspaceName,
        public ?string $inviterName,
        public string $role,
        public string $url,
        public ?Carbon $expiresAt = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->inviterName ? $this->inviterName.' invited you' : 'You are invited').' to '.$this->workspaceName);
    }

    public function content(): Content
    {
        return new Content(markdown: 'workspaces::invitation', with: [
            'workspaceName' => $this->workspaceName,
            'inviterName' => $this->inviterName,
            'role' => $this->role,
            'url' => $this->url,
            'expiresAt' => $this->expiresAt,
        ]);
    }
}
