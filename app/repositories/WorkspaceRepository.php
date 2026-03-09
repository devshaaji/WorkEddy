<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use DateTimeInterface;
use Doctrine\DBAL\Connection;
use RuntimeException;

final class WorkspaceRepository
{
    private ?bool $hasOrganizationSettingsColumn = null;

    public function __construct(private readonly Connection $db) {}

    public function findById(int $organizationId): array
    {
        $select = 'id, name, slug, contact_email, plan, status, created_at, updated_at';
        if ($this->organizationHasSettingsColumn()) {
            $select .= ', settings';
        } else {
            $select .= ', NULL AS settings';
        }

        $row = $this->db->fetchAssociative(
            'SELECT ' . $select . ' FROM organizations WHERE id = :id LIMIT 1',
            ['id' => $organizationId]
        );
        if (!$row) {
            throw new RuntimeException('Organization not found');
        }

        return $row;
    }

    public function create(string $name, string $plan = 'starter'): int
    {
        $this->db->executeStatement(
            'INSERT INTO organizations (name, plan, created_at) VALUES (:name, :plan, NOW())',
            ['name' => $name, 'plan' => $plan]
        );

        return (int) $this->db->lastInsertId();
    }

    public function activePlan(int $organizationId): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT s.id AS subscription_id,
                    p.id, p.name, p.scan_limit, p.price, p.billing_cycle, s.start_date, s.end_date, s.status
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.organization_id = :org_id AND s.status = "active"
             ORDER BY s.id DESC LIMIT 1',
            ['org_id' => $organizationId]
        );
        if (!$row) {
            throw new RuntimeException('No active subscription found');
        }

        return $row;
    }

    public function usageCountForPeriod(int $organizationId, DateTimeInterface $periodStart, DateTimeInterface $periodEnd): int
    {
        $row = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS used FROM usage_records
             WHERE organization_id = :org_id
               AND created_at >= :period_start
               AND created_at < :period_end',
            [
                'org_id' => $organizationId,
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
                'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        return (int) ($row['used'] ?? 0);
    }

    public function reservationCountForPeriod(int $organizationId, DateTimeInterface $periodStart, DateTimeInterface $periodEnd): int
    {
        $row = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS reserved FROM usage_reservations
             WHERE organization_id = :org_id
               AND created_at >= :period_start
               AND created_at < :period_end',
            [
                'org_id' => $organizationId,
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
                'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        return (int) ($row['reserved'] ?? 0);
    }

    public function monthlyUsageCount(int $organizationId): int
    {
        $now = new \DateTimeImmutable('now');
        $periodStart = $now->setDate((int) $now->format('Y'), (int) $now->format('n'), 1)->setTime(0, 0, 0);
        $periodEnd = $periodStart->modify('+1 month');

        return $this->usageCountForPeriod($organizationId, $periodStart, $periodEnd);
    }

    public function createSubscription(int $organizationId, int $planId): int
    {
        $this->db->executeStatement(
            'INSERT INTO subscriptions (organization_id, plan_id, start_date, status, created_at) VALUES (:org_id, :plan_id, CURRENT_DATE(), "active", NOW())',
            ['org_id' => $organizationId, 'plan_id' => $planId]
        );

        return (int) $this->db->lastInsertId();
    }

    public function starterPlanId(): int
    {
        $row = $this->db->fetchAssociative('SELECT id FROM plans WHERE name = "starter" LIMIT 1');
        if (!$row) {
            throw new RuntimeException('Starter plan not seeded in database');
        }

        return (int) $row['id'];
    }

    public function allPlans(): array
    {
        return $this->db->fetchAllAssociative('SELECT id, name, scan_limit, price, billing_cycle FROM plans ORDER BY id ASC');
    }

    public function updateOrg(int $id, array $data): void
    {
        $sets = [];
        $params = ['id' => $id];

        $updatableColumns = ['name', 'slug', 'contact_email'];
        if ($this->organizationHasSettingsColumn()) {
            $updatableColumns[] = 'settings';
        }

        foreach ($updatableColumns as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }

        if (empty($sets)) {
            return;
        }

        $sets[] = 'updated_at = NOW()';

        $this->db->executeStatement(
            'UPDATE organizations SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function deactivateSubscriptions(int $orgId): void
    {
        $this->db->executeStatement(
            'UPDATE subscriptions SET status = :status, end_date = CURRENT_DATE() WHERE organization_id = :org_id AND status = :active',
            ['status' => 'cancelled', 'org_id' => $orgId, 'active' => 'active']
        );
    }

    private function organizationHasSettingsColumn(): bool
    {
        if ($this->hasOrganizationSettingsColumn !== null) {
            return $this->hasOrganizationSettingsColumn;
        }

        $columns = $this->db->createSchemaManager()->listTableColumns('organizations');
        $this->hasOrganizationSettingsColumn = array_key_exists('settings', $columns);

        return $this->hasOrganizationSettingsColumn;
    }
}
