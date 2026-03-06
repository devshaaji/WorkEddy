<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use Predis\Client;
use RuntimeException;

final class QueueService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'scheme' => 'tcp',
            'host'   => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port'   => (int) (getenv('REDIS_PORT') ?: 6379),
        ]);
    }

    public function enqueueScanJob(array $payload): void
    {
        $queue = (string) (getenv('WORKER_QUEUE') ?: 'scan_jobs');
        $this->client->lpush($queue, [json_encode($payload, JSON_THROW_ON_ERROR)]);
    }

    public function queueDepth(): int
    {
        $queue = (string) (getenv('WORKER_QUEUE') ?: 'scan_jobs');
        return (int) $this->client->llen($queue);
    }
}
