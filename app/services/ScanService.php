<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Contracts\CacheInterface;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Repositories\ControlRecommendationRepository;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\Ergonomics\AssessmentEngine;

final class ScanService
{
    private const VALID_MODELS = ['rula', 'reba', 'niosh'];
    private const SCAN_QUEUE   = 'scan_jobs';
    private const SCAN_LIST_CACHE_TTL = 300;
    private const VALID_SCAN_STATUSES = ['pending', 'processing', 'completed', 'invalid'];
    private const MAX_SCAN_LIST_LIMIT = 500;

    public function __construct(
        private readonly ScanRepository    $scans,
        private readonly TaskRepository    $tasks,
        private readonly AssessmentEngine  $assessmentEngine,
        private readonly UsageMeterService $usageMeter,
        private readonly QueueInterface    $queue,
        private readonly ?CacheInterface   $cache = null,
        private readonly int               $scanListCacheTtl = self::SCAN_LIST_CACHE_TTL,
        private readonly ?WorkspaceRepository $workspaces = null,
        private readonly ?ControlRecommendationRepository $controlRecommendations = null,
        private readonly ?ControlRecommendationService $controlRecommendationEngine = null,
        private readonly ?ImprovementProofService $improvementProofs = null,
        private readonly ?VideoProcessingService $videos = null,
    ) {}

    public function createManualScan(int $organizationId, int $userId, int $taskId, ?string $model, array $metrics): array
    {
        $model = $this->resolveModelForInput($organizationId, $model, 'manual');
        $this->assessmentEngine->validateCombination($model, 'manual');

        $this->tasks->findById($organizationId, $taskId);
        $this->usageMeter->assertAvailable($organizationId);

        $score  = $this->assessmentEngine->assess($model, $metrics);
        $scanId = $this->scans->createManual($organizationId, $userId, $taskId, $model, $score, $metrics);
        $this->persistRecommendations($organizationId, $scanId, $model, $metrics, $score);
        $this->invalidateScanLists($organizationId);

        return $this->scans->findById($organizationId, $scanId);
    }

    public function createVideoScan(int $organizationId, int $userId, int $taskId, ?string $model, string $videoPath, ?int $parentScanId = null): array
    {
        $model = $this->resolveModelForInput($organizationId, $model, 'video');
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
        $this->scans->reserveUsage($organizationId, $scanId, 'video_scan');

        try {
            $this->queue->enqueue(self::SCAN_QUEUE, [
                'scan_id'         => $scanId,
                'organization_id' => $organizationId,
                'video_path'      => $videoPath,
                'model'           => $model,
            ]);
        } catch (\Throwable $e) {
            $this->scans->markVideoInvalid($organizationId, $scanId, 'Queue enqueue failed');
            $this->invalidateScanLists($organizationId);
            throw new RuntimeException('Unable to queue video scan for processing', 0, $e);
        }

        $this->invalidateScanLists($organizationId);

        return ['scan_id' => $scanId, 'status' => 'processing'];
    }

