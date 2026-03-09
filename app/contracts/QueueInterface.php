<?php

declare(strict_types=1);

namespace WorkEddy\Contracts;

/**
 * Queue driver contract.
 *
 * All queue back-ends (Redis, database, SQS, …) implement this
 * interface so the rest of the application never couples to a
 * concrete transport.
 */
interface QueueInterface
{
    /**
     * Push a JSON-serialisable payload onto the named queue.
     */
    public function enqueue(string $queue, array $payload): void;

    /**
     * Pop the next payload from the named queue, or null when empty.
     */
    public function dequeue(string $queue): ?array;

    /**
     * Return the number of jobs currently waiting in the named queue.
     */
    public function size(string $queue): int;
}
