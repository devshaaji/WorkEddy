<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use Doctrine\DBAL\Connection;

final class DashboardService
{
    public function __construct(private readonly Connection $db) {}

    public function summary(int $organizationId): array
    {
        $totals = $this->db->fetchAssociative(
            'SELECT
                COUNT(*)                                                             AS total_scans,
                SUM(CASE WHEN risk_category = "high"     THEN 1 ELSE 0 END)        AS high_risk,
                SUM(CASE WHEN risk_category = "moderate" THEN 1 ELSE 0 END)        AS moderate_risk,
                ROUND(AVG(normalized_score), 1)                                     AS avg_score
             FROM scans WHERE organization_id = :org_id',
            ['org_id' => $organizationId]
        ) ?: [];

        $recentScans = $this->db->fetchAllAssociative(
            'SELECT s.id, s.scan_type, s.normalized_score, s.risk_category, s.status, s.created_at,
                    t.name AS task_name
             FROM scans s
             LEFT JOIN tasks t ON t.id = s.task_id
             WHERE s.organization_id = :org_id
             ORDER BY s.id DESC
             LIMIT 5',
            ['org_id' => $organizationId]
        );

        $topTasks = $this->db->fetchAllAssociative(
            'SELECT t.id, t.name, COUNT(s.id) AS scan_count,
                    MAX(s.risk_category) AS highest_risk
             FROM tasks t
             LEFT JOIN scans s ON s.task_id = t.id
             WHERE t.organization_id = :org_id
             GROUP BY t.id, t.name
             ORDER BY scan_count DESC
             LIMIT 5',
            ['org_id' => $organizationId]
        );

        // ── Weekly scan trends (last 12 weeks) ──────────────────────────
        $weeklyTrends = $this->db->fetchAllAssociative(
            'SELECT
                YEARWEEK(created_at, 1)                                             AS yw,
                DATE_FORMAT(MIN(created_at), "%Y-%m-%d")                            AS week_start,
                COUNT(*)                                                             AS scan_count,
                SUM(CASE WHEN risk_category = "high"     THEN 1 ELSE 0 END)        AS high,
                SUM(CASE WHEN risk_category = "moderate" THEN 1 ELSE 0 END)        AS moderate,
                SUM(CASE WHEN risk_category = "low"      THEN 1 ELSE 0 END)        AS low,
                ROUND(AVG(normalized_score), 1)                                     AS avg_score
             FROM scans
             WHERE organization_id = :org_id
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
             GROUP BY yw
             ORDER BY yw ASC',
            ['org_id' => $organizationId]
        );

        // ── Department risk heatmap ──────────────────────────────────
        $departmentHeatmap = $this->db->fetchAllAssociative(
            'SELECT
                t.department,
                COUNT(s.id)                                                          AS scan_count,
                ROUND(AVG(s.normalized_score), 1)                                    AS avg_score,
                SUM(CASE WHEN s.risk_category = "high"     THEN 1 ELSE 0 END)       AS high,
                SUM(CASE WHEN s.risk_category = "moderate" THEN 1 ELSE 0 END)       AS moderate,
                SUM(CASE WHEN s.risk_category = "low"      THEN 1 ELSE 0 END)       AS low
             FROM scans s
             JOIN tasks t ON t.id = s.task_id
             WHERE s.organization_id = :org_id
               AND t.department IS NOT NULL AND t.department != ""
             GROUP BY t.department
             ORDER BY avg_score DESC',
            ['org_id' => $organizationId]
        );

        return [
            'total_scans'        => (int)   ($totals['total_scans']   ?? 0),
            'high_risk'          => (int)   ($totals['high_risk']     ?? 0),
            'moderate_risk'      => (int)   ($totals['moderate_risk'] ?? 0),
            'avg_score'          => isset($totals['avg_score']) ? (float) $totals['avg_score'] : null,
            'recent_scans'       => $recentScans,
            'top_tasks'          => $topTasks,
            'weekly_trends'      => $weeklyTrends,
            'department_heatmap' => $departmentHeatmap,
        ];
    }
}
