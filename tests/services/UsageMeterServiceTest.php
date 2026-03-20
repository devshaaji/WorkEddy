<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WorkEddy\Repositories\CopilotAuditRepository;
use WorkEddy\Repositories\LiveSessionRepository;
use WorkEddy\Repositories\UserRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\BillingPeriodService;
use WorkEddy\Services\UsageMeterService;

final class UsageMeterServiceTest extends TestCase
{
    public function testCurrentUsageIncludesReservedScansInEnforcement(): void
    {
        $conn = $this->mockConnectionForUsage(
            plan: [
                'subscription_id' => 11,
                'id' => 2,
                'name' => 'professional',
                'scan_limit' => 10,
                'price' => 299.0,
                'billing_cycle' => 'monthly',
                'start_date' => '2026-03-01',
                'end_date' => null,
                'status' => 'active',
            ],
            used: 7,
            reserved: 3,
        );

        $service = new UsageMeterService(new WorkspaceRepository($conn), new BillingPeriodService());
        $snapshot = $service->currentUsage(77, new DateTimeImmutable('2026-03-08 09:00:00'));

        $this->assertSame(10, $snapshot['usage']['used_scans']);
        $this->assertSame(3, $snapshot['usage']['reserved_scans']);
        $this->assertSame(7, $snapshot['usage']['billed_scans']);
        $this->assertTrue($snapshot['usage']['limit_exceeded']);
        $this->assertSame('monthly', $snapshot['usage']['billing_cycle']);
        $this->assertSame('monthly', $snapshot['plan']['billing_cycle']);
    }

    public function testAssertAvailableThrowsWhenReservedUsageConsumesRemainingQuota(): void
    {
        $conn = $this->mockConnectionForUsage(
            plan: [
                'subscription_id' => 12,
                'id' => 1,
                'name' => 'starter',
                'scan_limit' => 5,
                'price' => 99.0,
                'billing_cycle' => 'monthly',
                'start_date' => '2026-03-01',
                'end_date' => null,
                'status' => 'active',
            ],
            used: 4,
            reserved: 1,
        );

        $service = new UsageMeterService(new WorkspaceRepository($conn), new BillingPeriodService());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Scan limit exceeded for current plan period');

        $service->assertAvailable(99);
    }

    public function testUnlimitedPlansNeverExceedLimit(): void
    {
        $conn = $this->mockConnectionForUsage(
            plan: [
                'subscription_id' => 13,
                'id' => 3,
                'name' => 'enterprise',
                'scan_limit' => null,
                'price' => 999.0,
                'billing_cycle' => 'yearly',
                'start_date' => '2026-01-10',
                'end_date' => null,
                'status' => 'active',
            ],
            used: 999,
            reserved: 100,
        );

        $service = new UsageMeterService(new WorkspaceRepository($conn), new BillingPeriodService());
        $snapshot = $service->currentUsage(55, new DateTimeImmutable('2026-03-08 09:00:00'));

        $this->assertNull($snapshot['usage']['remaining_scans']);
        $this->assertFalse($snapshot['usage']['limit_exceeded']);
        $this->assertSame('yearly', $snapshot['usage']['billing_cycle']);
    }

