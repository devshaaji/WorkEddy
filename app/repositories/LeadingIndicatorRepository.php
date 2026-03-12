<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;

final class LeadingIndicatorRepository
{
    public function __construct(private readonly Connection $db) {}

    /**
     * @param array<string,mixed> $payload
     */
    public function create(int $organizationId, int $userId, array $payload): int
    {
        $this->db->executeStatement(
            'INSERT INTO worker_leading_indicators (
                organization_id, user_id, task_id, checkin_type, shift_date,
                discomfort_level, fatigue_level, micro_breaks_taken,
                recovery_minutes, overtime_minutes, task_rotation_quality,
                psychosocial_load, notes, created_at
            ) VALUES (
                :org_id, :user_id, :task_id, :checkin_type, :shift_date,
                :discomfort_level, :fatigue_level, :micro_breaks_taken,
                :recovery_minutes, :overtime_minutes, :task_rotation_quality,
                :psychosocial_load, :notes, NOW()
            )',
            [
                'org_id' => $organizationId,
                'user_id' => $userId,
                'task_id' => $payload['task_id'],
                'checkin_type' => $payload['checkin_type'],
                'shift_date' => $payload['shift_date'],
                'discomfort_level' => $payload['discomfort_level'],
                'fatigue_level' => $payload['fatigue_level'],
                'micro_breaks_taken' => $payload['micro_breaks_taken'],
                'recovery_minutes' => $payload['recovery_minutes'],
                'overtime_minutes' => $payload['overtime_minutes'],
                'task_rotation_quality' => $payload['task_rotation_quality'],
                'psychosocial_load' => $payload['psychosocial_load'],
                'notes' => $payload['notes'],
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function recentByOrganization(int $organizationId, int $days = 30): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, organization_id, user_id, task_id, checkin_type, shift_date,
                    discomfort_level, fatigue_level, micro_breaks_taken,
                    recovery_minutes, overtime_minutes, task_rotation_quality,
                    psychosocial_load, notes, created_at
             FROM worker_leading_indicators
             WHERE organization_id = :org_id
               AND shift_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             ORDER BY shift_date DESC, id DESC',
            ['org_id' => $organizationId, 'days' => max(1, $days)]
        );
    }

    public function recentByUser(int $organizationId, int $userId, int $days = 30): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, organization_id, user_id, task_id, checkin_type, shift_date,
                    discomfort_level, fatigue_level, micro_breaks_taken,
                    recovery_minutes, overtime_minutes, task_rotation_quality,
                    psychosocial_load, notes, created_at
             FROM worker_leading_indicators
             WHERE organization_id = :org_id
               AND user_id = :user_id
               AND shift_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
             ORDER BY shift_date DESC, id DESC',
            [
                'org_id' => $organizationId,
                'user_id' => $userId,
                'days' => max(1, $days),
            ]
        );
    }

    public function summaryByOrganization(int $organizationId, int $days = 30): array
    {
        return $this->db->fetchAssociative(
            'SELECT
                COUNT(*) AS total_checkins,
                ROUND(AVG(discomfort_level), 2) AS avg_discomfort,
                ROUND(AVG(fatigue_level), 2) AS avg_fatigue,
                ROUND(AVG(micro_breaks_taken), 2) AS avg_micro_breaks,
                ROUND(AVG(recovery_minutes), 2) AS avg_recovery_minutes,
                ROUND(AVG(overtime_minutes), 2) AS avg_overtime_minutes,
                SUM(CASE WHEN checkin_type = "pre_shift" THEN 1 ELSE 0 END) AS pre_shift_count,
                SUM(CASE WHEN checkin_type = "mid_shift" THEN 1 ELSE 0 END) AS mid_shift_count,
                SUM(CASE WHEN checkin_type = "post_shift" THEN 1 ELSE 0 END) AS post_shift_count,
                SUM(CASE WHEN psychosocial_load = "high" THEN 1 ELSE 0 END) AS high_psychosocial_count,
                SUM(CASE WHEN task_rotation_quality = "poor" THEN 1 ELSE 0 END) AS poor_rotation_count
             FROM worker_leading_indicators
             WHERE organization_id = :org_id
               AND shift_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)',
            ['org_id' => $organizationId, 'days' => max(1, $days)]
        ) ?: [];
    }

    public function latestByUser(int $organizationId, int $userId): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, organization_id, user_id, task_id, checkin_type, shift_date,
                    discomfort_level, fatigue_level, micro_breaks_taken,
                    recovery_minutes, overtime_minutes, task_rotation_quality,
                    psychosocial_load, notes, created_at
             FROM worker_leading_indicators
             WHERE organization_id = :org_id
               AND user_id = :user_id
             ORDER BY shift_date DESC, id DESC
             LIMIT 1',
            [
                'org_id' => $organizationId,
                'user_id' => $userId,
            ]
        );

        return $row ?: null;
    }
}
