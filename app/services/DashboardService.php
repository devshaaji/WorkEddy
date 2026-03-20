<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use Doctrine\DBAL\Connection;

final class DashboardService
{
    private ?bool $hasLeadingIndicatorsTable = null;

    public function __construct(private readonly Connection $db) {}

    public function summary(int $organizationId, int $userId, string $role): array
    {
        return match ($role) {
            'worker' => $this->workerSummary($organizationId, $userId),
            'observer' => $this->observerSummary($organizationId, $userId),
            default => $this->orgSummary($organizationId, $role),
        };
    }

    private function orgSummary(int $organizationId, string $role): array
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

        $leadingIndicators = [
            'total_checkins_30d' => 0,
            'avg_discomfort_30d' => null,
            'avg_fatigue_30d' => null,
            'avg_micro_breaks_30d' => null,
            'high_psychosocial_count_30d' => 0,
        ];

        if ($this->hasLeadingIndicatorsTable()) {
            $li = $this->db->fetchAssociative(
                'SELECT
                    COUNT(*) AS total_checkins,
                    ROUND(AVG(discomfort_level), 2) AS avg_discomfort,
                    ROUND(AVG(fatigue_level), 2) AS avg_fatigue,
                    ROUND(AVG(micro_breaks_taken), 2) AS avg_micro_breaks,
                    SUM(CASE WHEN psychosocial_load = "high" THEN 1 ELSE 0 END) AS high_psychosocial_count
                 FROM worker_leading_indicators
                 WHERE organization_id = :org_id
                   AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
                ['org_id' => $organizationId]
            ) ?: [];

            $leadingIndicators = [
                'total_checkins_30d' => (int) ($li['total_checkins'] ?? 0),
                'avg_discomfort_30d' => isset($li['avg_discomfort']) ? (float) $li['avg_discomfort'] : null,
                'avg_fatigue_30d' => isset($li['avg_fatigue']) ? (float) $li['avg_fatigue'] : null,
                'avg_micro_breaks_30d' => isset($li['avg_micro_breaks']) ? (float) $li['avg_micro_breaks'] : null,
                'high_psychosocial_count_30d' => (int) ($li['high_psychosocial_count'] ?? 0),
            ];
        }

