<?php

declare(strict_types=1);

namespace WorkEddy\Services\Cache;

use WorkEddy\Contracts\CacheInterface;

/**
 * In-memory array cache driver.
 *
 * Data lives only for the current request / process lifetime.
 * Ideal for unit tests — no external dependencies, instant and
 * fully deterministic.
 */
final class ArrayCacheDriver implements CacheInterface
{
    /** @var array<string, array{value: mixed, expires_at: int}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->store[$key]['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $this->store[$key] = [
            'value'      => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
        ];

        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->store[$key])) {
            return false;
        }

        $expiresAt = $this->store[$key]['expires_at'];

        if ($expiresAt > 0 && $expiresAt < time()) {
            unset($this->store[$key]);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function flush(): bool
    {
        $this->store = [];
        return true;
    }
}
