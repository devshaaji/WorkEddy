<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WorkEddy\Contracts\CacheInterface;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Repositories\LiveSessionRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\LiveSessionService;

/**
 * Unit tests for LiveSessionService.
 *
 * Uses Connection mocking (same approach as ScanBillingFlowTest).
 */
final class LiveSessionServiceTest extends TestCase
{
    private function defaultConfig(): array
    {
        return [
            'pose_engine'              => 'yolo26',
            'scoring_model'            => 'reba',
            'target_fps'               => 5.0,
            'batch_window_ms'          => 500,
            'max_e2e_latency_ms'       => 2000,
            'worker_poll_interval_seconds' => 1.0,
            'yolo_model_variant'       => 'yolo26n-pose',
            'mediapipe_model_variant'  => 'pose_landmarker_lite',
            'max_concurrent_sessions'  => 4,
            'max_concurrent_sessions_per_org' => 10,
            'worker_count'             => 1,
            'session_timeout_seconds'  => 300,
            'queue_name'               => 'live_session_jobs',
        ];
    }

    private function noopCache(): CacheInterface
    {
        return new class implements CacheInterface {
            public function get(string $key, mixed $default = null): mixed { return $default; }
            public function set(string $key, mixed $value, int $ttl = 3600): bool { return true; }
            public function has(string $key): bool { return false; }
            public function delete(string $key): bool { return true; }
            public function flush(): bool { return true; }
        };
    }

    private function capturingQueue(): object
    {
        return new class implements QueueInterface {
            public array $enqueued = [];
            public function enqueue(string $queue, array $payload): void {
                $this->enqueued[] = ['queue' => $queue, 'payload' => $payload];
            }
            public function dequeue(string $queue): ?array { return null; }
            public function size(string $queue): int { return 0; }
        };
    }

    /**
     * Build a service with a mock Connection that returns canned data.
     */
    private function makeService(
        ?Connection $conn = null,
        ?QueueInterface $queue = null,
        ?array $config = null,
    ): LiveSessionService {
        $conn   = $conn ?? $this->createMock(Connection::class);
        $queue  = $queue ?? $this->capturingQueue();
        $config = $config ?? $this->defaultConfig();

        return new LiveSessionService(
            new LiveSessionRepository($conn),
            new TaskRepository($conn),
            new WorkspaceRepository($conn),
            new AssessmentEngine(),
            $queue,
            $this->noopCache(),
            $config,
        );
    }

    // ── getEngineConfig ───────────────────────────────────────────────

    public function testGetEngineConfigReturnsDefaultsAndBothEngines(): void
    {
        $svc    = $this->makeService();
        $result = $svc->getEngineConfig();

        self::assertSame('yolo26', $result['default_engine']);
        self::assertSame('reba', $result['scoring_model']);

        $engines = array_column($result['available_engines'], 'id');
        self::assertContains('mediapipe', $engines);
        self::assertContains('yolo26', $engines);

        self::assertSame(5.0,  $result['latency_defaults']['target_fps']);
        self::assertSame(500,  $result['latency_defaults']['batch_window_ms']);
        self::assertSame(2000, $result['latency_defaults']['max_e2e_latency_ms']);
    }

    public function testGetEngineConfigRespectsConfigOverrides(): void
    {
        $config = $this->defaultConfig();
        $config['pose_engine']        = 'mediapipe';
        $config['target_fps']         = 10.0;
        $config['max_e2e_latency_ms'] = 1000;

        $svc    = $this->makeService(config: $config);
        $result = $svc->getEngineConfig();

        self::assertSame('mediapipe', $result['default_engine']);
        self::assertSame(10.0, $result['latency_defaults']['target_fps']);
        self::assertSame(1000, $result['latency_defaults']['max_e2e_latency_ms']);
    }

    // ── startSession — validation ─────────────────────────────────────

    public function testStartSessionValidatesEngineChoice(): void
    {
        $conn = $this->createMock(Connection::class);
        // TaskRepo will need to return a task first
        $conn->method('fetchAssociative')
            ->willReturn([
                'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                'description' => null, 'workstation' => null, 'department' => null,
                'created_at' => '2026-03-12 00:00:00',
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Invalid pose engine");

        $svc = $this->makeService(conn: $conn);
        $svc->startSession(10, 1, 5, 'invalid_engine');
    }

    public function testStartSessionValidatesModelChoice(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturn([
                'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                'description' => null, 'workstation' => null, 'department' => null,
                'created_at' => '2026-03-12 00:00:00',
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Invalid scoring model");

        $svc = $this->makeService(conn: $conn);
        $svc->startSession(10, 1, 5, 'yolo26', 'niosh');
    }

    public function testStartSessionEnqueuesJobWithYolo26(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'FROM tasks')) {
                    return [
                        'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                        'description' => null, 'workstation' => null, 'department' => null,
                        'created_at' => '2026-03-12 00:00:00',
                    ];
                }
                // findById after create
                if (str_contains($sql, 'FROM live_sessions')) {
                    return [
                        'id' => 42, 'organization_id' => 10, 'status' => 'active',
                        'pose_engine' => 'yolo26', 'model' => 'reba',
                    ];
                }
                return false;
            });
        $conn->method('insert')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('42');

        $queue = $this->capturingQueue();

        $svc    = $this->makeService(conn: $conn, queue: $queue);
        $result = $svc->startSession(10, 1, 5);

        self::assertSame(42, $result['id']);
        self::assertSame('yolo26', $result['pose_engine']);
        self::assertCount(1, $queue->enqueued);
        self::assertSame('live_session_jobs', $queue->enqueued[0]['queue']);
        self::assertSame(42, $queue->enqueued[0]['payload']['session_id']);
        self::assertSame('yolo26', $queue->enqueued[0]['payload']['pose_engine']);
        self::assertSame(5.0, $queue->enqueued[0]['payload']['target_fps']);
    }

