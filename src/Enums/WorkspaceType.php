<?php

namespace Whilesmart\Workspaces\Enums;

enum WorkspaceType: string
{
    case PERSONAL = 'personal';
    case TEAM = 'team';
    case ORGANIZATION = 'organization';

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
        return self::TEAM;
    }

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'Personal',
            self::TEAM => 'Team',
            self::ORGANIZATION => 'Organization',
        };
    }

    public function isPersonal(): bool
    {
        return $this === self::PERSONAL;
    }

    public static function creatableTypes(): array
    {
        return array_filter(
            self::cases(),
            fn (self $type) => ! $type->isPersonal()
        );
    }

    public static function creatableValues(): array
    {
        return array_map(
            fn (self $type) => $type->value,
            self::creatableTypes()
        );
    }
}
