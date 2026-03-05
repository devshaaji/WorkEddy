<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class ScanService
{
    public function __construct(
        private Connection $db,
        private RiskScoringService $riskScoring,
        private QueueService $queueService,
        private BillingService $billingService,
    ) {
    }

    public function createManualScan(int $organizationId, int $userId, int $taskId, array $input): array
    {
        $this->billingService->assertScanAvailable($organizationId);

        $required = ['weight', 'frequency', 'duration', 'trunk_angle_estimate', 'twisting', 'overhead', 'repetition'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                throw new RuntimeException("Missing field: {$field}");
            }
        }

        $score = $this->riskScoring->scoreManual($input);

        return $this->db->transactional(function () use ($organizationId, $userId, $taskId, $input, $score): array {
            $this->db->executeStatement(
                'INSERT INTO scans (organization_id, user_id, task_id, scan_type, raw_score, normalized_score, risk_category, status, created_at) VALUES (:organization_id, :user_id, :task_id, :scan_type, :raw_score, :normalized_score, :risk_category, :status, NOW())',
                ['organization_id' => $organizationId, 'user_id' => $userId, 'task_id' => $taskId, 'scan_type' => 'manual', 'raw_score' => $score['raw_score'], 'normalized_score' => $score['normalized_score'], 'risk_category' => $score['risk_category'], 'status' => 'completed']
            );
            $scanId = (int) $this->db->lastInsertId();

            $this->db->executeStatement(
                'INSERT INTO manual_inputs (scan_id, weight, frequency, duration, trunk_angle_estimate, twisting, overhead, repetition) VALUES (:scan_id, :weight, :frequency, :duration, :trunk_angle_estimate, :twisting, :overhead, :repetition)',
                ['scan_id' => $scanId, 'weight' => (float) $input['weight'], 'frequency' => (float) $input['frequency'], 'duration' => (float) $input['duration'], 'trunk_angle_estimate' => (float) $input['trunk_angle_estimate'], 'twisting' => (int) ((bool) $input['twisting']), 'overhead' => (int) ((bool) $input['overhead']), 'repetition' => (float) $input['repetition']]
            );

            $this->db->executeStatement(
                'INSERT INTO usage_records (organization_id, scan_id, usage_type, created_at) VALUES (:organization_id, :scan_id, :usage_type, NOW())',
                ['organization_id' => $organizationId, 'scan_id' => $scanId, 'usage_type' => 'manual_scan']
            );

            return $this->getById($organizationId, $scanId);
        });
    }

    public function createVideoScan(int $organizationId, int $userId, int $taskId, string $videoPath): array
    {
        $this->billingService->assertScanAvailable($organizationId);

        return $this->db->transactional(function () use ($organizationId, $userId, $taskId, $videoPath): array {
            $this->db->executeStatement(
                'INSERT INTO scans (organization_id, user_id, task_id, scan_type, raw_score, normalized_score, risk_category, status, video_path, created_at) VALUES (:organization_id, :user_id, :task_id, :scan_type, :raw_score, :normalized_score, :risk_category, :status, :video_path, NOW())',
                ['organization_id' => $organizationId, 'user_id' => $userId, 'task_id' => $taskId, 'scan_type' => 'video', 'raw_score' => 0, 'normalized_score' => 0, 'risk_category' => 'low', 'status' => 'processing', 'video_path' => $videoPath]
            );
            $scanId = (int) $this->db->lastInsertId();

            $this->queueService->enqueueScanJob([
                'scan_id' => $scanId,
                'organization_id' => $organizationId,
                'task_id' => $taskId,
                'video_path' => $videoPath,
            ]);

            return $this->getById($organizationId, $scanId);
        });
    }

    public function getById(int $organizationId, int $scanId): array
    {
        $scan = $this->db->fetchAssociative(
            'SELECT s.id, s.organization_id, s.user_id, s.task_id, s.scan_type, s.raw_score, s.normalized_score, s.risk_category, s.parent_scan_id, s.status, s.video_path, s.created_at, mi.weight, mi.frequency, mi.duration, mi.trunk_angle_estimate, mi.twisting, mi.overhead, mi.repetition, vm.max_trunk_angle, vm.avg_trunk_angle, vm.shoulder_elevation_duration, vm.repetition_count, vm.processing_confidence FROM scans s LEFT JOIN manual_inputs mi ON mi.scan_id = s.id LEFT JOIN video_metrics vm ON vm.scan_id = s.id WHERE s.organization_id = :organization_id AND s.id = :id LIMIT 1',
            ['organization_id' => $organizationId, 'id' => $scanId]
        );
        if (!$scan) {
            throw new RuntimeException('Scan not found');
        }
        return $scan;
    }

    public function listByOrganization(int $organizationId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, organization_id, user_id, task_id, scan_type, raw_score, normalized_score, risk_category, status, video_path, created_at FROM scans WHERE organization_id = :organization_id ORDER BY id DESC',
            ['organization_id' => $organizationId]
        );
    }
}
