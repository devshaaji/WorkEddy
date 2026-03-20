<?php

declare(strict_types=1);

namespace WorkEddy\Helpers;

use RuntimeException;

final class WorkerContract
{
    /** @var array<string,array<string,mixed>> */
    private static array $cache = [];

    /**
     * @return array<string,mixed>
     */
    public static function video(): array
    {
        return self::load('video-worker');
    }

    /**
     * @return array<string,mixed>
     */
    public static function live(): array
    {
        return self::load('live-worker');
    }

    public static function videoQueueName(): string
    {
        return self::queueName(self::video());
    }

    public static function liveQueueName(): string
    {
        return self::queueName(self::live());
    }

    public static function liveFrameQueueName(): string
    {
        return self::queueName(self::live(), 'frame_queue_name');
    }

    public static function videoRoute(string $name): string
    {
        return self::route(self::video(), $name);
    }

    public static function liveRoute(string $name): string
    {
        return self::route(self::live(), $name);
    }

    /**
     * @return list<string>
     */
    public static function requiredFields(string $worker, string $payload): array
    {
        $contract = $worker === 'live' ? self::live() : self::video();
        $definition = self::payloadDefinition($contract, $payload);
        $required = $definition['required'] ?? [];

        if (!is_array($required)) {
            throw new RuntimeException("Worker contract payload [$payload] is missing required fields");
        }

        return array_values(array_map(static fn (mixed $value): string => (string) $value, $required));
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public static function videoJobPayload(array $values): array
    {
        return self::payload(self::video(), 'job', $values);
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public static function liveJobPayload(array $values): array
    {
        return self::payload(self::live(), 'job', $values);
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public static function liveFrameBatchPayload(array $values): array
    {
        return self::payload(self::live(), 'frame_batch', $values);
    }

    /**
     * @return array<string,mixed>
     */
    private static function load(string $name): array
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }

        $path = dirname(__DIR__, 2) . '/shared/worker-contracts/' . $name . '.json';
        if (!is_file($path)) {
            throw new RuntimeException("Worker contract [$name] not found");
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Worker contract [$name] is invalid JSON");
        }

        self::$cache[$name] = $decoded;

        return $decoded;
    }

    /**
     * @param array<string,mixed> $contract
     */
    private static function queueName(array $contract, string $key = 'queue_name'): string
    {
        $name = trim((string) ($contract[$key] ?? ''));
        if ($name === '') {
            throw new RuntimeException("Worker contract [$key] is missing");
        }

        return $name;
    }

    /**
     * @param array<string,mixed> $contract
     */
    private static function route(array $contract, string $name): string
    {
        $routes = is_array($contract['routes'] ?? null) ? $contract['routes'] : [];
        $path = trim((string) ($routes[$name] ?? ''));
        if ($path === '') {
            throw new RuntimeException("Worker contract route [$name] is missing");
        }

        return $path;
    }

    /**
     * @param array<string,mixed> $contract
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private static function payload(array $contract, string $payload, array $values): array
    {
        $definition = self::payloadDefinition($contract, $payload);
        $required = is_array($definition['required'] ?? null)
            ? array_values(array_map(static fn (mixed $value): string => (string) $value, $definition['required']))
            : [];
        $result = [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $values)) {
                throw new RuntimeException("Worker payload [$payload] is missing field [$field]");
            }

            $result[$field] = $values[$field];
        }

        $optional = is_array($definition['optional'] ?? null) ? $definition['optional'] : [];

        foreach ($optional as $field) {
            $key = (string) $field;
            if (array_key_exists($key, $values)) {
                $result[$key] = $values[$key];
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $contract
     * @return array<string,mixed>
     */
    private static function payloadDefinition(array $contract, string $payload): array
    {
        $payloads = is_array($contract['payloads'] ?? null) ? $contract['payloads'] : [];
        $definition = is_array($payloads[$payload] ?? null) ? $payloads[$payload] : null;

        if ($definition === null) {
            throw new RuntimeException("Worker contract payload [$payload] is missing");
        }

        return $definition;
    }
}
