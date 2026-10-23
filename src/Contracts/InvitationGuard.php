<?php

namespace Whilesmart\Workspaces\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;

/**
 * Lets the host refuse an invitation or an acceptance, for example when a
 * plan has no seats left. Throw an HttpException to refuse; the package
 * calls these inside a database transaction so row locks hold until the
 * invitation is saved.
 */
interface InvitationGuard
{
    public function beforeInvite(Workspace $workspace, string $email, string $role, ?Authenticatable $inviter): void;

    public function beforeAccept(WorkspaceInvitation $invitation, Authenticatable $user): void;
}
