<?php

declare(strict_types=1);

namespace WorkEddy\Services\Cache;

use WorkEddy\Contracts\CacheInterface;
use WorkEddy\Core\RedisConnectionFactory;

/**
 * Redis-backed cache driver.
 *
 * Values are JSON-encoded and stored with an optional TTL.
 * All keys are automatically prefixed to avoid collisions with
 * other Redis consumers (queue, rate-limiter, etc.).
 */
final class RedisCacheDriver implements CacheInterface
{
    private readonly string $prefix;

    public function __construct(
        private readonly RedisConnectionFactory $redis,
        string $prefix = 'cache:',
    ) {
        $this->prefix = $prefix;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->redis->connection()->get($this->prefix . $key);

        if ($raw === null) {
            return $default;
        }

        return json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        if ($ttl > 0) {
            $this->redis->connection()->setex($this->prefix . $key, $ttl, $encoded);
        } else {
            $this->redis->connection()->set($this->prefix . $key, $encoded);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->connection()->exists($this->prefix . $key);
    }

    public function delete(string $key): bool
    {
        return $this->redis->connection()->del([$this->prefix . $key]) > 0;
    }

    public function flush(): bool
    {
        $cursor = '0';
        $pattern = $this->prefix . '*';

        do {
            [$cursor, $keys] = $this->redis->connection()->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);

            if (!empty($keys)) {
                $this->redis->connection()->del($keys);
            }
        } while ($cursor !== '0');

        return true;
    }
}
