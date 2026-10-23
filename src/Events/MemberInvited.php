<?php

namespace Whilesmart\Workspaces\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;

class MemberInvited
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * $token is the plain token from the link, which is not stored when
     * tokens are hashed. $resent is true when an invitation was sent again.
     */
    public function __construct(
        public Workspace $workspace,
        public WorkspaceInvitation $invitation,
        public ?string $token = null,
        public bool $resent = false,
    ) {}

    public function url(): ?string
    {
        return $this->token ? WorkspaceInvitation::urlFor($this->token) : null;
    }

    public function invitedBy(): mixed
    {
        return $this->invitation->invitedBy;
    }
}