        return [
            'dashboard_mode'     => 'organization',
            'role'               => $role,
            'can_create_scan'    => true,
            'can_manage_org'     => true,
            'can_rate_scans'     => true,
            'kpi_labels'         => [
                'total_scans' => 'Total Scans',
                'high_risk' => 'High Risk',
                'moderate_risk' => 'Moderate Risk',
                'avg_score' => 'Avg Risk Score',
            ],
            'total_scans'        => (int)   ($totals['total_scans']   ?? 0),
            'high_risk'          => (int)   ($totals['high_risk']     ?? 0),
            'moderate_risk'      => (int)   ($totals['moderate_risk'] ?? 0),
            'avg_score'          => isset($totals['avg_score']) ? (float) $totals['avg_score'] : null,
            'recent_scans'       => $recentScans,
            'top_tasks'          => $topTasks,
            'weekly_trends'      => $weeklyTrends,
            'department_heatmap' => $departmentHeatmap,
            'leading_indicators' => $leadingIndicators,
        ];
    }

    private function workerSummary(int $organizationId, int $userId): array
    {
        $totals = $this->db->fetchAssociative(
            'SELECT
                COUNT(*)                                                             AS total_scans,
                SUM(CASE WHEN risk_category = "high"     THEN 1 ELSE 0 END)        AS high_risk,
                SUM(CASE WHEN risk_category = "moderate" THEN 1 ELSE 0 END)        AS moderate_risk,
                ROUND(AVG(normalized_score), 1)                                     AS avg_score
             FROM scans
             WHERE organization_id = :org_id
               AND user_id = :user_id',
            ['org_id' => $organizationId, 'user_id' => $userId]
        ) ?: [];

        $recentScans = $this->db->fetchAllAssociative(
            'SELECT s.id, s.scan_type, s.normalized_score, s.risk_category, s.status, s.created_at,
                    t.name AS task_name
             FROM scans s
             LEFT JOIN tasks t ON t.id = s.task_id
             WHERE s.organization_id = :org_id
               AND s.user_id = :user_id
             ORDER BY s.id DESC
             LIMIT 5',
            ['org_id' => $organizationId, 'user_id' => $userId]
        );

        $topTasks = $this->db->fetchAllAssociative(
            'SELECT t.id, t.name, COUNT(s.id) AS scan_count,
                    MAX(s.risk_category) AS highest_risk
             FROM tasks t
             LEFT JOIN scans s ON s.task_id = t.id AND s.user_id = :user_id
             WHERE t.organization_id = :org_id
             GROUP BY t.id, t.name
             HAVING scan_count > 0
             ORDER BY scan_count DESC
             LIMIT 5',
            ['org_id' => $organizationId, 'user_id' => $userId]
        );

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
               AND user_id = :user_id
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
             GROUP BY yw
             ORDER BY yw ASC',
            ['org_id' => $organizationId, 'user_id' => $userId]
        );

        $leadingIndicators = [
            'total_checkins_30d' => 0,
            'avg_discomfort_30d' => null,
            'avg_fatigue_30d' => null,
            'avg_micro_breaks_30d' => null,
            'high_psychosocial_count_30d' => 0,
        ];

        if ($this->hasLeadingIndicatorsTable()) {
            $li = $this->db->fetchAssociative(
                'SELECT
                    COUNT(*) AS total_checkins,
                    ROUND(AVG(discomfort_level), 2) AS avg_discomfort,
                    ROUND(AVG(fatigue_level), 2) AS avg_fatigue,
                    ROUND(AVG(micro_breaks_taken), 2) AS avg_micro_breaks,
                    SUM(CASE WHEN psychosocial_load = "high" THEN 1 ELSE 0 END) AS high_psychosocial_count
                 FROM worker_leading_indicators
                 WHERE organization_id = :org_id
                   AND user_id = :user_id
                   AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
                ['org_id' => $organizationId, 'user_id' => $userId]
            ) ?: [];

            $leadingIndicators = [
                'total_checkins_30d' => (int) ($li['total_checkins'] ?? 0),
                'avg_discomfort_30d' => isset($li['avg_discomfort']) ? (float) $li['avg_discomfort'] : null,
                'avg_fatigue_30d' => isset($li['avg_fatigue']) ? (float) $li['avg_fatigue'] : null,
                'avg_micro_breaks_30d' => isset($li['avg_micro_breaks']) ? (float) $li['avg_micro_breaks'] : null,
                'high_psychosocial_count_30d' => (int) ($li['high_psychosocial_count'] ?? 0),
            ];
        }

        return [
            'dashboard_mode'     => 'worker',
            'role'               => 'worker',
            'can_create_scan'    => true,
            'can_manage_org'     => false,
            'can_rate_scans'     => false,
            'kpi_labels'         => [
                'total_scans' => 'My Scans',
                'high_risk' => 'My High Risk',
                'moderate_risk' => 'My Moderate Risk',
                'avg_score' => 'My Avg Risk Score',
            ],
            'total_scans'        => (int)   ($totals['total_scans']   ?? 0),
            'high_risk'          => (int)   ($totals['high_risk']     ?? 0),
            'moderate_risk'      => (int)   ($totals['moderate_risk'] ?? 0),
            'avg_score'          => isset($totals['avg_score']) ? (float) $totals['avg_score'] : null,
            'recent_scans'       => $recentScans,
            'top_tasks'          => $topTasks,
            'weekly_trends'      => $weeklyTrends,
            'department_heatmap' => [],
            'leading_indicators' => $leadingIndicators,
        ];
    }

    private function observerSummary(int $organizationId, int $userId): array
    {
        $ratings = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) AS total_ratings_30d,
                ROUND(AVG(observer_score), 1) AS avg_observer_score_30d
             FROM observer_ratings r
             JOIN scans s ON s.id = r.scan_id
             WHERE r.observer_id = :user_id
               AND s.organization_id = :org_id
               AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            ['org_id' => $organizationId, 'user_id' => $userId]
        ) ?: [];

        $pending = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS pending_ratings
             FROM scans s
             LEFT JOIN observer_ratings r
               ON r.scan_id = s.id AND r.observer_id = :user_id
             WHERE s.organization_id = :org_id
               AND s.status = "completed"
               AND r.id IS NULL',
            ['org_id' => $organizationId, 'user_id' => $userId]
        ) ?: [];

        $recentScans = $this->db->fetchAllAssociative(
            'SELECT s.id, s.scan_type, s.normalized_score, s.risk_category, s.status, s.created_at,
                    t.name AS task_name,
                    r.id AS my_rating_id
             FROM scans s
             LEFT JOIN tasks t ON t.id = s.task_id
             LEFT JOIN observer_ratings r ON r.scan_id = s.id AND r.observer_id = :user_id
             WHERE s.organization_id = :org_id
             ORDER BY s.id DESC
             LIMIT 8',
            ['org_id' => $organizationId, 'user_id' => $userId]
        );

        $recentRatings = $this->db->fetchAllAssociative(
            'SELECT r.id, r.scan_id, r.observer_score, r.observer_category, r.created_at,
                    t.name AS task_name
             FROM observer_ratings r
             JOIN scans s ON s.id = r.scan_id
             LEFT JOIN tasks t ON t.id = s.task_id
             WHERE r.observer_id = :user_id
               AND s.organization_id = :org_id
             ORDER BY r.id DESC
             LIMIT 5',
            ['org_id' => $organizationId, 'user_id' => $userId]
        );

        return [
            'dashboard_mode'     => 'observer',
            'role'               => 'observer',
            'can_create_scan'    => false,
            'can_manage_org'     => false,
            'can_rate_scans'     => true,
            'kpi_labels'         => [
                'total_scans' => 'My Ratings (30d)',
                'high_risk' => 'Pending Ratings',
                'moderate_risk' => 'Rated Recent Scans',
                'avg_score' => 'Avg Observer Score',
            ],
            'total_scans'        => (int) ($ratings['total_ratings_30d'] ?? 0),
            'high_risk'          => (int) ($pending['pending_ratings'] ?? 0),
            'moderate_risk'      => count(array_filter($recentScans, static fn (array $scan): bool => isset($scan['my_rating_id']) && $scan['my_rating_id'] !== null)),
            'avg_score'          => isset($ratings['avg_observer_score_30d']) ? (float) $ratings['avg_observer_score_30d'] : null,
            'recent_scans'       => $recentScans,
            'recent_ratings'     => $recentRatings,
            'top_tasks'          => [],
            'weekly_trends'      => [],
            'department_heatmap' => [],
            'leading_indicators' => [
                'total_checkins_30d' => 0,
                'avg_discomfort_30d' => null,
                'avg_fatigue_30d' => null,
                'avg_micro_breaks_30d' => null,
                'high_psychosocial_count_30d' => 0,
            ],
        ];
    }

    private function hasLeadingIndicatorsTable(): bool
    {
        if ($this->hasLeadingIndicatorsTable !== null) {
            return $this->hasLeadingIndicatorsTable;
        }

        $tables = $this->db->createSchemaManager()->listTableNames();
        $this->hasLeadingIndicatorsTable = in_array('worker_leading_indicators', $tables, true);

        return $this->hasLeadingIndicatorsTable;
    }
}
