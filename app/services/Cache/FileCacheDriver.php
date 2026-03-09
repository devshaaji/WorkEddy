<?php

declare(strict_types=1);

namespace WorkEddy\Services\Cache;

use WorkEddy\Contracts\CacheInterface;

/**
 * Filesystem-backed cache driver.
 *
 * Each key is stored as a JSON file containing the value and its
 * expiry timestamp.  Works everywhere — no external services needed.
 */
final class FileCacheDriver implements CacheInterface
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim(
            $directory ?? (getenv('CACHE_FILE_PATH') ?: __DIR__ . '/../../../storage/cache'),
            DIRECTORY_SEPARATOR,
        );

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return $default;
        }

        $data = $this->readEntry($path);

        if ($data === null) {
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $entry = json_encode([
            'value'      => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
        ], JSON_THROW_ON_ERROR);

        return file_put_contents($this->path($key), $entry, LOCK_EX) !== false;
    }

    public function has(string $key): bool
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return false;
        }

        return $this->readEntry($path) !== null;
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    public function flush(): bool
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.cache');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            unlink($file);
        }

        return true;
    }

    // ──────────────────────────────────────────────────────────────────

    private function path(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
    }

    /**
     * Read and validate a cache file entry.  Returns null when expired.
     */
    private function readEntry(string $path): ?array
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || !array_key_exists('value', $data)) {
            return null;
        }

        $expiresAt = (int) ($data['expires_at'] ?? 0);

        if ($expiresAt > 0 && $expiresAt < time()) {
            unlink($path); // auto-clean expired entries
            return null;
        }

        return $data;
    }
}