    public function completeVideoScanFromWorker(
        int $organizationId,
        int $scanId,
        array $metrics,
        ?string $model = null,
        ?string $poseVideoPath = null
    ): array
    {
        if ($scanId <= 0) {
            throw new RuntimeException('scan_id must be a positive integer');
        }

        if ($organizationId <= 0) {
            throw new RuntimeException('organization_id must be a positive integer');
        }

        $scan = $this->scans->findWorkerScan($organizationId, $scanId);
        if (($scan['scan_type'] ?? '') !== 'video') {
            throw new RuntimeException('Only video scans can be completed by worker callbacks');
        }

        $scanModel = $this->normalizeModel((string) ($scan['model'] ?? ''));
        if ($model !== null && $model !== '') {
            $callbackModel = $this->normalizeModel($model);
            if ($callbackModel !== $scanModel) {
                throw new RuntimeException('Model mismatch for scan completion callback');
            }
        }

        $this->assessmentEngine->validateCombination($scanModel, 'video');
        $score = $this->assessmentEngine->assess($scanModel, $metrics);

        $resolvedPoseVideoPath = null;
        if ($poseVideoPath !== null && trim($poseVideoPath) !== '') {
            $resolvedPoseVideoPath = trim($poseVideoPath);
            if (!str_starts_with($resolvedPoseVideoPath, '/storage/uploads/videos/')) {
                throw new RuntimeException('pose_video_path must be under /storage/uploads/videos/');
            }
        }

        $this->scans->completeVideoProcessing($organizationId, $scanId, $scanModel, $score, $metrics, $resolvedPoseVideoPath);
        $this->persistRecommendations($organizationId, $scanId, $scanModel, $metrics, $score);
        $this->applyPrivacyPolicyAfterProcessing($organizationId, $scanId, $scan);
        $this->invalidateScanLists($organizationId);

        return $this->scans->findById($organizationId, $scanId);
    }

    public function failVideoScanFromWorker(int $organizationId, int $scanId, string $errorMessage): void
    {
        if ($scanId <= 0) {
            throw new RuntimeException('scan_id must be a positive integer');
        }

        if ($organizationId <= 0) {
            throw new RuntimeException('organization_id must be a positive integer');
        }

        $message = trim($errorMessage) !== '' ? trim($errorMessage) : 'Processing failed';
        $this->scans->markVideoInvalid($organizationId, $scanId, $message);
        $scan = $this->scans->findWorkerScan($organizationId, $scanId);
        $this->applyPrivacyPolicyAfterProcessing($organizationId, $scanId, $scan);
        $this->invalidateScanLists($organizationId);
    }

    public function getById(int $organizationId, int $scanId): array
    {
        return $this->scans->findById($organizationId, $scanId);
    }

    public function listByOrganization(int $organizationId, ?string $status = null, ?int $limit = null): array
    {
        $status = $this->normalizeStatus($status);
        $limit = $this->normalizeLimit($limit);

        if ($this->cache === null) {
            return $this->scans->listByOrganization($organizationId, $status, $limit);
        }

        $version = $this->scanListVersion($organizationId);
        $statusKey = $status ?? 'all';
        $limitKey = $limit ?? 'all';
        $cacheKey = "scan:list:org:{$organizationId}:status:{$statusKey}:limit:{$limitKey}:v:{$version}";
        $cached = $this->cache->get($cacheKey, null);

        if (is_array($cached)) {
            return $cached;
        }

        $rows = $this->scans->listByOrganization($organizationId, $status, $limit);
        $this->cache->set($cacheKey, $rows, $this->scanListCacheTtl);

        return $rows;
    }

