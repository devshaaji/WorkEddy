<?php

declare(strict_types=1);

namespace WorkEddy\Contracts;

/**
 * Application-level cache contract.
 *
 * Implementations may store data in Redis, the filesystem, or a
 * plain PHP array (useful for tests).  Swapping drivers requires
 * only a config change — consuming code never touches the concrete.
 */
interface CacheInterface
{
    /**
     * Retrieve a value by key, returning $default when the key is missing.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value under the given key for $ttl seconds.
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool;

    /**
     * Check whether a key exists and has not expired.
     */
    public function has(string $key): bool;

    /**
     * Remove a single key from the cache.
     */
    public function delete(string $key): bool;

    /**
     * Remove all keys managed by this cache instance.
     */
    public function flush(): bool;
}
