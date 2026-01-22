<?php

namespace Whilesmart\Workspaces\Enums;

enum Permission: string
{
    case ALL = '*';
    case VIEW = 'view';
    case MANAGE_MEMBERS = 'manage_members';
    case MANAGE_SETTINGS = 'manage_settings';
    case MANAGE_INVITATIONS = 'manage_invitations';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
