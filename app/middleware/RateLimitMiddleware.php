<?php

declare(strict_types=1);

namespace WorkEddy\Middleware;

use WorkEddy\Core\RedisConnectionFactory;
use WorkEddy\Helpers\Response;

final class RateLimitMiddleware
{
    private const DEFAULT_MAX_RPM = 120;

    public function __construct(
        private readonly RedisConnectionFactory $redis,
    ) {}

    public function handle(string $clientKey): void
    {
        try {
            $client  = $this->redis->connection();

            $key     = 'rate:' . $clientKey;
            $count   = (int) $client->incr($key);
            $maxRpm  = (int) (getenv('RATE_LIMIT_RPM') ?: self::DEFAULT_MAX_RPM);

            if ($count === 1) {
                $client->expire($key, 60);
            }

            if ($count > $maxRpm) {
                Response::error('Too many requests', 429);
            }
        } catch (\Throwable) {
            // If Redis is unavailable we let the request through rather than blocking it.
        }
    }
}