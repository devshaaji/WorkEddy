<?php

declare(strict_types=1);

namespace WorkEddy\Core;

use Predis\Client;

/**
 * Shared Redis connection factory.
 *
 * Creates a single {@see Client} instance from environment variables
 * and reuses it for the lifetime of the request.  Every component
 * that needs Redis (queue drivers, cache drivers, rate-limiter, …)
 * should receive this factory rather than building its own client.
 */
final class RedisConnectionFactory
{
    private ?Client $client = null;

    private readonly string $host;
    private readonly int    $port;

    public function __construct(?string $host = null, ?int $port = null)
    {
        $this->host = $host ?? (getenv('REDIS_HOST') ?: '127.0.0.1');
        $this->port = $port ?? (int) (getenv('REDIS_PORT') ?: 6379);
    }

    /**
     * Return the shared Predis client, creating it on first call.
     */
    public function connection(): Client
    {
        return $this->client ??= new Client([
            'scheme' => 'tcp',
            'host'   => $this->host,
            'port'   => $this->port,
        ]);
    }
}
