<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use WorkEddy\Repositories\WorkspaceRepository;

final class BillingService
{
    public function __construct(private readonly WorkspaceRepository $workspaces) {}

    public function plans(): array
    {
        return $this->workspaces->allPlans();
    }

    public function currentUsageSummary(int $organizationId): array
    {
        $plan  = $this->workspaces->activePlan($organizationId);
        $used  = $this->workspaces->monthlyUsageCount($organizationId);
        $limit = $plan['scan_limit'] !== null ? (int) $plan['scan_limit'] : null;

        return [
            'plan' => [
                'id'         => (int)   $plan['id'],
                'name'       => (string) $plan['name'],
                'scan_limit' => $limit,
                'price'      => (float)  $plan['price'],
                'status'     => (string) $plan['status'],
            ],
            'usage' => [
                'month'           => date('Y-m'),
                'used_scans'      => $used,
                'remaining_scans' => $limit === null ? null : max(0, $limit - $used),
                'limit_exceeded'  => $limit !== null && $used >= $limit,
            ],
        ];
    }
}
