<?php

namespace Whilesmart\Workspaces\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'invited_by_user_id',
        'accepted_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    const ROLE_ADMIN = 'admin';

    const ROLE_MEMBER = 'member';

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel(), 'invited_by_user_id');
    }

    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }

    public function isPending(): bool
    {
        return is_null($this->accepted_at);
    }

    protected function getUserModel(): string
    {
        return config('eloquent-workspaces.user_model', 'App\\Models\\User');
    }
}
