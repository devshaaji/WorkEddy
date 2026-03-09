<?php

declare(strict_types=1);

namespace WorkEddy\Services\Queue;

use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Core\RedisConnectionFactory;

/**
 * Redis-backed queue driver using Predis.
 *
 * Jobs are stored in a Redis list: {@code LPUSH} to enqueue,
 * {@code RPOP} to dequeue (FIFO order).
 */
final class RedisQueueDriver implements QueueInterface
{
    public function __construct(
        private readonly RedisConnectionFactory $redis,
    ) {}

    public function enqueue(string $queue, array $payload): void
    {
        $this->redis->connection()->lpush($queue, [
            json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }

    public function dequeue(string $queue): ?array
    {
        $raw = $this->redis->connection()->rpop($queue);

        if ($raw === null) {
            return null;
        }

        return $this->decode((string) $raw);
    }

    public function size(string $queue): int
    {
        return (int) $this->redis->connection()->llen($queue);
    }

    private function decode(string $raw): array
    {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Queue payload must decode to a JSON object.');
        }

        return $decoded;
    }
}
