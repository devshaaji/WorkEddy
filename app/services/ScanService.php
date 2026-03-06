<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Services\Ergonomics\AssessmentEngine;

final class ScanService
{
    private const VALID_MODELS = ['rula', 'reba', 'niosh'];

    public function __construct(
        private readonly ScanRepository    $scans,
        private readonly TaskRepository    $tasks,
        private readonly AssessmentEngine  $assessmentEngine,
        private readonly UsageMeterService $usageMeter,
        private readonly QueueService      $queue,
    ) {}

    public function createManualScan(int $organizationId, int $userId, int $taskId, string $model, array $metrics): array
    {
        $model = $this->normalizeModel($model);
        $this->assessmentEngine->validateCombination($model, 'manual');

        $this->tasks->findById($organizationId, $taskId);
        $this->usageMeter->assertAvailable($organizationId);

        $score  = $this->assessmentEngine->assess($model, $metrics);
        $scanId = $this->scans->createManual($organizationId, $userId, $taskId, $model, $score, $metrics);

        return $this->scans->findById($organizationId, $scanId);
    }

    public function createVideoScan(int $organizationId, int $userId, int $taskId, string $model, string $videoPath, ?int $parentScanId = null): array
    {
        $model = $this->normalizeModel($model);
        $this->assessmentEngine->validateCombination($model, 'video');

        if (trim($videoPath) === '') {
            throw new RuntimeException('Missing field: video_path');
        }

        if (!str_starts_with($videoPath, '/storage/uploads/videos/')) {
            throw new RuntimeException('video_path must be under /storage/uploads/videos/');
        }

        $this->tasks->findById($organizationId, $taskId);
        $this->usageMeter->assertAvailable($organizationId);

        $scanId = $this->scans->createVideo($organizationId, $userId, $taskId, $model, $videoPath, $parentScanId);

        $this->queue->enqueueScanJob([
            'scan_id'         => $scanId,
            'organization_id' => $organizationId,
            'video_path'      => $videoPath,
            'model'           => $model,
        ]);

        return ['scan_id' => $scanId, 'status' => 'processing'];
    }

    public function getById(int $organizationId, int $scanId): array
    {
        return $this->scans->findById($organizationId, $scanId);
    }

    public function listByOrganization(int $organizationId): array
    {
        return $this->scans->listByOrganization($organizationId);
    }

    /**
     * Compare a scan with its parent (repeat-scan comparison).
     *
     * Returns an array with 'current' and 'parent' scan data for side-by-side display.
     */
    public function compare(int $organizationId, int $scanId): array
    {
        $current = $this->scans->findById($organizationId, $scanId);

        $parentId = $current['parent_scan_id'] ?? null;
        if (!$parentId) {
            throw new RuntimeException('This scan has no parent scan to compare against');
        }

        $parent = $this->scans->findById($organizationId, (int) $parentId);

        return [
            'current' => $current,
            'parent'  => $parent,
        ];
    }

    private function normalizeModel(string $model): string
    {
        $model = strtolower(trim($model));
        if (!in_array($model, self::VALID_MODELS, true)) {
            throw new RuntimeException("Invalid assessment model: {$model}. Allowed: " . implode(', ', self::VALID_MODELS));
        }
        return $model;
    }
}