    public function testStartSessionAllowsMediaPipeSelection(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'FROM tasks')) {
                    return [
                        'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                        'description' => null, 'workstation' => null, 'department' => null,
                        'created_at' => '2026-03-12 00:00:00',
                    ];
                }
                if (str_contains($sql, 'FROM live_sessions')) {
                    return [
                        'id' => 43, 'organization_id' => 10, 'status' => 'active',
                        'pose_engine' => 'mediapipe', 'model' => 'reba',
                    ];
                }
                return false;
            });
        $conn->method('insert')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('43');

        $queue = $this->capturingQueue();

        $svc    = $this->makeService(conn: $conn, queue: $queue);
        $result = $svc->startSession(10, 1, 5, 'mediapipe');

        self::assertSame('mediapipe', $result['pose_engine']);
        self::assertSame('mediapipe', $queue->enqueued[0]['payload']['pose_engine']);
    }

    public function testStartSessionRejectsMediapipeWhenMultiPersonEnabled(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'COUNT(*) AS cnt')) {
                    return ['cnt' => 0];
                }

                return [
                    'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                    'description' => null, 'workstation' => null, 'department' => null,
                    'created_at' => '2026-03-12 00:00:00',
                ];
            });

        $config = $this->defaultConfig();
        $config['multi_person_mode'] = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support multi-person detection');

        $svc = $this->makeService(conn: $conn, config: $config);
        $svc->startSession(10, 1, 5, 'mediapipe');
    }

    public function testStartSessionRejectsWhenConcurrentLimitReached(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'COUNT(*) AS cnt')) {
                    return ['cnt' => 4];
                }

                return [
                    'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                    'description' => null, 'workstation' => null, 'department' => null,
                    'created_at' => '2026-03-12 00:00:00',
                ];
            });

        $config = $this->defaultConfig();
        $config['max_concurrent_sessions'] = 4;
        $config['max_concurrent_sessions_per_org'] = 100;
        $config['worker_count'] = 1;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Concurrent live session limit reached');

        $svc = $this->makeService(conn: $conn, config: $config);
        $svc->startSession(10, 1, 5, 'yolo26');
    }

    public function testStartSessionScalesCapacityWithWorkerCount(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'COUNT(*) AS cnt')) {
                    return ['cnt' => 7];
                }
                if (str_contains($sql, 'FROM tasks')) {
                    return [
                        'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                        'description' => null, 'workstation' => null, 'department' => null,
                        'created_at' => '2026-03-12 00:00:00',
                    ];
                }
                if (str_contains($sql, 'FROM live_sessions')) {
                    return [
                        'id' => 55, 'organization_id' => 10, 'status' => 'active',
                        'pose_engine' => 'yolo26', 'model' => 'reba',
                    ];
                }

                return false;
            });
        $conn->method('insert')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('55');

        $config = $this->defaultConfig();
        $config['max_concurrent_sessions'] = 4;
        $config['max_concurrent_sessions_per_org'] = 100;
        $config['worker_count'] = 2;

        $queue = $this->capturingQueue();

        $svc = $this->makeService(conn: $conn, queue: $queue, config: $config);
        $result = $svc->startSession(10, 1, 5);

        self::assertSame(55, $result['id']);
        self::assertCount(1, $queue->enqueued);
    }

    public function testStartSessionUsesPlanTierCapAndRejectsStarterWhenAtLimit(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'organization_id = :org_id') && str_contains($sql, 'COUNT(*) AS cnt')) {
                    return ['cnt' => 1];
                }
                if (str_contains($sql, 'FROM subscriptions s')) {
                    return [
                        'subscription_id' => 1,
                        'id' => 2,
                        'name' => 'starter',
                        'scan_limit' => 100,
                        'price' => 0.0,
                        'billing_cycle' => 'monthly',
                        'start_date' => '2026-03-01',
                        'end_date' => null,
                        'status' => 'active',
                    ];
                }
                if (str_contains($sql, 'COUNT(*) AS cnt')) {
                    return ['cnt' => 1];
                }

                return [
                    'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                    'description' => null, 'workstation' => null, 'department' => null,
                    'created_at' => '2026-03-12 00:00:00',
                ];
            });

        $config = $this->defaultConfig();
        $config['plan_concurrency_limits'] = ['starter' => 1, 'professional' => 4, 'enterprise' => 12];
        $config['max_concurrent_sessions_per_org'] = 10;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Organization live session limit reached');

        $svc = $this->makeService(conn: $conn, config: $config);
        $svc->startSession(10, 1, 5, 'yolo26');
    }

    public function testStartSessionUsesPlanTierCapAndAllowsProfessionalUnderLimit(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'organization_id = :org_id') && str_contains($sql, 'COUNT(*) AS cnt')) {
                    return ['cnt' => 2];
                }
                if (str_contains($sql, 'FROM subscriptions s')) {
                    return [
                        'subscription_id' => 1,
                        'id' => 3,
                        'name' => 'professional',
                        'scan_limit' => 500,
                        'price' => 199.0,
                        'billing_cycle' => 'monthly',
                        'start_date' => '2026-03-01',
                        'end_date' => null,
                        'status' => 'active',
                    ];
                }
                if (str_contains($sql, 'FROM tasks')) {
                    return [
                        'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                        'description' => null, 'workstation' => null, 'department' => null,
                        'created_at' => '2026-03-12 00:00:00',
                    ];
                }
                if (str_contains($sql, 'FROM live_sessions')) {
                    return [
                        'id' => 77, 'organization_id' => 10, 'status' => 'active',
                        'pose_engine' => 'yolo26', 'model' => 'reba',
                    ];
                }
                if (str_contains($sql, 'COUNT(*) AS cnt')) {
                    return ['cnt' => 2];
                }

                return false;
            });
        $conn->method('insert')->willReturn(1);
        $conn->method('lastInsertId')->willReturn('77');

        $queue = $this->capturingQueue();
        $config = $this->defaultConfig();
        $config['plan_concurrency_limits'] = ['starter' => 1, 'professional' => 4, 'enterprise' => 12];
        $config['max_concurrent_sessions_per_org'] = 1;

        $svc = $this->makeService(conn: $conn, queue: $queue, config: $config);
        $result = $svc->startSession(10, 1, 5, 'yolo26');

        self::assertSame(77, $result['id']);
        self::assertCount(1, $queue->enqueued);
    }

    public function testStartSessionRejectsWhenOrganizationConcurrentLimitReached(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql) {
                if (str_contains($sql, 'organization_id = :org_id')) {
                    return ['cnt' => 2];
                }
                if (str_contains($sql, 'COUNT(*) AS cnt')) {
                    return ['cnt' => 2];
                }

                return [
                    'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                    'description' => null, 'workstation' => null, 'department' => null,
                    'created_at' => '2026-03-12 00:00:00',
                ];
            });

        $config = $this->defaultConfig();
        $config['max_concurrent_sessions_per_org'] = 2;
        $config['max_concurrent_sessions'] = 100;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Organization live session limit reached');

        $svc = $this->makeService(conn: $conn, config: $config);
        $svc->startSession(10, 1, 5, 'yolo26');
    }

    // ── stopSession ───────────────────────────────────────────────────

    public function testStopSessionRejectsCompletedSession(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturn([
                'id' => 1, 'organization_id' => 10, 'status' => 'completed',
                'pose_engine' => 'yolo26',
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session is not active or paused');

        $svc = $this->makeService(conn: $conn);
        $svc->stopSession(10, 1);
    }

    // ── recordFrameBatch ──────────────────────────────────────────────

    public function testRecordFrameBatchRejectsInactiveSession(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturn([
                'id' => 1, 'organization_id' => 10, 'status' => 'completed',
                'pose_engine' => 'yolo26',
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session is not active');

        $svc = $this->makeService(conn: $conn);
        $svc->recordFrameBatch(1, 10, 'reba', []);
    }

    public function testRecordFrameBatchReturnsStats(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturn([
                'id' => 1, 'organization_id' => 10, 'status' => 'active',
                'pose_engine' => 'yolo26',
            ]);
        $conn->method('insert')->willReturn(1);
        $conn->method('executeStatement')->willReturn(1);

        $svc    = $this->makeService(conn: $conn);
        $result = $svc->recordFrameBatch(1, 10, 'reba', [
            ['frame_number' => 1, 'metrics' => ['trunk_angle' => 15.0], 'latency_ms' => 45.3],
            ['frame_number' => 2, 'metrics' => ['trunk_angle' => 18.0], 'latency_ms' => 42.1],
        ]);

        self::assertSame(2, $result['recorded']);
        self::assertEqualsWithDelta(43.7, $result['avg_latency_ms'], 0.01);
    }
}
