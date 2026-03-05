<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

final class JwtService
{
    public function issueToken(int $userId, int $organizationId, string $role): string
    {
        $secret = getenv('JWT_SECRET') ?: 'dev-secret';
        $ttl = (int) (getenv('JWT_TTL_SECONDS') ?: 3600);
        $now = time();

        return JWT::encode([
            'sub' => $userId,
            'org' => $organizationId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + $ttl,
        ], $secret, 'HS256');
    }

    public function parseToken(string $token): array
    {
        $secret = getenv('JWT_SECRET') ?: 'dev-secret';
        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Throwable $exception) {
            throw new RuntimeException('Invalid or expired token');
        }
    }
}
