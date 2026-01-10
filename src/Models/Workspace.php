<?php

namespace Whilesmart\Workspaces\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    use HasFactory, Sluggable, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'owner_type',
        'owner_id',
        'is_personal',
        'is_active',
        'settings',
        'metadata',
    ];

    protected $casts = [
        'is_personal' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

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

    public function pendingInvitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
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

    public function isOwnedBy(Model $owner): bool
    {
        return $this->owner_type === get_class($owner)
            && $this->owner_id === $owner->getKey();
    }

    public function hasMember(Model $user): bool
    {
        return $this->members()
            ->where('users.id', $user->getKey())
            ->exists();
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, mixed $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;

        return $this;
    }
}
