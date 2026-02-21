<?php

namespace Whilesmart\Workspaces\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Whilesmart\Workspaces\Enums\Role;
use Whilesmart\Workspaces\Enums\WorkspaceType;
use Whilesmart\Workspaces\Events\WorkspaceSwitched;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;

// @phpstan-ignore-next-line
trait HasWorkspaces
{
    public function ownedWorkspaces(): MorphMany
    {
        return $this->morphMany(Workspace::class, 'owner');
    }

    public function workspaces()
    {
        return $this->hasManyThrough(
            Workspace::class,
            'Whilesmart\\Roles\\Models\\RoleAssignment',
            'assignable_id',
            'id',
            'id',
            'context_id'
        )->where('role_assignments.assignable_type', static::class)
            ->where('role_assignments.context_type', Workspace::class);
    }

    public function pendingWorkspaceInvitations()
    {
        return WorkspaceInvitation::where('email', $this->email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function createPersonalWorkspace(?string $name = null): Workspace
    {
        $template = config('workspaces.personal_workspace_name_template', "{name}'s Workspace");
        $defaultName = str_replace(
            ['{name}', '{first_name}', '{last_name}', '{full_name}', '{email}'],
            [
                $this->name ?? $this->first_name ?? 'My',
                $this->first_name ?? $this->name ?? 'My',
                $this->last_name ?? '',
                $this->name ?? trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')),
                $this->email ?? '',
            ],
            $template
        );

        $workspace = $this->ownedWorkspaces()->create([
            'name' => $name ?? $defaultName,
            'type' => WorkspaceType::PERSONAL->value,
            'is_personal' => true,
        ]);

        $this->joinWorkspace($workspace, Role::OWNER->value);

        return $workspace;
    }

    public function createWorkspace(string $name, array $attributes = []): Workspace
    {
        $workspace = $this->ownedWorkspaces()->create(array_merge([
            'name' => $name,
            'type' => WorkspaceType::default()->value,
            'is_personal' => false,
        ], $attributes));

        $this->joinWorkspace($workspace, Role::OWNER->value);

        return $workspace;
    }

    public function joinWorkspace(Workspace $workspace, ?string $role = null): void
    {
        $role = $role ?? Role::default()->value;

        if (method_exists($this, 'assignRole')) {
            $this->assignRole($role, Workspace::class, $workspace->id);
        }
    }

    public function leaveWorkspace(Workspace $workspace): void
    {
        if (method_exists($this, 'removeRole')) {
            foreach (Role::values() as $role) {
                $this->removeRole($role, Workspace::class, $workspace->id);
            }
        }
    }

    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->exists();
    }

    public function ownsWorkspace(Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($this);
    }

    public function acceptInvitation(WorkspaceInvitation $invitation): bool
    {
        if (! $invitation->isValid()) {
            return false;
        }

        if ($invitation->email !== $this->email) {
            return false;
        }

        if ($invitation->accept()) {
            $this->joinWorkspace(
                $invitation->workspace,
                $invitation->role
            );

            return true;
        }

        return false;
    }

    public function declineInvitation(WorkspaceInvitation $invitation): bool
    {
        if ($invitation->email !== $this->email) {
            return false;
        }

        return $invitation->decline();
    }

    /**
     * Switch to a workspace.
     *
     * This method dispatches a WorkspaceSwitched event and optionally persists
     * the current workspace if the model implements setCurrentWorkspaceId().
     *
     * Apps can handle persistence by either:
     * - Implementing setCurrentWorkspaceId($id) on the model
     * - Listening to the WorkspaceSwitched event
     * - Ignoring this entirely and managing context at the app level
     */
    public function switchWorkspace(Workspace $workspace): bool
    {
        if (! $this->belongsToWorkspace($workspace)) {
            return false;
        }

        $previousWorkspace = $this->currentWorkspace();

        if (method_exists($this, 'setCurrentWorkspaceId')) {
            $this->setCurrentWorkspaceId($workspace->id);
        }

        WorkspaceSwitched::dispatch($workspace, $this, $previousWorkspace);

        return true;
    }

    /**
     * Get the current workspace.
     *
     * Returns null if no current workspace is set or if the model doesn't
     * implement getCurrentWorkspaceId(). Apps can override this method
     * or implement getCurrentWorkspaceId() to customize behavior.
     */
    public function currentWorkspace(): ?Workspace
    {
        if (! method_exists($this, 'getCurrentWorkspaceId')) {
            return null;
        }

        $workspaceId = $this->getCurrentWorkspaceId();

        if (! $workspaceId) {
            return null;
        }

        return $this->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->first();
    }

    /**
     * Get the current workspace or fall back to the first available workspace.
     */
    public function currentOrDefaultWorkspace(): ?Workspace
    {
        return $this->currentWorkspace() ?? $this->workspaces()->first();
    }
}
