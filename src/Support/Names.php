<?php

namespace Whilesmart\Workspaces\Support;

class Names
{
    /** A display name for a user, whether the host stores a single name or first and last names. */
    public static function of(mixed $user): ?string
    {
        if (! $user) {
            return null;
        }
        $name = trim((string) (data_get($user, 'name') ?: trim(data_get($user, 'first_name').' '.data_get($user, 'last_name'))));

        return $name !== '' ? $name : (data_get($user, 'username') ?: null);
    }

    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }
}
