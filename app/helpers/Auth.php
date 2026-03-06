<?php

declare(strict_types=1);

namespace WorkEddy\Helpers;

use RuntimeException;

final class Auth
{
    /**
     * Throw 401 if the request is not authenticated.
     */
    public static function requireClaims(array $claims): void
    {
        if (empty($claims)) {
            throw new RuntimeException('Unauthorized');
        }
    }

    /**
     * Throw 403 if the authenticated user does not have one of the allowed roles.
     *
     * @param string[] $roles
     */
    public static function requireRoles(array $claims, array $roles): void
    {
        self::requireClaims($claims);
        if (!in_array($claims['role'] ?? '', $roles, true)) {
            throw new RuntimeException('Forbidden: insufficient role');
        }
    }

    /**
     * Return the organisation id from parsed JWT claims.
     */
    public static function orgId(array $claims): int
    {
        return (int) ($claims['org'] ?? 0);
    }

    /**
     * Return the user id (subject) from parsed JWT claims.
     */
    public static function userId(array $claims): int
    {
        return (int) ($claims['sub'] ?? 0);
    }
}