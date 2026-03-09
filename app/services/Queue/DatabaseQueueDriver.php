<?php

declare(strict_types=1);

namespace WorkEddy\Services\Queue;

use Doctrine\DBAL\Connection;
use RuntimeException;
use WorkEddy\Contracts\QueueInterface;

/**
 * Database-backed queue driver using the `queue_jobs` table.
 *
 * Suitable for environments without Redis.  Uses SELECT … FOR UPDATE
 * to guarantee exclusive dequeue in a concurrent setting.
 */
final class DatabaseQueueDriver implements QueueInterface
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    public function enqueue(string $queue, array $payload): void
    {
        $this->db->executeStatement(
            'INSERT INTO queue_jobs (queue_name, payload, created_at)
             VALUES (:queue_name, :payload, NOW())',
            [
                'queue_name' => $queue,
                'payload'    => json_encode($payload, JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function dequeue(string $queue): ?array
    {
        return $this->db->transactional(function () use ($queue): ?array {
            $row = $this->db->fetchAssociative(
                'SELECT id, payload
                 FROM queue_jobs
                 WHERE queue_name = :queue_name
                 ORDER BY id ASC
                 LIMIT 1
                 FOR UPDATE',
                ['queue_name' => $queue],
            );

            if (!$row) {
                return null;
            }

            $this->db->executeStatement(
                'DELETE FROM queue_jobs WHERE id = :id',
                ['id' => (int) $row['id']],
            );

            return $this->decode((string) $row['payload']);
        });
    }

    public function size(string $queue): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM queue_jobs WHERE queue_name = :queue_name',
            ['queue_name' => $queue],
        );
    }

    private function decode(string $raw): array
    {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException('Queue payload must decode to a JSON object.');
        }

        return $decoded;
    }
}
