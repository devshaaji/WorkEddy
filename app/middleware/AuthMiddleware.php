<?php

declare(strict_types=1);

namespace WorkEddy\Middleware;

use WorkEddy\Helpers\Response;
use WorkEddy\Services\JwtService;

final class AuthMiddleware
{
    public function __construct(private readonly JwtService $jwt) {}

    /**
     * Parse and return JWT claims from the Authorization header.
     * Calls Response::error with 401 if the token is missing or invalid.
     */
    public function handle(): array
    {
        $headers     = getallheaders() ?: [];
        $authHeader  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token       = str_starts_with($authHeader, 'Bearer ') ? trim(substr($authHeader, 7)) : null;

        if ($token === null || $token === '') {
            Response::error('Unauthorized', 401);
        }

        try {
            return $this->jwt->parseToken($token);
        } catch (\Throwable) {
            Response::error('Unauthorized: invalid token', 401);
        }
    }
}