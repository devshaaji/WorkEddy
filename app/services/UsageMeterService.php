<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use DateTimeImmutable;
use RuntimeException;
use WorkEddy\Repositories\WorkspaceRepository;

final class UsageMeterService
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly BillingPeriodService $periods,
    ) {}

    public function currentUsage(int $organizationId, ?DateTimeImmutable $now = null): array
    {
        $plan = $this->workspaces->activePlan($organizationId);
        $limit = $plan['scan_limit'] !== null ? (int) $plan['scan_limit'] : null;
        $period = $this->periods->currentPeriod(
            (string) $plan['start_date'],
            (string) ($plan['billing_cycle'] ?? 'monthly'),
            $now,
        );

        $billed = $this->workspaces->usageCountForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
        );
        $reserved = $this->workspaces->reservationCountForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
        );
        $usedForEnforcement = $billed + $reserved;

        return [
            'plan' => [
                'id' => (int) $plan['id'],
                'subscription_id' => (int) ($plan['subscription_id'] ?? 0),
                'name' => (string) $plan['name'],
                'scan_limit' => $limit,
                'price' => (float) $plan['price'],
                'status' => (string) $plan['status'],
                'billing_cycle' => (string) ($plan['billing_cycle'] ?? 'monthly'),
            ],
            'usage' => [
                // Backwards-compatible key.
                'month' => ($now ?? new DateTimeImmutable('now'))->format('Y-m'),
                'used_scans' => $usedForEnforcement,
                'remaining_scans' => $limit === null ? null : max(0, $limit - $usedForEnforcement),
                'limit_exceeded' => $limit !== null && $usedForEnforcement >= $limit,
                'billed_scans' => $billed,
                'reserved_scans' => $reserved,
                'period_start' => $period['period_start']->format('Y-m-d H:i:s'),
                'period_end' => $period['period_end']->format('Y-m-d H:i:s'),
                'billing_cycle' => $period['billing_cycle'],
            ],
        ];
    }

    public function assertAvailable(int $organizationId): void
    {
        $snapshot = $this->currentUsage($organizationId);
        if ($snapshot['usage']['limit_exceeded'] === true) {
            throw new RuntimeException('Scan limit exceeded for current plan period');
        }
    }
}

