<?php

namespace Whilesmart\Workspaces\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Whilesmart\Workspaces\Enums\Role;
use Workbench\App\Models\User;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $email
 * @property int $invited_by_user_id
 * @property string $token
 * @property string $role
 * @property User|null $invitedBy
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 */
class WorkspaceInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'invited_by_user_id',
        'token',
        'expires_at',
        'accepted_at',
        'declined_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @deprecated Use Role::OWNER->value instead */
    public const ROLE_OWNER = 'owner';

    /** @deprecated Use Role::ADMIN->value instead */
    public const ROLE_ADMIN = 'admin';

    /** @deprecated Use Role::MEMBER->value instead */
    public const ROLE_MEMBER = 'member';

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    protected static function booted(): void
    {
        static::creating(function (WorkspaceInvitation $invitation) {
            if (empty($invitation->token)) {
                // @phpstan-ignore-next-line
                $invitation->token = Str::random(64);
            }
            if (empty($invitation->expires_at)) {
                $days = config('workspaces.invitation_expiry_days', 7);
                // @phpstan-ignore-next-line
                $invitation->expires_at = now()->addDays($days);
            }
        });
    }

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
        // @phpstan-ignore-next-line
        return ! is_null($this->accepted_at);
    }

    public function isDeclined(): bool
    {
        // @phpstan-ignore-next-line
        return ! is_null($this->declined_at);
    }

    public function isPending(): bool
    {
        // @phpstan-ignore-next-line
        return is_null($this->accepted_at) && is_null($this->declined_at);
    }

    public function isExpired(): bool
    {
        // @phpstan-ignore-next-line
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }

    public function accept(): bool
    {
        if (! $this->isValid()) {
            return false;
        }
        // @phpstan-ignore-next-line
        $this->accepted_at = now();

        return $this->save();
    }

    public function decline(): bool
    {
        if (! $this->isPending()) {
            return false;
        }
        // @phpstan-ignore-next-line
        $this->declined_at = now();

        return $this->save();
    }

    protected function getUserModel(): string
    {
        return config('workspaces.user_model', 'App\\Models\\User');
    }
}
