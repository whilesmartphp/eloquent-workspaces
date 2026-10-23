<?php

namespace Whilesmart\Workspaces\Guards;

use Illuminate\Contracts\Auth\Authenticatable;
use Whilesmart\Workspaces\Contracts\InvitationGuard;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;

class AllowAllInvitationGuard implements InvitationGuard
{
    public function beforeInvite(Workspace $workspace, string $email, string $role, ?Authenticatable $inviter): void {}

    public function beforeAccept(WorkspaceInvitation $invitation, Authenticatable $user): void {}
}
