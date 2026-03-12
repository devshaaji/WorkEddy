<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Repositories\ControlActionRepository;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Repositories\UserRepository;

final class ControlActionService
{
    /** @var list<string> */
    private const VALID_STATUSES = ['planned', 'in_progress', 'implemented', 'verified', 'cancelled'];
    /** @var list<string> */
    private const VALID_PRIORITIES = ['low', 'medium', 'high'];

    /** @var array<string,list<string>> */
    private const STATUS_TRANSITIONS = [
        'planned' => ['planned', 'in_progress', 'cancelled'],
        'in_progress' => ['in_progress', 'implemented', 'cancelled'],
        'implemented' => ['implemented', 'cancelled'],
        'verified' => ['verified'],
        'cancelled' => ['cancelled'],
    ];

    public function __construct(
        private readonly ControlActionRepository $actions,
        private readonly ScanRepository $scans,
        private readonly UserRepository $users,
        private readonly ?ImprovementProofService $improvementProofs = null,
        private readonly ?ScanComparisonService $comparisons = null,
    ) {}

    /**
     * @param array<string,mixed> $payload
     */
    public function createFromControlRecommendation(
        int $organizationId,
        int $createdByUserId,
        int $sourceScanId,
        int $controlRecommendationId,
        array $payload
    ): array {
        $scan = $this->scans->findById($organizationId, $sourceScanId);
        $control = $this->findControl($scan, $controlRecommendationId);

        $assignedTo = $this->normalizeAssignee($organizationId, $payload['assigned_to_user_id'] ?? null);
        $priority = $this->normalizePriority($payload['priority'] ?? 'medium');
        $targetDueDate = $this->normalizeDate($payload['target_due_date'] ?? null);
        $notes = $this->normalizeNotes($payload['implementation_notes'] ?? null);

        $actionId = $this->actions->create([
            'organization_id' => $organizationId,
            'source_scan_id' => $sourceScanId,
            'source_control_id' => $controlRecommendationId,
            'control_code' => (string) ($control['control_code'] ?? ''),
            'control_title' => (string) ($control['title'] ?? ''),
            'hierarchy_level' => (string) ($control['hierarchy_level'] ?? 'administrative'),
            'control_type' => in_array((string) ($control['control_type'] ?? 'permanent'), ['permanent', 'interim'], true)
                ? (string) $control['control_type']
                : 'permanent',
            'assigned_to_user_id' => $assignedTo,
            'created_by_user_id' => $createdByUserId,
            'status' => 'planned',
            'priority' => $priority,
            'target_due_date' => $targetDueDate,
            'implementation_notes' => $notes,
        ]);

        return $this->actions->findById($organizationId, $actionId);
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function listByOrganization(int $organizationId, array $filters = []): array
    {
        $scanId = isset($filters['scan_id']) ? (int) $filters['scan_id'] : null;
        $status = isset($filters['status']) ? strtolower(trim((string) $filters['status'])) : null;
        $assigneeId = isset($filters['assignee_id']) ? (int) $filters['assignee_id'] : null;
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;

        if ($status !== null && $status !== '' && !in_array($status, self::VALID_STATUSES, true)) {
            throw new RuntimeException('Invalid control action status filter');
        }

        return $this->actions->listByOrganization(
            $organizationId,
            $scanId !== null && $scanId > 0 ? $scanId : null,
            $status !== '' ? $status : null,
            $assigneeId !== null && $assigneeId > 0 ? $assigneeId : null,
            $limit > 0 ? $limit : 100
        );
    }

    public function findById(int $organizationId, int $actionId): array
    {
        return $this->actions->findById($organizationId, $actionId);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function updateAction(int $organizationId, int $actionId, array $payload): array
    {
        $existing = $this->actions->findById($organizationId, $actionId);
        if ((string) ($existing['status'] ?? '') === 'verified') {
            throw new RuntimeException('Verified control actions are read-only');
        }

        $fields = [];

        if (array_key_exists('assigned_to_user_id', $payload)) {
            $fields['assigned_to_user_id'] = $this->normalizeAssignee($organizationId, $payload['assigned_to_user_id']);
        }

        if (array_key_exists('priority', $payload)) {
            $fields['priority'] = $this->normalizePriority($payload['priority']);
        }

        if (array_key_exists('target_due_date', $payload)) {
            $fields['target_due_date'] = $this->normalizeDate($payload['target_due_date']);
        }

        if (array_key_exists('implementation_notes', $payload)) {
            $fields['implementation_notes'] = $this->normalizeNotes($payload['implementation_notes']);
        }

        if (array_key_exists('status', $payload)) {
            $nextStatus = strtolower(trim((string) $payload['status']));
            if (!in_array($nextStatus, self::VALID_STATUSES, true)) {
                throw new RuntimeException('Invalid control action status');
            }

            $currentStatus = (string) ($existing['status'] ?? 'planned');
            $allowedTransitions = self::STATUS_TRANSITIONS[$currentStatus] ?? [$currentStatus];
            if (!in_array($nextStatus, $allowedTransitions, true)) {
                throw new RuntimeException("Cannot transition control action from {$currentStatus} to {$nextStatus}");
            }

            $fields['status'] = $nextStatus;
            if ($nextStatus === 'implemented' && empty($existing['implemented_at'])) {
                $fields['implemented_at'] = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            }
        }

        $this->actions->updateFields($organizationId, $actionId, $fields);
        return $this->actions->findById($organizationId, $actionId);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function verifyAction(int $organizationId, int $actionId, array $payload): array
    {
        $verificationScanId = (int) ($payload['verification_scan_id'] ?? 0);
        if ($verificationScanId <= 0) {
            throw new RuntimeException('verification_scan_id is required');
        }

        $action = $this->actions->findById($organizationId, $actionId);
        if ((string) ($action['status'] ?? '') === 'cancelled') {
            throw new RuntimeException('Cancelled actions cannot be verified');
        }
        if ((string) ($action['status'] ?? '') === 'verified') {
            throw new RuntimeException('Action is already verified');
        }

        $baselineScan = $this->scans->findById($organizationId, (int) ($action['source_scan_id'] ?? 0));
        $verificationScan = $this->scans->findById($organizationId, $verificationScanId);

        $nodes = [];
        if ($this->comparisons !== null) {
            try {
                $comparison = $this->comparisons->compare(
                    $organizationId,
                    (int) ($action['source_scan_id'] ?? 0),
                    $verificationScanId
                );
                $nodes = is_array($comparison['nodes'] ?? null) ? $comparison['nodes'] : [];
            } catch (\Throwable) {
                $nodes = [];
            }
        }

        $improvement = ($this->improvementProofs ?? new ImprovementProofService())->build(
            $baselineScan,
            $verificationScan,
            $nodes,
        );

        $workerFeedback = $this->normalizeWorkerFeedback($payload['worker_feedback'] ?? []);

        $verificationSummary = [
            'action_id' => $actionId,
            'source_scan_id' => (int) ($action['source_scan_id'] ?? 0),
            'verification_scan_id' => $verificationScanId,
            'control_code' => (string) ($action['control_code'] ?? ''),
            'control_title' => (string) ($action['control_title'] ?? ''),
            'improvement_proof' => $improvement,
            'worker_feedback_summary' => $this->summarizeWorkerFeedback($workerFeedback),
            'verified_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
        ];

        $this->actions->markVerified(
            $organizationId,
            $actionId,
            $verificationScanId,
            $workerFeedback,
            $verificationSummary
        );

        return $this->actions->findById($organizationId, $actionId);
    }

    /**
     * @param array<string,mixed> $scan
     * @return array<string,mixed>
     */
    private function findControl(array $scan, int $controlRecommendationId): array
    {
        $controls = is_array($scan['controls'] ?? null) ? $scan['controls'] : [];
        foreach ($controls as $control) {
            if (!is_array($control)) {
                continue;
            }
            if ((int) ($control['id'] ?? 0) === $controlRecommendationId) {
                return $control;
            }
        }

        throw new RuntimeException('Control recommendation not found for the source scan');
    }

    private function normalizePriority(mixed $value): string
    {
        $priority = strtolower(trim((string) $value));
        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            throw new RuntimeException('priority must be one of: low, medium, high');
        }
        return $priority;
    }

    private function normalizeAssignee(int $organizationId, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $assigneeId = (int) $value;
        if ($assigneeId <= 0) {
            return null;
        }

        $user = $this->users->findById($assigneeId);
        if (!$user || (int) ($user['organization_id'] ?? 0) !== $organizationId) {
            throw new RuntimeException('assigned_to_user_id must belong to the same organization');
        }

        return $assigneeId;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('target_due_date must be in YYYY-MM-DD format');
        }
        return $date;
    }

    private function normalizeNotes(mixed $value): ?string
    {
        $notes = trim((string) ($value ?? ''));
        if ($notes === '') {
            return null;
        }
        if (strlen($notes) > 3000) {
            throw new RuntimeException('implementation_notes must be 3000 characters or fewer');
        }
        return $notes;
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeWorkerFeedback(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $allowed = [
            'discomfort_before',
            'discomfort_after',
            'fatigue_before',
            'fatigue_after',
            'throughput_before',
            'throughput_after',
            'notes',
        ];

        $feedback = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }

            if ($key === 'notes') {
                $notes = trim((string) $raw[$key]);
                if ($notes !== '') {
                    $feedback[$key] = mb_substr($notes, 0, 2000);
                }
                continue;
            }

            if (!is_numeric($raw[$key])) {
                continue;
            }
            $feedback[$key] = (float) $raw[$key];
        }

        return $feedback;
    }

    /**
     * @param array<string,mixed> $feedback
     * @return array<string,mixed>
     */
    private function summarizeWorkerFeedback(array $feedback): array
    {
        $summary = ['available' => false];

        if (isset($feedback['discomfort_before'], $feedback['discomfort_after'])) {
            $summary['available'] = true;
            $summary['discomfort_delta'] = round(
                (float) $feedback['discomfort_after'] - (float) $feedback['discomfort_before'],
                2
            );
        }

        if (isset($feedback['fatigue_before'], $feedback['fatigue_after'])) {
            $summary['available'] = true;
            $summary['fatigue_delta'] = round(
                (float) $feedback['fatigue_after'] - (float) $feedback['fatigue_before'],
                2
            );
        }

        if (isset($feedback['throughput_before'], $feedback['throughput_after'])) {
            $summary['available'] = true;
            $summary['throughput_delta'] = round(
                (float) $feedback['throughput_after'] - (float) $feedback['throughput_before'],
                2
            );
        }

        if (isset($feedback['notes'])) {
            $summary['worker_notes'] = (string) $feedback['notes'];
        }

        return $summary;
    }
}
