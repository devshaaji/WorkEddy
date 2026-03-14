<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Repositories\CopilotAuditRepository;
use WorkEddy\Repositories\LiveSessionRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Repositories\UserRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\Cache\ArrayCacheDriver;
use WorkEddy\Services\BillingPeriodService;
use WorkEddy\Services\LiveSessionService;
use WorkEddy\Services\UsageMeterService;

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
        ?UsageMeterService $usageMeter = null,
        ?ArrayCacheDriver $cache = null,
    ): LiveSessionService {
        $conn   = $conn ?? $this->createMock(Connection::class);
        $queue  = $queue ?? $this->capturingQueue();
        $config = $config ?? $this->defaultConfig();
        $cache ??= new ArrayCacheDriver();

        return new LiveSessionService(
            new LiveSessionRepository($conn),
            new TaskRepository($conn),
            new WorkspaceRepository($conn),
            $queue,
            $config,
            $usageMeter,
            $cache,
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

    public function testStartSessionRejectsWhenBillingLiveSessionBudgetIsExhausted(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $params = []) {
                if (str_contains($sql, 'FROM tasks')) {
                    return [
                        'id' => 5, 'organization_id' => 10, 'name' => 'Task A',
                        'description' => null, 'workstation' => null, 'department' => null,
                        'created_at' => '2026-03-12 00:00:00',
                    ];
                }
                if (str_contains($sql, 'FROM subscriptions s')) {
                    return [
                        'subscription_id' => 1,
                        'id' => 2,
                        'name' => 'starter',
                        'scan_limit' => 10,
                        'price' => 0.0,
                        'billing_cycle' => 'monthly',
                        'billing_limits_json' => json_encode([
                            'video_scan_limit' => 10,
                            'live_session_limit' => 1,
                            'live_session_minutes_limit' => 120,
                            'llm_request_limit' => 10,
                            'llm_token_limit' => 100000,
                            'max_video_retention_days' => 30,
                            'max_org_members' => 5,
                            'max_live_concurrent_sessions' => 1,
                        ], JSON_UNESCAPED_UNICODE),
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
                if (str_contains($sql, 'FROM live_sessions') && str_contains($sql, 'started_at >= :period_start')) {
                    return ['cnt' => 1];
                }
                if (str_contains($sql, 'seconds_used')) {
                    return ['seconds_used' => 0];
                }
                if (str_contains($sql, 'status IN ("active", "paused")')) {
                    return ['cnt' => 0];
                }
                if (str_contains($sql, 'FROM copilot_audit_logs')) {
                    return ['total' => 0];
                }
                if (str_contains($sql, 'FROM users') && str_contains($sql, 'status <> "inactive"')) {
                    return ['cnt' => 1];
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
                        'settings' => json_encode(['video_retention_days' => 30], JSON_UNESCAPED_UNICODE),
                    ];
                }

                return false;
            });

        $usageMeter = new UsageMeterService(
            new WorkspaceRepository($conn),
            new BillingPeriodService(),
            new LiveSessionRepository($conn),
            new CopilotAuditRepository($conn),
            new UserRepository($conn),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Live session limit exceeded for current plan period');

        $svc = $this->makeService(conn: $conn, usageMeter: $usageMeter);
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
        $svc->recordFrameBatch(1, 10, []);
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
        $result = $svc->recordFrameBatch(1, 10, [
            ['frame_number' => 1, 'metrics' => ['trunk_angle' => 15.0], 'latency_ms' => 45.3],
            ['frame_number' => 2, 'metrics' => ['trunk_angle' => 18.0], 'latency_ms' => 42.1],
        ]);

        self::assertSame(2, $result['recorded']);
        self::assertEqualsWithDelta(43.7, $result['avg_latency_ms'], 0.01);
    }

    public function testIngestFrameBatchQueuesBrowserFramesForWorker(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturn([
                'id' => 12,
                'organization_id' => 10,
                'status' => 'active',
                'pose_engine' => 'yolo26',
                'model' => 'reba',
                'target_fps' => 5.0,
                'batch_window_ms' => 500,
                'max_e2e_latency_ms' => 2000,
            ]);

        $queue = $this->capturingQueue();
        $svc = $this->makeService(conn: $conn, queue: $queue);

        $result = $svc->ingestFrameBatch(10, 12, [
            [
                'frame_number' => 1,
                'captured_at_ms' => 1700000000000,
                'width' => 640,
                'height' => 360,
                'image_jpeg_base64' => str_repeat('a', 64),
            ],
        ]);

        self::assertSame(1, $result['queued']);
        self::assertCount(1, $queue->enqueued);
        self::assertSame('live_session_frame_batches', $queue->enqueued[0]['queue']);
        self::assertSame(1, $queue->enqueued[0]['payload']['frames'][0]['frame_number']);
    }

    public function testIngestFrameBatchDropsWhenFrameQueueIsBackpressured(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturn([
                'id' => 12,
                'organization_id' => 10,
                'status' => 'active',
                'pose_engine' => 'yolo26',
                'model' => 'reba',
                'target_fps' => 5.0,
                'batch_window_ms' => 500,
                'max_e2e_latency_ms' => 2000,
                'telemetry_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            ]);
        $conn->expects(self::never())->method('insert');
        $conn->expects(self::once())
            ->method('update')
            ->with(
                'live_sessions',
                self::callback(static function (array $data): bool {
                    $telemetry = json_decode((string) ($data['telemetry_json'] ?? '{}'), true);
                    return is_array($telemetry)
                        && (int) ($telemetry['server_dropped_frame_batches'] ?? 0) === 1
                        && (int) ($telemetry['server_dropped_frames'] ?? 0) === 1;
                }),
                ['id' => 12]
            );

        $queue = new class implements QueueInterface {
            public array $enqueued = [];
            public function enqueue(string $queue, array $payload): void {
                $this->enqueued[] = ['queue' => $queue, 'payload' => $payload];
            }
            public function dequeue(string $queue): ?array { return null; }
            public function size(string $queue): int { return 99; }
        };

        $config = $this->defaultConfig();
        $config['max_pending_frame_batches'] = 5;

        $svc = $this->makeService(conn: $conn, queue: $queue, config: $config);

        $result = $svc->ingestFrameBatch(10, 12, [
            [
                'frame_number' => 1,
                'captured_at_ms' => 1700000000000,
                'width' => 640,
                'height' => 360,
                'image_jpeg_base64' => str_repeat('a', 64),
            ],
        ]);

        self::assertSame('dropped_backpressure', $result['status']);
        self::assertSame(1, $result['dropped']);
        self::assertCount(0, $queue->enqueued);
    }

    public function testGetRecentFramesUsesCacheWhenAvailable(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 1,
                'organization_id' => 10,
                'status' => 'active',
                'pose_engine' => 'yolo26',
            ]);
        $conn->expects(self::never())->method('fetchAllAssociative');

        $cache = new ArrayCacheDriver();
        $cache->set('live:recent-frames:1', [
            ['frame_number' => 2, 'metrics_json' => '{"trunk_angle":18}', 'trunk_angle' => 18.0],
            ['frame_number' => 1, 'metrics_json' => '{"trunk_angle":15}', 'trunk_angle' => 15.0],
        ], 900);

        $svc = $this->makeService(conn: $conn, cache: $cache);
        $frames = $svc->getRecentFrames(10, 1, 1);

        self::assertCount(1, $frames);
        self::assertSame(2, $frames[0]['frame_number']);
    }

    public function testRecordFrameBatchPersistsWorkerTelemetry(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturn([
                'id' => 1,
                'organization_id' => 10,
                'status' => 'active',
                'pose_engine' => 'yolo26',
                'telemetry_json' => json_encode(['queued_frames' => 4], JSON_UNESCAPED_UNICODE),
            ]);
        $conn->method('insert')->willReturn(1);
        $conn->method('executeStatement')->willReturn(1);
        $conn->expects(self::once())
            ->method('update')
            ->with(
                'live_sessions',
                self::callback(static function (array $data): bool {
                    $telemetry = json_decode((string) ($data['telemetry_json'] ?? '{}'), true);
                    return is_array($telemetry)
                        && (int) ($telemetry['queued_frames'] ?? 0) === 4
                        && (int) ($telemetry['worker_processed_frames'] ?? 0) === 2
                        && (int) ($telemetry['worker_skipped_frames'] ?? 0) === 1
                        && (int) ($telemetry['worker_decode_failures'] ?? 0) === 1;
                }),
                ['id' => 1]
            );

        $svc = $this->makeService(conn: $conn);
        $svc->recordFrameBatch(
            1,
            10,
            [
                ['frame_number' => 1, 'metrics' => ['trunk_angle' => 15.0], 'latency_ms' => 45.3],
                ['frame_number' => 2, 'metrics' => ['trunk_angle' => 18.0], 'latency_ms' => 42.1],
            ],
            [
                'worker_processed_frames' => 2,
                'worker_skipped_frames' => 1,
                'worker_decode_failures' => 1,
                'worker_lag_samples' => 2,
                'worker_lag_ms_avg' => 175.5,
                'worker_lag_ms_max' => 220.0,
                'last_worker_at' => '2026-03-14T09:00:00Z',
            ],
        );
    }

    public function testStreamSnapshotReturnsNormalizedSessionTelemetry(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 12,
                'organization_id' => 10,
                'status' => 'active',
                'pose_engine' => 'yolo26',
                'summary_metrics_json' => json_encode(['avg_trunk_angle' => 14.2], JSON_UNESCAPED_UNICODE),
                'telemetry_json' => json_encode(['queued_frames' => 3, 'worker_processed_frames' => 2], JSON_UNESCAPED_UNICODE),
            ]);
        $conn->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['frame_number' => 2, 'metrics_json' => '{"trunk_angle":18}', 'trunk_angle' => 18.0],
            ]);

        $svc = $this->makeService(conn: $conn, cache: new ArrayCacheDriver());
        $snapshot = $svc->streamSnapshot(10, 12, 10);

        self::assertSame(12, $snapshot['session']['id']);
        self::assertSame(3, $snapshot['session']['telemetry']['queued_frames']);
        self::assertSame(14.2, $snapshot['session']['summary_metrics']['avg_trunk_angle']);
        self::assertCount(1, $snapshot['frames']);
    }
}
