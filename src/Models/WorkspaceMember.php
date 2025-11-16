<?php

namespace Whilesmart\Workspaces\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const ROLE_OWNER = 'owner';

    const ROLE_ADMIN = 'admin';

    const ROLE_MEMBER = 'member';

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->getUserModel(), 'user_id');
    }

    protected function getUserModel(): string
    {
        return config('workspaces.user_model', 'App\\Models\\User');
    }
}
