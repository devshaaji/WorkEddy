<?php

declare(strict_types=1);

namespace WorkEddy\Helpers;

final class InternalRequestAuth
{
    public static function requireWorkerToken(): void
    {
        $expected = trim((string) (getenv('WORKER_API_TOKEN') ?: ''));
        if ($expected === '') {
            Response::error('Worker API token is not configured', 500);
        }

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $provided = $headers['X-Worker-Token']
            ?? $headers['x-worker-token']
            ?? ($_SERVER['HTTP_X_WORKER_TOKEN'] ?? '');
        $provided = is_string($provided) ? trim($provided) : '';

        if ($provided === '' || !hash_equals($expected, $provided)) {
            Response::error('Unauthorized', 401);
        }
    }
}