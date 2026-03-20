<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class ControlActionRepository
{
    public function __construct(private readonly Connection $db) {}

    /**
     * @param array<string,mixed> $payload
     */
    public function create(array $payload): int
    {
        $this->db->executeStatement(
            'INSERT INTO control_actions (
                organization_id, source_scan_id, source_control_id,
                control_code, control_title, hierarchy_level, control_type,
                assigned_to_user_id, created_by_user_id, status, priority,
                target_due_date, implementation_notes, created_at, updated_at
             ) VALUES (
                :organization_id, :source_scan_id, :source_control_id,
                :control_code, :control_title, :hierarchy_level, :control_type,
                :assigned_to_user_id, :created_by_user_id, :status, :priority,
                :target_due_date, :implementation_notes, NOW(), NOW()
             )',
            [
                'organization_id' => (int) $payload['organization_id'],
                'source_scan_id' => (int) $payload['source_scan_id'],
                'source_control_id' => $payload['source_control_id'],
                'control_code' => (string) $payload['control_code'],
                'control_title' => (string) $payload['control_title'],
                'hierarchy_level' => (string) $payload['hierarchy_level'],
                'control_type' => (string) $payload['control_type'],
                'assigned_to_user_id' => $payload['assigned_to_user_id'],
                'created_by_user_id' => (int) $payload['created_by_user_id'],
                'status' => (string) $payload['status'],
                'priority' => (string) $payload['priority'],
                'target_due_date' => $payload['target_due_date'],
                'implementation_notes' => $payload['implementation_notes'],
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $organizationId, int $actionId): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT ca.*,
                    assignee.name AS assigned_to_name,
                    creator.name AS created_by_name
             FROM control_actions ca
             LEFT JOIN users assignee ON assignee.id = ca.assigned_to_user_id
             LEFT JOIN users creator ON creator.id = ca.created_by_user_id
             WHERE ca.organization_id = :org_id
               AND ca.id = :id
             LIMIT 1',
            [
                'org_id' => $organizationId,
                'id' => $actionId,
            ]
        );

        if (!$row) {
            throw new RuntimeException('Control action not found');
        }

        return $this->decodeRow($row);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listByOrganization(
        int $organizationId,
        ?int $scanId = null,
        ?string $status = null,
        ?int $assigneeId = null,
        int $limit = 100
    ): array {
        $sql = 'SELECT ca.*,
                       assignee.name AS assigned_to_name,
                       creator.name AS created_by_name
                FROM control_actions ca
                LEFT JOIN users assignee ON assignee.id = ca.assigned_to_user_id
                LEFT JOIN users creator ON creator.id = ca.created_by_user_id
                WHERE ca.organization_id = :org_id';
        $params = ['org_id' => $organizationId];

        if ($scanId !== null && $scanId > 0) {
            $sql .= ' AND ca.source_scan_id = :scan_id';
            $params['scan_id'] = $scanId;
        }

        if ($status !== null && $status !== '') {
            $sql .= ' AND ca.status = :status';
            $params['status'] = $status;
        }

        if ($assigneeId !== null && $assigneeId > 0) {
            $sql .= ' AND ca.assigned_to_user_id = :assignee_id';
            $params['assignee_id'] = $assigneeId;
        }

        $sql .= ' ORDER BY ca.id DESC LIMIT ' . max(1, min(500, $limit));

        $rows = $this->db->fetchAllAssociative($sql, $params);
        foreach ($rows as &$row) {
            $row = $this->decodeRow($row);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string,mixed> $fields
     */
    public function updateFields(int $organizationId, int $actionId, array $fields): void
    {
        $allowed = [
            'assigned_to_user_id',
            'status',
            'priority',
            'target_due_date',
            'implementation_notes',
            'implemented_at',
            'updated_at',
        ];

        $set = [];
        $params = [
            'org_id' => $organizationId,
            'id' => $actionId,
        ];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $fields)) {
                continue;
            }
            $set[] = "{$column} = :{$column}";
            $params[$column] = $fields[$column];
        }

        if (!array_key_exists('updated_at', $fields)) {
            $set[] = 'updated_at = NOW()';
        }

        if ($set === []) {
            return;
        }

        $affected = $this->db->executeStatement(
            'UPDATE control_actions
             SET ' . implode(', ', $set) . '
             WHERE organization_id = :org_id
               AND id = :id',
            $params
        );

        if ($affected === 0) {
            throw new RuntimeException('Control action not found');
        }
    }

    /**
     * @param array<string,mixed> $workerFeedback
     * @param array<string,mixed> $verificationSummary
     */
    public function markVerified(
        int $organizationId,
        int $actionId,
        int $verificationScanId,
        array $workerFeedback,
        array $verificationSummary
    ): void {
        $affected = $this->db->executeStatement(
            'UPDATE control_actions
             SET verification_scan_id = :verification_scan_id,
                 worker_feedback_json = :worker_feedback_json,
                 verification_summary_json = :verification_summary_json,
                 status = "verified",
                 verified_at = NOW(),
                 updated_at = NOW()
             WHERE organization_id = :org_id
               AND id = :id',
            [
                'verification_scan_id' => $verificationScanId,
                'worker_feedback_json' => json_encode($workerFeedback, JSON_UNESCAPED_UNICODE),
                'verification_summary_json' => json_encode($verificationSummary, JSON_UNESCAPED_UNICODE),
                'org_id' => $organizationId,
                'id' => $actionId,
            ]
        );

        if ($affected === 0) {
            throw new RuntimeException('Control action not found');
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decodeRow(array $row): array
    {
        $row['worker_feedback'] = [];
        $rawFeedback = $row['worker_feedback_json'] ?? null;
        if (is_string($rawFeedback) && $rawFeedback !== '') {
            $decoded = json_decode($rawFeedback, true);
            if (is_array($decoded)) {
                $row['worker_feedback'] = $decoded;
            }
        }

        $row['verification_summary'] = [];
        $rawSummary = $row['verification_summary_json'] ?? null;
        if (is_string($rawSummary) && $rawSummary !== '') {
            $decoded = json_decode($rawSummary, true);
            if (is_array($decoded)) {
                $row['verification_summary'] = $decoded;
            }
        }

        unset($row['worker_feedback_json'], $row['verification_summary_json']);
        return $row;
    }
}

