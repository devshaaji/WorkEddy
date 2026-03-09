<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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

