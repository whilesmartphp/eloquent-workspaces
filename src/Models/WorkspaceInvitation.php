<?php

namespace Whilesmart\Workspaces\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Whilesmart\Workspaces\Enums\Role;

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

    /** The token for the invitation link, known only while the invitation is being created or refreshed. */
    public ?string $plainToken = null;

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public static function hashesTokens(): bool
    {
        return (bool) config('workspaces.invitations.hash_tokens', false);
    }

    public static function storedToken(string $token): string
    {
        return static::hashesTokens() ? hash('sha256', $token) : $token;
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token', static::storedToken($token))->first();
    }

    public static function urlFor(string $token): ?string
    {
        $template = config('workspaces.invitations.url');

        return $template ? str_replace('{token}', rawurlencode($token), $template) : null;
    }

    public function url(): ?string
    {
        return $this->plainToken ? static::urlFor($this->plainToken) : null;
    }

    /** Issue a new link and restart the expiry, invalidating the old link. */
    public function refreshToken(): string
    {
        $plain = static::generateToken();
        $this->forceFill([
            'token' => static::storedToken($plain),
            'expires_at' => now()->addDays((int) config('workspaces.invitation_expiry_days', 7)),
        ])->save();
        $this->plainToken = $plain;

        return $plain;
    }

    public function isFor(?string $email): bool
    {
        return $email !== null && mb_strtolower(trim($email)) === mb_strtolower(trim((string) $this->email));
    }

    protected function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : mb_strtolower(trim($value));
    }

    protected static function booted(): void
    {
        static::creating(function (WorkspaceInvitation $invitation) {
            if (empty($invitation->token)) {
                $invitation->plainToken = static::generateToken();
                $invitation->token = static::storedToken($invitation->plainToken);
            } elseif (! static::hashesTokens()) {
                $invitation->plainToken = $invitation->token;
            }
            if (empty($invitation->expires_at)) {
                $days = config('workspaces.invitation_expiry_days', 7);
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
        return ! is_null($this->accepted_at);
    }

    public function isDeclined(): bool
    {
        return ! is_null($this->declined_at);
    }

    public function isPending(): bool
    {
        return is_null($this->accepted_at) && is_null($this->declined_at);
    }

    public function isExpired(): bool
    {
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

        $this->accepted_at = now();

        return $this->save();
    }

    public function decline(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $this->declined_at = now();

        return $this->save();
    }

    protected function getUserModel(): string
    {
        return config('workspaces.user_model', 'App\\Models\\User');
    }
}
