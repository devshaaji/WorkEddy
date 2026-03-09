<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\BillingPeriodService;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\ScanService;
use WorkEddy\Services\UsageMeterService;

final class ScanBillingFlowTest extends TestCase
{
    public function testVideoQueueFailureMarksScanInvalidAndReleasesReservation(): void
    {
        $executedSql = [];
        $conn = $this->createMock(Connection::class);

        $conn->method('transactional')
            ->willReturnCallback(fn (callable $cb) => $cb());

        $conn->method('lastInsertId')->willReturn('501');

        $conn->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$executedSql) {
                $executedSql[] = $sql;

                if (str_contains($sql, 'UPDATE scans')) {
                    return 1;
                }

                return 1;
            });

        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $params = []) {
                if (str_contains($sql, 'FROM tasks')) {
                    return [
                        'id' => 1,
                        'organization_id' => 10,
                        'name' => 'Task A',
                        'description' => null,
                        'workstation' => null,
                        'department' => null,
                        'created_at' => '2026-03-08 00:00:00',
                    ];
                }

                if (str_contains($sql, 'FROM subscriptions s')) {
                    return [
                        'subscription_id' => 1,
                        'id' => 2,
                        'name' => 'professional',
                        'scan_limit' => 100,
                        'price' => 299.0,
                        'billing_cycle' => 'monthly',
                        'start_date' => '2026-03-01',
                        'end_date' => null,
                        'status' => 'active',
                    ];
                }

                if (str_contains($sql, 'FROM usage_records')) {
                    return ['used' => 0];
                }

                if (str_contains($sql, 'FROM usage_reservations')) {
                    return ['reserved' => 0];
                }

                return false;
            });

        $queue = new class implements QueueInterface {
            public function enqueue(string $queue, array $payload): void
            {
                throw new RuntimeException('queue_down');
            }

            public function dequeue(string $queue): ?array
            {
                return null;
            }

            public function size(string $queue): int
            {
                return 0;
            }
        };

        $service = new ScanService(
            new ScanRepository($conn),
            new TaskRepository($conn),
            new AssessmentEngine(),
            new UsageMeterService(new WorkspaceRepository($conn), new BillingPeriodService()),
            $queue,
            null,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to queue video scan for processing');

        try {
            $service->createVideoScan(10, 3, 1, 'reba', '/storage/uploads/videos/sample.mp4');
        } finally {
            $joined = implode("\n", $executedSql);
            $this->assertStringContainsString('INSERT INTO usage_reservations', $joined);
            $this->assertStringContainsString('UPDATE scans', $joined);
            $this->assertStringContainsString('DELETE FROM usage_reservations', $joined);
        }
    }

    public function testCompleteVideoProcessingUsesIdempotentUsageUpsert(): void
    {
        $executedSql = [];
        $conn = $this->createMock(Connection::class);

        $conn->method('transactional')
            ->willReturnCallback(fn (callable $cb) => $cb());

        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $params = []) {
                if (str_contains($sql, 'FROM scans') && str_contains($sql, 'scan_type')) {
                    return [
                        'id' => 77,
                        'organization_id' => 10,
                        'scan_type' => 'video',
                        'model' => 'reba',
                        'status' => 'processing',
                    ];
                }

                return false;
            });

        $conn->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$executedSql) {
                $executedSql[] = $sql;
                return 1;
            });

        $repo = new ScanRepository($conn);
        $repo->completeVideoProcessing(
            10,
            77,
            'reba',
            [
                'raw_score' => 6,
                'normalized_score' => 40,
                'risk_category' => 'moderate',
                'score' => 6,
                'risk_level' => 'Medium',
                'recommendation' => 'Review posture',
                'algorithm_version' => 'reba_official_v1',
            ],
            [
                'trunk_angle' => 25,
            ],
        );

        $joined = implode("\n", $executedSql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE id = id', $joined);
        $this->assertStringContainsString('DELETE FROM usage_reservations', $joined);
    }
}
