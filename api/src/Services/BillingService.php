<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class BillingService
{
    public function __construct(private Connection $db)
    {
    }

    public function createDefaultSubscription(int $organizationId): void
    {
        $starter = $this->db->fetchAssociative('SELECT id FROM plans WHERE name = :name LIMIT 1', ['name' => 'starter']);
        if (!$starter) {
            throw new RuntimeException('Starter plan not configured');
        }

        $this->db->executeStatement(
            'INSERT INTO subscriptions (organization_id, plan_id, start_date, status, created_at) VALUES (:organization_id, :plan_id, NOW(), :status, NOW())',
            ['organization_id' => $organizationId, 'plan_id' => (int) $starter['id'], 'status' => 'active']
        );
    }

    public function activePlan(int $organizationId): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT p.id, p.name, p.scan_limit, p.price, s.start_date, s.end_date, s.status
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.organization_id = :organization_id AND s.status = :status
             ORDER BY s.id DESC LIMIT 1',
            ['organization_id' => $organizationId, 'status' => 'active']
        );

        if (!$row) {
            throw new RuntimeException('No active subscription');
        }

        return $row;
    }

    public function monthlyUsage(int $organizationId): array
    {
        $usage = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS used_scans
             FROM usage_records
             WHERE organization_id = :organization_id
               AND YEAR(created_at) = YEAR(CURRENT_DATE())
               AND MONTH(created_at) = MONTH(CURRENT_DATE())',
            ['organization_id' => $organizationId]
        ) ?: ['used_scans' => 0];

        $plan = $this->activePlan($organizationId);
        $limit = $plan['scan_limit'] === null ? null : (int) $plan['scan_limit'];
        $used = (int) $usage['used_scans'];

        return [
            'plan' => [
                'id' => (int) $plan['id'],
                'name' => (string) $plan['name'],
                'scan_limit' => $limit,
                'price' => (float) $plan['price'],
                'status' => (string) $plan['status'],
            ],
            'usage' => [
                'month' => date('Y-m'),
                'used_scans' => $used,
                'remaining_scans' => $limit === null ? null : max(0, $limit - $used),
                'limit_exceeded' => $limit === null ? false : $used >= $limit,
            ],
        ];
    }

    public function assertScanAvailable(int $organizationId): void
    {
        $snapshot = $this->monthlyUsage($organizationId);
        if (($snapshot['usage']['limit_exceeded'] ?? false) === true) {
            throw new RuntimeException('Monthly scan limit exceeded for current plan');
        }
    }

    public function plans(): array
    {
        return $this->db->fetchAllAssociative('SELECT id, name, scan_limit, price FROM plans ORDER BY id ASC');
    }
}
