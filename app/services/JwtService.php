<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

final class JwtService
{
    public function issueToken(int $userId, int $organizationId, string $role, string $name = '', string $plan = ''): string
    {
        $secret = (string) (getenv('JWT_SECRET') ?: 'dev-secret-change-me');
        $ttl    = (int)    (getenv('JWT_TTL_SECONDS') ?: 3600);
        $now    = time();

        return JWT::encode([
            'sub'  => $userId,
            'org'  => $organizationId,
            'role' => $role,
            'name' => $name,
            'plan' => $plan,
            'iat'  => $now,
            'exp'  => $now + $ttl,
        ], $secret, 'HS256');
    }

    public function parseToken(string $token): array
    {
        $secret = (string) (getenv('JWT_SECRET') ?: 'dev-secret-change-me');
        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Throwable) {
            throw new RuntimeException('Unauthorized: invalid or expired token');
        }
    }
}
