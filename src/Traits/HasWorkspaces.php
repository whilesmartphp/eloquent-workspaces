<?php

namespace Whilesmart\Workspaces\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceMember;

trait HasWorkspaces
{
    /**
     * Get workspaces owned by this user
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /**
     * Get workspaces this user is a member of
     */
    public function workspaces()
    {
        return $this->hasManyThrough(
            Workspace::class,
            'Whilesmart\\Roles\\Models\\RoleAssignment',
            'assignable_id',
            'id',
            'id',
            'context_id'
        )->where('role_assignments.assignable_type', self::class)
            ->where('role_assignments.context_type', Workspace::class);
    }

    /**
     * Get workspace memberships for this user
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'user_id');
    }

    /**
     * Create a personal workspace for this user
     */
    public function createPersonalWorkspace(?string $name = null): Workspace
    {
        $workspace = $this->ownedWorkspaces()->create([
            'name' => $name ?? ($this->first_name."'s Workspace"),
        ]);

        // Add owner as member
        $workspace->members()->create([
            'user_id' => $this->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        return $workspace;
    }
}
