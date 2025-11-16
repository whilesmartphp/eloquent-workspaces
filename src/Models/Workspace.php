<?php

namespace Whilesmart\Workspaces\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function members()
    {
        return $this->hasManyThrough(
            config('workspaces.user_model', 'App\\Models\\User'),
            'Whilesmart\\Roles\\Models\\RoleAssignment',
            'context_id',
            'id',
            'id',
            'assignable_id'
        )->where('role_assignments.context_type', self::class)
            ->where('role_assignments.assignable_type', config('workspaces.user_model', 'App\\Models\\User'));
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function roleAssignments()
    {
        return $this->hasMany('Whilesmart\\Roles\\Models\\RoleAssignment', 'context_id')
            ->where('context_type', self::class);
    }

    public function getOwnersAttribute()
    {
        return $this->members()
            ->whereHas('roleAssignments', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('slug', 'workspace-owner');
                })
                    ->where('context_type', self::class)
                    ->where('context_id', $this->id);
            })
            ->get();
    }

    public function getAdminsAttribute()
    {
        return $this->members()
            ->whereHas('roleAssignments', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->whereIn('slug', ['workspace-owner', 'workspace-admin']);
                })
                    ->where('context_type', self::class)
                    ->where('context_id', $this->id);
            })
            ->get();
    }
}
