<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use DateTimeInterface;
use Doctrine\DBAL\Connection;
use RuntimeException;
use WorkEddy\Services\PlanBillingDefaults;

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

    /**
     * @return array<string,mixed>
     */
    public function organizationSettings(int $organizationId): array
    {
        $org = $this->findById($organizationId);
        $raw = $org['settings'] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function organizationSetting(int $organizationId, string $key, mixed $default = null): mixed
    {
        $settings = $this->organizationSettings($organizationId);
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
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
                    p.id, p.name, p.scan_limit, p.price, p.billing_cycle, p.billing_limits_json,
                    s.start_date, s.end_date, s.status
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.organization_id = :org_id AND s.status = "active"
             ORDER BY s.id DESC LIMIT 1',
            ['org_id' => $organizationId]
        );
        if (!$row) {
            throw new RuntimeException('No active subscription found');
        }

        return $this->hydratePlanRow($row);
    }

    public function usageCountForPeriod(
        int $organizationId,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd,
        ?string $usageType = null
    ): int
    {
        $sql = 'SELECT COUNT(*) AS used FROM usage_records
             WHERE organization_id = :org_id
               AND created_at >= :period_start
               AND created_at < :period_end';
        $params = [
            'org_id' => $organizationId,
            'period_start' => $periodStart->format('Y-m-d H:i:s'),
            'period_end' => $periodEnd->format('Y-m-d H:i:s'),
        ];

        if ($usageType !== null && $usageType !== '') {
            $sql .= ' AND usage_type = :usage_type';
            $params['usage_type'] = $usageType;
        }

        $row = $this->db->fetchAssociative(
            $sql,
            $params
        );

        return (int) ($row['used'] ?? 0);
    }

    public function reservationCountForPeriod(
        int $organizationId,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd,
        ?string $usageType = null
    ): int
    {
        $sql = 'SELECT COUNT(*) AS reserved FROM usage_reservations
             WHERE organization_id = :org_id
               AND created_at >= :period_start
               AND created_at < :period_end';
        $params = [
            'org_id' => $organizationId,
            'period_start' => $periodStart->format('Y-m-d H:i:s'),
            'period_end' => $periodEnd->format('Y-m-d H:i:s'),
        ];

        if ($usageType !== null && $usageType !== '') {
            $sql .= ' AND usage_type = :usage_type';
            $params['usage_type'] = $usageType;
        }

        $row = $this->db->fetchAssociative(
            $sql,
            $params
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
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, name, scan_limit, price, billing_cycle, billing_limits_json, status
             FROM plans
             ORDER BY id ASC'
        );

        return array_map(fn (array $row): array => $this->hydratePlanRow($row), $rows);
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

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function hydratePlanRow(array $row): array
    {
        $scanLimit = isset($row['scan_limit']) && $row['scan_limit'] !== null ? (int) $row['scan_limit'] : null;
        $row['billing_limits'] = PlanBillingDefaults::normalize(
            $row['billing_limits_json'] ?? null,
            isset($row['name']) ? (string) $row['name'] : null,
            $scanLimit,
        );
        unset($row['billing_limits_json']);

        return $row;
    }
}
