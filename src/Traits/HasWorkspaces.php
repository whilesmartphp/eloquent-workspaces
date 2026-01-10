<?php

namespace Whilesmart\Workspaces\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Whilesmart\Workspaces\Models\Workspace;
use Whilesmart\Workspaces\Models\WorkspaceInvitation;

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
                $this->name ?? trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
                $this->email ?? '',
            ],
            $template
        );

        $workspace = $this->ownedWorkspaces()->create([
            'name' => $name ?? $defaultName,
            'type' => 'personal',
            'is_personal' => true,
        ]);

        $this->joinWorkspace($workspace, 'workspace-owner');

        return $workspace;
    }

    public function createWorkspace(string $name, array $attributes = []): Workspace
    {
        $workspace = $this->ownedWorkspaces()->create(array_merge([
            'name' => $name,
            'type' => 'team',
            'is_personal' => false,
        ], $attributes));

        $this->joinWorkspace($workspace, 'workspace-owner');

        return $workspace;
    }

    public function joinWorkspace(Workspace $workspace, string $role = 'workspace-member'): void
    {
        if (method_exists($this, 'assignRole')) {
            $this->assignRole($role, Workspace::class, $workspace->id);
        }
    }

    public function leaveWorkspace(Workspace $workspace): void
    {
        if (method_exists($this, 'removeRole')) {
            $roles = ['workspace-owner', 'workspace-admin', 'workspace-member'];
            foreach ($roles as $role) {
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
                'workspace-'.$invitation->role
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
}
