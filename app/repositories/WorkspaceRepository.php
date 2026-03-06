<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class WorkspaceRepository
{
    public function __construct(private readonly Connection $db) {}

    public function findById(int $organizationId): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, name, slug, contact_email, plan, status, created_at, updated_at
             FROM organizations WHERE id = :id LIMIT 1',
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
            'SELECT p.id, p.name, p.scan_limit, p.price, s.start_date, s.end_date, s.status
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

    public function monthlyUsageCount(int $organizationId): int
    {
        $row = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS used FROM usage_records
             WHERE organization_id = :org_id
               AND YEAR(created_at) = YEAR(CURRENT_DATE())
               AND MONTH(created_at) = MONTH(CURRENT_DATE())',
            ['org_id' => $organizationId]
        );
        return (int) ($row['used'] ?? 0);
    }

    public function createSubscription(int $organizationId, int $planId): void
    {
        $this->db->executeStatement(
            'INSERT INTO subscriptions (organization_id, plan_id, start_date, status, created_at) VALUES (:org_id, :plan_id, CURRENT_DATE(), "active", NOW())',
            ['org_id' => $organizationId, 'plan_id' => $planId]
        );
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
        return $this->db->fetchAllAssociative('SELECT id, name, scan_limit, price FROM plans ORDER BY id ASC');
    }

    public function updateOrg(int $id, array $data): void
    {
        $sets   = [];
        $params = ['id' => $id];

        foreach (['name', 'slug', 'contact_email'] as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]       = "$col = :$col";
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
}