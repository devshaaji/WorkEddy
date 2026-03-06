<?php

declare(strict_types=1);

namespace WorkEddy\Middleware;

use Predis\Client;
use WorkEddy\Helpers\Response;

final class RateLimitMiddleware
{
    private const DEFAULT_MAX_RPM = 120;

    public function handle(string $clientKey): void
    {
        try {
            $redis  = new Client([
                'scheme' => 'tcp',
                'host'   => getenv('REDIS_HOST') ?: '127.0.0.1',
                'port'   => (int) (getenv('REDIS_PORT') ?: 6379),
            ]);

            $key     = 'rate:' . $clientKey;
            $count   = (int) $redis->incr($key);
            $maxRpm  = (int) (getenv('RATE_LIMIT_RPM') ?: self::DEFAULT_MAX_RPM);

            if ($count === 1) {
                $redis->expire($key, 60);
            }

            if ($count > $maxRpm) {
                Response::error('Too many requests', 429);
            }
        } catch (\Throwable) {
            // If Redis is unavailable we let the request through rather than blocking it.
        }
    }
}