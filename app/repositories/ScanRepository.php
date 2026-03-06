<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class ScanRepository
{
    public function __construct(private readonly Connection $db) {}

    public function findById(int $organizationId, int $scanId): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT s.*,
                    sr.score   AS result_score,
                    sr.risk_level,
                    sr.recommendation
             FROM scans s
             LEFT JOIN scan_results sr ON sr.scan_id = s.id
             WHERE s.organization_id = :org_id AND s.id = :id LIMIT 1',
            ['org_id' => $organizationId, 'id' => $scanId]
        );
        if (!$row) {
            throw new RuntimeException('Scan not found');
        }

        // Attach metrics as a nested array
        $metrics = $this->db->fetchAssociative(
            'SELECT * FROM scan_metrics WHERE scan_id = :id LIMIT 1',
            ['id' => $scanId]
        );
        $row['metrics'] = $metrics ?: [];

        return $row;
    }

    public function listByOrganization(int $organizationId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT s.id, s.organization_id, s.user_id, s.task_id, s.scan_type, s.model,
                    s.raw_score, s.normalized_score, s.risk_category,
                    s.status, s.video_path, s.created_at,
                    sr.score AS result_score, sr.risk_level, sr.recommendation
             FROM scans s
             LEFT JOIN scan_results sr ON sr.scan_id = s.id
             WHERE s.organization_id = :org_id
             ORDER BY s.id DESC',
            ['org_id' => $organizationId]
        );
    }

    /**
     * Create a manual scan with the new schema (scan_metrics + scan_results).
     */
    public function createManual(int $organizationId, int $userId, int $taskId, string $model, array $score, array $metrics): int
    {
        return $this->db->transactional(function () use ($organizationId, $userId, $taskId, $model, $score, $metrics): int {
            $this->db->executeStatement(
                'INSERT INTO scans (organization_id, user_id, task_id, scan_type, model, raw_score, normalized_score, risk_category, status, created_at)
                 VALUES (:org_id, :user_id, :task_id, "manual", :model, :raw, :norm, :cat, "completed", NOW())',
                ['org_id' => $organizationId, 'user_id' => $userId, 'task_id' => $taskId, 'model' => $model,
                 'raw' => $score['raw_score'], 'norm' => $score['normalized_score'], 'cat' => $score['risk_category']]
            );
            $scanId = (int) $this->db->lastInsertId();

            $this->insertMetrics($scanId, $metrics);
            $this->insertResult($scanId, $model, $score);

            $this->db->executeStatement(
                'INSERT INTO usage_records (organization_id, scan_id, usage_type, created_at) VALUES (:org_id, :scan_id, "manual_scan", NOW())',
                ['org_id' => $organizationId, 'scan_id' => $scanId]
            );

            return $scanId;
        });
    }

    /**
     * Create a video scan (status = processing). Worker completes it later.
     */
    public function createVideo(int $organizationId, int $userId, int $taskId, string $model, string $videoPath, ?int $parentScanId): int
    {
        return $this->db->transactional(function () use ($organizationId, $userId, $taskId, $model, $videoPath, $parentScanId): int {
            $this->db->executeStatement(
                'INSERT INTO scans (organization_id, user_id, task_id, scan_type, model, raw_score, normalized_score, risk_category, parent_scan_id, status, video_path, created_at)
                 VALUES (:org_id, :user_id, :task_id, "video", :model, 0, 0, "low", :parent, "processing", :video_path, NOW())',
                ['org_id' => $organizationId, 'user_id' => $userId, 'task_id' => $taskId, 'model' => $model,
                 'parent' => $parentScanId, 'video_path' => $videoPath]
            );
            return (int) $this->db->lastInsertId();
        });
    }

    /**
     * Insert metrics into the unified scan_metrics table.
     */
    public function insertMetrics(int $scanId, array $metrics): void
    {
        $columns = [
            'neck_angle', 'trunk_angle', 'upper_arm_angle', 'lower_arm_angle', 'wrist_angle', 'leg_score',
            'load_weight', 'horizontal_distance', 'vertical_start', 'vertical_travel', 'twist_angle', 'frequency', 'coupling',
            'shoulder_elevation_duration', 'repetition_count', 'processing_confidence',
        ];

        $values = ['scan_id' => $scanId];
        $placeholders = [':scan_id'];
        $cols = ['scan_id'];

        foreach ($columns as $col) {
            if (isset($metrics[$col]) && $metrics[$col] !== '' && $metrics[$col] !== null) {
                $cols[] = $col;
                $placeholders[] = ':' . $col;
                $values[$col] = $metrics[$col];
            }
        }

        if (count($cols) > 1) {
            $this->db->executeStatement(
                'INSERT INTO scan_metrics (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')',
                $values
            );
        }
    }

    /**
     * Insert assessment result into scan_results.
     */
    public function insertResult(int $scanId, string $model, array $score): void
    {
        $this->db->executeStatement(
            'INSERT INTO scan_results (scan_id, model, score, risk_level, recommendation, created_at)
             VALUES (:scan_id, :model, :score, :risk_level, :recommendation, NOW())',
            [
                'scan_id'        => $scanId,
                'model'          => $model,
                'score'          => $score['score'] ?? $score['raw_score'],
                'risk_level'     => $score['risk_level'] ?? $score['risk_category'],
                'recommendation' => $score['recommendation'] ?? '',
            ]
        );
    }
}