    public function listByTask(int $organizationId, int $taskId, ?string $status = null, ?int $limit = null): array
    {
        $status = $this->normalizeStatus($status);
        $limit = $this->normalizeLimit($limit);

        if ($this->cache === null) {
            return $this->scans->listByTask($organizationId, $taskId, $status, $limit);
        }

        $version = $this->scanListVersion($organizationId);
        $statusKey = $status ?? 'all';
        $limitKey = $limit ?? 'all';
        $cacheKey = "scan:list:org:{$organizationId}:task:{$taskId}:status:{$statusKey}:limit:{$limitKey}:v:{$version}";
        $cached = $this->cache->get($cacheKey, null);

        if (is_array($cached)) {
            return $cached;
        }

        $rows = $this->scans->listByTask($organizationId, $taskId, $status, $limit);
        $this->cache->set($cacheKey, $rows, $this->scanListCacheTtl);

        return $rows;
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
            'improvement_proof' => ($this->improvementProofs ?? new ImprovementProofService())->build($parent, $current),
        ];
    }

    /**
     * @param array<string,mixed> $metrics
     * @param array<string,mixed> $score
     */
    private function persistRecommendations(int $organizationId, int $scanId, string $model, array $metrics, array $score): void
    {
        if ($this->controlRecommendations === null) {
            return;
        }

        $engine = $this->controlRecommendationEngine ?? new ControlRecommendationService();
        $policy = $this->orgSettingArray($organizationId, 'recommendation_policy', []);
        $controls = $engine->recommend($model, $metrics, $score, $policy);
        $this->controlRecommendations->replaceForScan($scanId, $controls);
    }

    /**
     * @param array<string,mixed> $default
     * @return array<string,mixed>
     */
    private function orgSettingArray(int $organizationId, string $key, array $default = []): array
    {
        if ($this->workspaces === null) {
            return $default;
        }

        $value = $this->workspaces->organizationSetting($organizationId, $key, $default);
        return is_array($value) ? $value : $default;
    }

    /**
     * @param array<string,mixed> $scan
     */
    private function applyPrivacyPolicyAfterProcessing(int $organizationId, int $scanId, array $scan): void
    {
        if (!$this->orgSettingBool($organizationId, 'auto_delete_video', false)) {
            return;
        }

        $videoPath = trim((string) ($scan['video_path'] ?? ''));
        if ($videoPath === '') {
            return;
        }

        if ($this->videos !== null) {
            $this->videos->deleteVideo($videoPath);
        } elseif (is_file($videoPath)) {
            @unlink($videoPath);
        }

        $this->scans->clearVideoPath($organizationId, $scanId);
    }

    private function resolveModelForInput(int $organizationId, ?string $requestedModel, string $inputType): string
    {
        $candidate = strtolower(trim((string) $requestedModel));

        if ($candidate === '' && $this->workspaces !== null) {
            $candidate = strtolower(trim((string) $this->workspaces->organizationSetting($organizationId, 'default_model', '')));
        }

        if ($candidate === '') {
            $candidate = 'reba';
        }

        $model = $this->normalizeModel($candidate);
        $service = $this->assessmentEngine->resolve($model);

        if (in_array($inputType, $service->supportedInputTypes(), true)) {
            return $model;
        }

        // Safe fallback when org default is incompatible (e.g., NIOSH for video).
        return $inputType === 'video' ? 'reba' : $model;
    }

    private function orgSettingBool(int $organizationId, string $key, bool $default): bool
    {
        if ($this->workspaces === null) {
            return $default;
        }

        $value = $this->workspaces->organizationSetting($organizationId, $key, $default);
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeModel(string $model): string
    {
        $model = strtolower(trim($model));
        if (!in_array($model, self::VALID_MODELS, true)) {
            throw new RuntimeException("Invalid assessment model: {$model}. Allowed: " . implode(', ', self::VALID_MODELS));
        }
        return $model;
    }

    private function scanListVersion(int $organizationId): int
    {
        if ($this->cache === null) {
            return 1;
        }

        $versionKey = "scan:list:org:{$organizationId}:version";
        $version = (int) $this->cache->get($versionKey, 1);
        if ($version < 1) {
            $version = 1;
            $this->cache->set($versionKey, $version, 0);
        }

        return $version;
    }

    private function invalidateScanLists(int $organizationId): void
    {
        if ($this->cache === null) {
            return;
        }

        $versionKey = "scan:list:org:{$organizationId}:version";
        $current = (int) $this->cache->get($versionKey, 1);
        $next = $current >= 1 ? $current + 1 : 2;
        $this->cache->set($versionKey, $next, 0);
    }

    private function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $status = strtolower(trim($status));
        if ($status === '') {
            return null;
        }

        return in_array($status, self::VALID_SCAN_STATUSES, true) ? $status : null;
    }

    private function normalizeLimit(?int $limit): ?int
    {
        if ($limit === null || $limit <= 0) {
            return null;
        }

        return min(self::MAX_SCAN_LIST_LIMIT, $limit);
    }
}
