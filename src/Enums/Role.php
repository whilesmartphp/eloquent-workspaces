<?php

namespace Whilesmart\Workspaces\Enums;

enum Role: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }

    public static function default(): self
    {
        return self::MEMBER;
    }

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::ADMIN => 'Administrator',
            self::MEMBER => 'Member',
        };
    }

    public function permissions(): array
    {
        return match ($this) {
            self::OWNER => [Permission::ALL->value],
            self::ADMIN => [
                Permission::MANAGE_MEMBERS->value,
                Permission::MANAGE_SETTINGS->value,
                Permission::MANAGE_INVITATIONS->value,
            ],
            self::MEMBER => [Permission::VIEW->value],
        };
    }

    public function canAccess(): bool
    {
        return true;
    }

    public function canManage(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN]);
    }

    public function isOwner(): bool
    {
        return $this === self::OWNER;
    }

    public function precedence(): int
    {
        return match ($this) {
            self::OWNER => 3,
            self::ADMIN => 2,
            self::MEMBER => 1,
        };
    }

    public static function byPrecedence(): array
    {
        $roles = self::cases();
        usort($roles, fn ($role1, $role2) => $role2->precedence() <=> $role1->precedence());

        return $roles;
    }
}
