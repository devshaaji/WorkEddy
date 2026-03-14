<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use DateTimeInterface;
use Doctrine\DBAL\Connection;
use RuntimeException;

final class LiveSessionRepository
{
    public function __construct(private readonly Connection $db) {}

    /**
     * Create a new live session record.
     *
     * @return int The new session ID.
     */
    public function create(array $data): int
    {
        $this->db->insert('live_sessions', [
            'organization_id'   => $data['organization_id'],
            'user_id'           => $data['user_id'],
            'task_id'           => $data['task_id'],
            'model'             => $data['model'],
            'pose_engine'       => $data['pose_engine'],
            'status'            => 'active',
            'target_fps'        => $data['target_fps'],
            'batch_window_ms'   => $data['batch_window_ms'],
            'max_e2e_latency_ms' => $data['max_e2e_latency_ms'],
            'started_at'        => $data['started_at'],
            'created_at'        => $data['created_at'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Find a live session by ID scoped to organization.
     */
    public function findById(int $organizationId, int $sessionId): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM live_sessions
             WHERE id = :id AND organization_id = :org_id
             LIMIT 1',
            ['id' => $sessionId, 'org_id' => $organizationId]
        );

        if (!$row) {
            throw new RuntimeException('Live session not found');
        }

        return $row;
    }

    /**
     * List active sessions for a given organization.
     */
    public function listByOrganization(int $organizationId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM live_sessions WHERE organization_id = :org_id';
        $params = ['org_id' => $organizationId];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY created_at DESC';

        return $this->db->fetchAllAssociative($sql, $params);
    }

    /**
     * Count currently open sessions (active + paused) across all organizations.
     */
    public function countOpenSessions(): int
    {
        $row = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS cnt
             FROM live_sessions
             WHERE status IN ("active", "paused")'
        );

        if (!is_array($row)) {
            return 0;
        }

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count open sessions (active + paused) for one organization.
     */
    public function countOpenSessionsByOrganization(int $organizationId): int
    {
        $row = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS cnt
             FROM live_sessions
             WHERE organization_id = :org_id
               AND status IN ("active", "paused")',
            ['org_id' => $organizationId]
        );

        if (!is_array($row)) {
            return 0;
        }

        return (int) ($row['cnt'] ?? 0);
    }

    public function countStartedSessionsForPeriod(
        int $organizationId,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd
    ): int {
        $row = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS cnt
             FROM live_sessions
             WHERE organization_id = :org_id
               AND started_at >= :period_start
               AND started_at < :period_end',
            [
                'org_id' => $organizationId,
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
                'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        return (int) ($row['cnt'] ?? 0);
    }

    public function sumSessionMinutesForPeriod(
        int $organizationId,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd,
        ?DateTimeInterface $now = null
    ): int {
        $now ??= new \DateTimeImmutable('now');

        $row = $this->db->fetchAssociative(
            'SELECT COALESCE(SUM(
                GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        GREATEST(started_at, :period_start),
                        LEAST(COALESCE(completed_at, :now), :period_end)
                    )
                )
             ), 0) AS seconds_used
             FROM live_sessions
             WHERE organization_id = :org_id
               AND started_at < :period_end
               AND COALESCE(completed_at, :now) > :period_start',
            [
                'org_id' => $organizationId,
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
                'period_end' => $periodEnd->format('Y-m-d H:i:s'),
                'now' => $now->format('Y-m-d H:i:s'),
            ]
        );

        $seconds = (int) ($row['seconds_used'] ?? 0);
        if ($seconds <= 0) {
            return 0;
        }

        return (int) ceil($seconds / 60);
    }

    /**
     * Update session status.
     */
    public function updateStatus(int $sessionId, string $status, ?string $errorMessage = null): void
    {
        $data = [
            'status'     => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if ($status === 'completed') {
            $data['completed_at'] = gmdate('Y-m-d H:i:s');
        }

        if ($errorMessage !== null) {
            $data['error_message'] = $errorMessage;
        }

        $this->db->update('live_sessions', $data, ['id' => $sessionId]);
    }

    /**
     * Record a batch of analysed frames for a session.
     */
    public function insertFrames(int $sessionId, array $frames): void
    {
        foreach ($frames as $frame) {
            $this->db->insert('live_session_frames', [
                'session_id'     => $sessionId,
                'frame_number'   => $frame['frame_number'],
                'metrics_json'   => json_encode($frame['metrics']),
                'trunk_angle'    => $frame['metrics']['trunk_angle'] ?? null,
                'neck_angle'     => $frame['metrics']['neck_angle'] ?? null,
                'upper_arm_angle' => $frame['metrics']['upper_arm_angle'] ?? null,
                'lower_arm_angle' => $frame['metrics']['lower_arm_angle'] ?? null,
                'wrist_angle'    => $frame['metrics']['wrist_angle'] ?? null,
                'confidence'     => $frame['metrics']['confidence'] ?? null,
                'latency_ms'     => $frame['latency_ms'] ?? null,
            ]);
        }
    }

    /**
     * Increment analysed-frame counter and update average latency.
     */
    public function updateFrameStats(int $sessionId, int $newFrames, float $avgLatency): void
    {
        $this->db->executeStatement(
            'UPDATE live_sessions
             SET analysed_frame_count = analysed_frame_count + :new_frames,
                 avg_latency_ms = :avg_latency,
                 updated_at = :now
             WHERE id = :id',
            [
                'new_frames'  => $newFrames,
                'avg_latency' => $avgLatency,
                'now'         => gmdate('Y-m-d H:i:s'),
                'id'          => $sessionId,
            ]
        );
    }

    public function incrementCapturedFrameCount(int $sessionId, int $newFrames): void
    {
        $this->db->executeStatement(
            'UPDATE live_sessions
             SET frame_count = frame_count + :new_frames,
                 updated_at = :now
             WHERE id = :id',
            [
                'new_frames' => $newFrames,
                'now' => gmdate('Y-m-d H:i:s'),
                'id' => $sessionId,
            ]
        );
    }

    /**
     * Store telemetry JSON for a live session.
     *
     * @param array<string,mixed> $telemetry
     */
    public function storeTelemetry(int $sessionId, array $telemetry): void
    {
        $this->db->update(
            'live_sessions',
            [
                'telemetry_json' => json_encode($telemetry, JSON_UNESCAPED_UNICODE),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $sessionId]
        );
    }

    /**
     * Store summary metrics JSON when a session is completed.
     */
    public function storeSummary(int $sessionId, array $summaryMetrics): void
    {
        $this->db->update(
            'live_sessions',
            [
                'summary_metrics_json' => json_encode($summaryMetrics),
                'updated_at'           => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $sessionId]
        );
    }

    /**
     * Fetch the next active session that a live-worker should process.
     */
    public function claimNextActiveSession(): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM live_sessions
             WHERE status = "active"
             ORDER BY created_at ASC
             LIMIT 1'
        );

        return $row ?: null;
    }

    /**
     * Get recent frames for a session (for real-time streaming to frontend).
     */
    public function getRecentFrames(int $sessionId, int $limit = 50): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT * FROM live_session_frames
             WHERE session_id = :session_id
             ORDER BY frame_number DESC
             LIMIT :lim',
            ['session_id' => $sessionId, 'lim' => $limit],
            ['session_id' => \Doctrine\DBAL\ParameterType::INTEGER, 'lim' => \Doctrine\DBAL\ParameterType::INTEGER]
        );
    }
}
