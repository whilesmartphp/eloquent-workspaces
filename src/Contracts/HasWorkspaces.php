<?php

namespace Whilesmart\Workspaces\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface HasWorkspaces
{
    /**
     * Get workspaces owned by this user
     */
    public function ownedWorkspaces(): HasMany;

    /**
     * Get workspaces this user is a member of
     */
    public function workspaces(): BelongsToMany;

    /**
     * Get workspace memberships for this user
     */
    public function workspaceMemberships(): HasMany;
}
