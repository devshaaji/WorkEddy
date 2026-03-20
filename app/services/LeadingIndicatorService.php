<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Repositories\LeadingIndicatorRepository;

final class LeadingIndicatorService
{
    public function __construct(private readonly LeadingIndicatorRepository $indicators) {}

    /**
     * @param array<string,mixed> $payload
     */
    public function submit(int $organizationId, int $userId, array $payload): array
    {
        $normalized = $this->normalizePayload($payload);
        $id = $this->indicators->create($organizationId, $userId, $normalized);

        return [
            'id' => $id,
            'organization_id' => $organizationId,
            'user_id' => $userId,
        ] + $normalized;
    }

    public function summary(int $organizationId, int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $summary = $this->indicators->summaryByOrganization($organizationId, $days);

        return [
            'window_days' => $days,
            'total_checkins' => (int) ($summary['total_checkins'] ?? 0),
            'avg_discomfort' => isset($summary['avg_discomfort']) ? (float) $summary['avg_discomfort'] : null,
            'avg_fatigue' => isset($summary['avg_fatigue']) ? (float) $summary['avg_fatigue'] : null,
            'avg_micro_breaks' => isset($summary['avg_micro_breaks']) ? (float) $summary['avg_micro_breaks'] : null,
            'avg_recovery_minutes' => isset($summary['avg_recovery_minutes']) ? (float) $summary['avg_recovery_minutes'] : null,
            'avg_overtime_minutes' => isset($summary['avg_overtime_minutes']) ? (float) $summary['avg_overtime_minutes'] : null,
            'pre_shift_count' => (int) ($summary['pre_shift_count'] ?? 0),
            'mid_shift_count' => (int) ($summary['mid_shift_count'] ?? 0),
            'post_shift_count' => (int) ($summary['post_shift_count'] ?? 0),
            'high_psychosocial_count' => (int) ($summary['high_psychosocial_count'] ?? 0),
            'poor_rotation_count' => (int) ($summary['poor_rotation_count'] ?? 0),
            'recent_entries' => $this->indicators->recentByOrganization($organizationId, min($days, 14)),
        ];
    }

    public function mine(int $organizationId, int $userId, int $days = 14): array
    {
        $days = max(1, min(90, $days));

        return [
            'window_days' => $days,
            'entries' => $this->indicators->recentByUser($organizationId, $userId, $days),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $taskId = isset($payload['task_id']) && $payload['task_id'] !== '' ? (int) $payload['task_id'] : null;
        $checkinType = strtolower(trim((string) ($payload['checkin_type'] ?? 'post_shift')));
        if (!in_array($checkinType, ['pre_shift', 'mid_shift', 'post_shift'], true)) {
            throw new RuntimeException('checkin_type must be one of: pre_shift, mid_shift, post_shift');
        }

        $shiftDate = trim((string) ($payload['shift_date'] ?? ''));
        if ($shiftDate === '') {
            $shiftDate = (new \DateTimeImmutable('now'))->format('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
            throw new RuntimeException('shift_date must be in YYYY-MM-DD format');
        }

        $discomfort = $this->boundInt($payload['discomfort_level'] ?? null, 0, 10, 'discomfort_level');
        $fatigue = $this->boundInt($payload['fatigue_level'] ?? null, 0, 10, 'fatigue_level');
        $microBreaks = $this->boundInt($payload['micro_breaks_taken'] ?? 0, 0, 100, 'micro_breaks_taken');
        $recovery = $this->boundInt($payload['recovery_minutes'] ?? 0, 0, 1440, 'recovery_minutes');
        $overtime = $this->boundInt($payload['overtime_minutes'] ?? 0, 0, 1440, 'overtime_minutes');

        $rotation = strtolower(trim((string) ($payload['task_rotation_quality'] ?? 'fair')));
        if (!in_array($rotation, ['poor', 'fair', 'good'], true)) {
            throw new RuntimeException('task_rotation_quality must be one of: poor, fair, good');
        }

        $psychosocial = strtolower(trim((string) ($payload['psychosocial_load'] ?? 'moderate')));
        if (!in_array($psychosocial, ['low', 'moderate', 'high'], true)) {
            throw new RuntimeException('psychosocial_load must be one of: low, moderate, high');
        }

        $notes = trim((string) ($payload['notes'] ?? ''));
        if (strlen($notes) > 2000) {
            throw new RuntimeException('notes must be 2000 characters or less');
        }

        return [
            'task_id' => $taskId,
            'checkin_type' => $checkinType,
            'shift_date' => $shiftDate,
            'discomfort_level' => $discomfort,
            'fatigue_level' => $fatigue,
            'micro_breaks_taken' => $microBreaks,
            'recovery_minutes' => $recovery,
            'overtime_minutes' => $overtime,
            'task_rotation_quality' => $rotation,
            'psychosocial_load' => $psychosocial,
            'notes' => $notes === '' ? null : $notes,
        ];
    }

    private function boundInt(mixed $value, int $min, int $max, string $field): int
    {
        if ($value === null || $value === '') {
            throw new RuntimeException("{$field} is required");
        }

        if (!is_numeric($value)) {
            throw new RuntimeException("{$field} must be numeric");
        }

        $num = (int) $value;
        if ($num < $min || $num > $max) {
            throw new RuntimeException("{$field} must be between {$min} and {$max}");
        }

        return $num;
    }
}
