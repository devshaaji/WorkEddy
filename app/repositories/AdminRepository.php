<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;

final class AdminRepository
{
    public function __construct(private readonly Connection $db) {}

    /* ── Organizations ───────────────────────────────────────────────── */

    public function listAllOrganizations(): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT o.id, o.name, o.slug, o.contact_email, o.plan, o.status, o.created_at,
                    (SELECT COUNT(*) FROM users u WHERE u.organization_id = o.id) AS user_count,
                    (SELECT p2.name FROM subscriptions s2
                     JOIN plans p2 ON p2.id = s2.plan_id
                     WHERE s2.organization_id = o.id AND s2.status = :sub_status
                     ORDER BY s2.id DESC LIMIT 1) AS active_plan
             FROM organizations o
             ORDER BY o.id DESC',
            ['sub_status' => 'active']
        );
    }

    public function findOrganizationById(int $id): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT o.id, o.name, o.slug, o.contact_email, o.plan, o.status,
                    o.created_at, o.updated_at,
                    (SELECT COUNT(*) FROM users u WHERE u.organization_id = o.id) AS user_count
             FROM organizations o WHERE o.id = :id LIMIT 1',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function createOrganization(string $name, string $slug, ?string $contactEmail): int
    {
        $this->db->executeStatement(
            'INSERT INTO organizations (name, slug, contact_email, plan, status, created_at)
             VALUES (:name, :slug, :email, :plan, :status, NOW())',
            [
                'name'   => $name,
                'slug'   => $slug,
                'email'  => $contactEmail,
                'plan'   => 'starter',
                'status' => 'active',
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateOrganization(int $id, array $data): void
    {
        $sets   = [];
        $params = ['id' => $id];

        foreach (['name', 'slug', 'contact_email', 'status'] as $col) {
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

    /* ── Users (global) ──────────────────────────────────────────────── */

    public function listAllUsers(): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT u.id, u.organization_id, u.name, u.email, u.role, u.status, u.created_at,
                    o.name AS org_name
             FROM users u
             INNER JOIN organizations o ON o.id = u.organization_id
             ORDER BY u.id DESC'
        );
    }

    public function updateUser(int $id, array $data): void
    {
        $sets   = [];
        $params = ['id' => $id];

        foreach (['name', 'email', 'role', 'status', 'organization_id'] as $col) {
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
            'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function deleteUser(int $id): void
    {
        $this->db->executeStatement('DELETE FROM users WHERE id = :id', ['id' => $id]);
    }

    /* ── Plans ────────────────────────────────────────────────────────── */

    public function listPlans(): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT p.id, p.name, p.scan_limit, p.price, p.billing_cycle, p.status,
                    (SELECT COUNT(*) FROM subscriptions s
                     WHERE s.plan_id = p.id AND s.status = :sub_status) AS active_subs
             FROM plans p ORDER BY p.id ASC',
            ['sub_status' => 'active']
        );
    }

    public function findPlanById(int $id): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM plans WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function createPlan(
        string $name,
        ?int $scanLimit,
        float $price,
        string $billingCycle = 'monthly'
    ): int {
        $this->db->executeStatement(
            'INSERT INTO plans (name, scan_limit, price, billing_cycle, status)
             VALUES (:name, :lim, :price, :cycle, :status)',
            [
                'name'   => $name,
                'lim'    => $scanLimit,
                'price'  => $price,
                'cycle'  => $billingCycle,
                'status' => 'active',
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updatePlan(int $id, array $data): void
    {
        $sets   = [];
        $params = ['id' => $id];

        foreach (['name', 'scan_limit', 'price', 'billing_cycle', 'status'] as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]       = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }

        if (empty($sets)) {
            return;
        }

        $this->db->executeStatement(
            'UPDATE plans SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params
        );
    }

    public function deletePlan(int $id): void
    {
        $this->db->executeStatement('DELETE FROM plans WHERE id = :id', ['id' => $id]);
    }

    /* ── System Stats ─────────────────────────────────────────────────── */

    public function systemStats(): array
    {
        $orgs  = $this->db->fetchAssociative('SELECT COUNT(*) AS c FROM organizations');
        $users = $this->db->fetchAssociative('SELECT COUNT(*) AS c FROM users');
        $scans = $this->db->fetchAssociative('SELECT COUNT(*) AS c FROM scans');

        $scansThisMonth = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS c FROM scans
             WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
               AND MONTH(created_at) = MONTH(CURRENT_DATE())'
        );

        $revenue = $this->db->fetchAssociative(
            'SELECT COALESCE(SUM(p.price), 0) AS total
             FROM subscriptions s
             JOIN plans p ON p.id = s.plan_id
             WHERE s.status = :status',
            ['status' => 'active']
        );

        $recentOrgs = $this->db->fetchAllAssociative(
            'SELECT id, name, status, created_at
             FROM organizations
             ORDER BY id DESC LIMIT 5'
        );

        $recentUsers = $this->db->fetchAllAssociative(
            'SELECT u.id, u.name, u.email, u.role, o.name AS org_name, u.created_at
             FROM users u
             JOIN organizations o ON o.id = u.organization_id
             ORDER BY u.id DESC LIMIT 5'
        );

        $riskBreakdown = $this->db->fetchAllAssociative(
            'SELECT risk_category, COUNT(*) AS cnt
             FROM scans GROUP BY risk_category'
        );

        return [
            'total_organizations'  => (int) ($orgs['c'] ?? 0),
            'total_users'          => (int) ($users['c'] ?? 0),
            'total_scans'          => (int) ($scans['c'] ?? 0),
            'scans_this_month'     => (int) ($scansThisMonth['c'] ?? 0),
            'monthly_revenue'      => (float) ($revenue['total'] ?? 0),
            'recent_organizations' => $recentOrgs,
            'recent_users'         => $recentUsers,
            'risk_breakdown'       => $riskBreakdown,
        ];
    }
}
