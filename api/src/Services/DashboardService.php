<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

use Doctrine\DBAL\Connection;

final class DashboardService
{
    public function __construct(private Connection $db)
    {
    }

    public function summary(int $organizationId): array
    {
        return [
            'totals' => $this->db->fetchAssociative('SELECT COUNT(*) AS total_scans, SUM(CASE WHEN risk_category = "high" THEN 1 ELSE 0 END) AS high_risk_tasks, SUM(CASE WHEN risk_category = "moderate" THEN 1 ELSE 0 END) AS moderate_risk_tasks, SUM(CASE WHEN risk_category = "low" THEN 1 ELSE 0 END) AS low_risk_tasks FROM scans WHERE organization_id = :organization_id', ['organization_id' => $organizationId]) ?: [],
            'risk_distribution' => $this->db->fetchAllAssociative('SELECT risk_category, COUNT(*) AS total FROM scans WHERE organization_id = :organization_id GROUP BY risk_category', ['organization_id' => $organizationId]),
            'scan_trends' => $this->db->fetchAllAssociative('SELECT DATE(created_at) AS scan_date, COUNT(*) AS total FROM scans WHERE organization_id = :organization_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY scan_date ASC', ['organization_id' => $organizationId]),
        ];
    }
}
