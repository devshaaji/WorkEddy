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
use WorkEddy\Services\ImprovementProofService;
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
            new ImprovementProofService(),
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

    public function testCompleteVideoProcessingAutoDeletesRawVideoWhenOrgPolicyEnabled(): void
    {
        $executedSql = [];
        $conn = $this->createMock(Connection::class);

        $tmpFile = tempnam(sys_get_temp_dir(), 'we_video_');
        $this->assertNotFalse($tmpFile);
        $videoPath = $tmpFile . '.mp4';
        rename($tmpFile, $videoPath);
        file_put_contents($videoPath, 'video-bytes');

        $conn->method('transactional')
            ->willReturnCallback(fn (callable $cb) => $cb());

        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $params = []) use ($videoPath) {
                if (str_contains($sql, 'SELECT id, organization_id, scan_type, model, status, video_path')) {
                    return [
                        'id' => 778,
                        'organization_id' => 10,
                        'scan_type' => 'video',
                        'model' => 'reba',
                        'status' => 'processing',
                        'video_path' => $videoPath,
                    ];
                }

                if (str_contains($sql, 'SELECT s.*')) {
                    return [
                        'id' => 778,
                        'organization_id' => 10,
                        'user_id' => 3,
                        'task_id' => 1,
                        'scan_type' => 'video',
                        'model' => 'reba',
                        'raw_score' => 6.0,
                        'normalized_score' => 40.0,
                        'risk_category' => 'moderate',
                        'status' => 'completed',
                        'video_path' => null,
                        'error_message' => null,
                        'parent_scan_id' => null,
                        'created_at' => '2026-03-11 10:00:00',
                        'result_score' => 6.0,
                        'risk_level' => 'Medium',
                        'recommendation' => 'Review posture',
                        'algorithm_version' => 'reba_official_v1',
                    ];
                }

                if (str_contains($sql, 'SELECT * FROM scan_metrics')) {
                    return [
                        'scan_id' => 778,
                        'trunk_angle' => 25,
                        'neck_angle' => 10,
                        'upper_arm_angle' => 30,
                        'lower_arm_angle' => 80,
                        'wrist_angle' => 8,
                    ];
                }

                if (str_contains($sql, 'FROM organizations')) {
                    return [
                        'id' => 10,
                        'name' => 'Org 10',
                        'slug' => 'org-10',
                        'contact_email' => null,
                        'plan' => 'starter',
                        'status' => 'active',
                        'created_at' => '2026-03-01 00:00:00',
                        'updated_at' => null,
                        'settings' => json_encode(['auto_delete_video' => true]),
                    ];
                }

                return false;
            });

        $conn->method('fetchAllAssociative')->willReturn([]);

        $conn->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$executedSql) {
                $executedSql[] = $sql;
                return 1;
            });

        $service = new ScanService(
            new ScanRepository($conn),
            new TaskRepository($conn),
            new AssessmentEngine(),
            new UsageMeterService(new WorkspaceRepository($conn), new BillingPeriodService()),
            new class implements QueueInterface {
                public function enqueue(string $queue, array $payload): void {}
                public function dequeue(string $queue): ?array { return null; }
                public function size(string $queue): int { return 0; }
            },
            new ImprovementProofService(),
            null,
            300,
            new WorkspaceRepository($conn),
        );

        try {
            $service->completeVideoScanFromWorker(
                10,
                778,
                [
                    'trunk_angle' => 25,
                    'neck_angle' => 10,
                    'upper_arm_angle' => 30,
                    'lower_arm_angle' => 80,
                    'wrist_angle' => 8,
                    'leg_score' => 1,
                    'load_weight' => 7,
                ],
                'reba'
            );

            $this->assertFileDoesNotExist($videoPath);
            $joined = implode("\n", $executedSql);
            $this->assertStringContainsString('SET video_path = NULL', $joined);
        } finally {
            if (is_file($videoPath)) {
                @unlink($videoPath);
            }
        }
    }
}