    public function testCurrentUsageExposesExtendedBillingMetrics(): void
    {
        $plan = [
            'subscription_id' => 14,
            'id' => 4,
            'name' => 'starter',
            'scan_limit' => 10,
            'price' => 0.0,
            'billing_cycle' => 'monthly',
            'billing_limits_json' => json_encode([
                'video_scan_limit' => 8,
                'live_session_limit' => 5,
                'live_session_minutes_limit' => 90,
                'llm_request_limit' => 20,
                'llm_token_limit' => 50000,
                'max_video_retention_days' => 30,
                'max_org_members' => 5,
                'max_live_concurrent_sessions' => 2,
            ], JSON_UNESCAPED_UNICODE),
            'start_date' => '2026-03-01',
            'end_date' => null,
            'status' => 'active',
        ];

        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $params = []) use ($plan) {
                if (str_contains($sql, 'FROM subscriptions s')) {
                    return $plan;
                }

                if (str_contains($sql, 'FROM usage_records')) {
                    $type = $params['usage_type'] ?? null;

                    return ['used' => match ($type) {
                        'manual_scan' => 2,
                        'video_scan' => 5,
                        default => 7,
                    }];
                }

                if (str_contains($sql, 'FROM usage_reservations')) {
                    $type = $params['usage_type'] ?? null;

                    return ['reserved' => match ($type) {
                        'manual_scan' => 0,
                        'video_scan' => 1,
                        default => 1,
                    }];
                }

                if (str_contains($sql, 'FROM live_sessions') && str_contains($sql, 'started_at >= :period_start')) {
                    return ['cnt' => 4];
                }

                if (str_contains($sql, 'seconds_used')) {
                    return ['seconds_used' => 5100];
                }

                if (str_contains($sql, 'status IN ("active", "paused")')) {
                    return ['cnt' => 1];
                }

                if (str_contains($sql, 'FROM copilot_audit_logs') && str_contains($sql, 'SUM(llm_request_count)')) {
                    return ['total' => 12];
                }

                if (str_contains($sql, 'FROM copilot_audit_logs') && str_contains($sql, 'SUM(llm_total_tokens)')) {
                    return ['total' => 32000];
                }

                if (str_contains($sql, 'FROM users') && str_contains($sql, 'status <> "inactive"')) {
                    return ['cnt' => 3];
                }

                if (str_contains($sql, 'FROM organizations')) {
                    return [
                        'id' => 77,
                        'name' => 'Org 77',
                        'slug' => 'org-77',
                        'contact_email' => null,
                        'plan' => 'starter',
                        'status' => 'active',
                        'created_at' => '2026-03-01 00:00:00',
                        'updated_at' => null,
                        'settings' => json_encode(['video_retention_days' => 45], JSON_UNESCAPED_UNICODE),
                    ];
                }

                return false;
            });

        $service = new UsageMeterService(
            new WorkspaceRepository($conn),
            new BillingPeriodService(),
            new LiveSessionRepository($conn),
            new CopilotAuditRepository($conn),
            new UserRepository($conn),
        );

        $snapshot = $service->currentUsage(77, new DateTimeImmutable('2026-03-08 09:00:00'));

        $this->assertSame(6, $snapshot['usage']['metrics']['video_scans']['used']);
        $this->assertSame(4, $snapshot['usage']['metrics']['live_sessions']['used']);
        $this->assertSame(85, $snapshot['usage']['metrics']['live_session_minutes']['used']);
        $this->assertSame(12, $snapshot['usage']['metrics']['llm_requests']['used']);
        $this->assertSame(32000, $snapshot['usage']['metrics']['llm_tokens']['used']);
        $this->assertSame(45, $snapshot['usage']['metrics']['video_retention_days']['used']);
        $this->assertTrue($snapshot['usage']['metrics']['video_retention_days']['exceeded']);
        $this->assertContains('video_retention_days', $snapshot['usage']['violations']);
        $this->assertSame(2, $snapshot['plan']['billing_limits']['max_live_concurrent_sessions']);
    }

    private function mockConnectionForUsage(array $plan, int $used, int $reserved): Connection
    {
        $conn = $this->createMock(Connection::class);

        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $params = []) use ($plan, $used, $reserved) {
                if (str_contains($sql, 'FROM subscriptions s')) {
                    return $plan;
                }

                if (str_contains($sql, 'FROM usage_records')) {
                    return ['used' => $used];
                }

                if (str_contains($sql, 'FROM usage_reservations')) {
                    return ['reserved' => $reserved];
                }

                return false;
            });

        return $conn;
    }
}

