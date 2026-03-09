<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WorkEddy\Services\BillingPeriodService;

final class BillingPeriodServiceTest extends TestCase
{
    private BillingPeriodService $periods;

    protected function setUp(): void
    {
        $this->periods = new BillingPeriodService();
    }

    public function testMonthlyPeriodHandlesBoundaryTransitions(): void
    {
        $period = $this->periods->currentPeriod(
            '2026-01-31',
            'monthly',
            new DateTimeImmutable('2026-02-15 10:00:00'),
        );

        $this->assertSame('monthly', $period['billing_cycle']);
        $this->assertSame('2026-01-31 00:00:00', $period['period_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28 00:00:00', $period['period_end']->format('Y-m-d H:i:s'));

        $next = $this->periods->currentPeriod(
            '2026-01-31',
            'monthly',
            new DateTimeImmutable('2026-02-28 12:00:00'),
        );

        $this->assertSame('2026-02-28 00:00:00', $next['period_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-31 00:00:00', $next['period_end']->format('Y-m-d H:i:s'));
    }

    public function testYearlyPeriodHandlesLeapYearAnchors(): void
    {
        $period = $this->periods->currentPeriod(
            '2024-02-29',
            'yearly',
            new DateTimeImmutable('2025-03-01 08:00:00'),
        );

        $this->assertSame('yearly', $period['billing_cycle']);
        $this->assertSame('2025-02-28 00:00:00', $period['period_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28 00:00:00', $period['period_end']->format('Y-m-d H:i:s'));
    }
}
