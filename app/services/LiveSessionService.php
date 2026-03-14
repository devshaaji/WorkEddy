<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Contracts\CacheInterface;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Helpers\WorkerContract;
use WorkEddy\Repositories\LiveSessionRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Repositories\WorkspaceRepository;

final class LiveSessionService
{
    public function __construct(
        private readonly LiveSessionRepository $repo,
        private readonly TaskRepository        $tasks,
        private readonly WorkspaceRepository   $workspaces,
        private readonly QueueInterface        $queue,
        private readonly array                 $config,
        private readonly ?UsageMeterService    $usageMeter = null,
        private readonly ?CacheInterface       $cache = null,
    ) {}

    // ─── User-facing ──────────────────────────────────────────────────

    /**
     * Start a new live session.
     */
    public function startSession(
        int     $organizationId,
        int     $userId,
        int     $taskId,
        ?string $poseEngine = null,
        ?string $scoringModel = null,
    ): array {
        // Validate task belongs to org
        $this->tasks->findById($organizationId, $taskId);

        $engine = $poseEngine ?? $this->config['pose_engine'];
        $model  = $scoringModel ?? $this->config['scoring_model'];

        if (!in_array($engine, ['mediapipe', 'yolo26'], true)) {
            throw new RuntimeException("Invalid pose engine: {$engine}. Must be 'mediapipe' or 'yolo26'.");
        }

        $multiPersonMode = (bool) ($this->config['multi_person_mode'] ?? false);
        if ($multiPersonMode && $engine === 'mediapipe') {
            throw new RuntimeException(
                'MediaPipe live mode does not support multi-person detection. '
                . 'Disable LIVE_MULTI_PERSON_MODE or switch pose_engine to yolo26.'
            );
        }

        if (!in_array($model, ['rula', 'reba'], true)) {
            throw new RuntimeException("Invalid scoring model: {$model}. Must be 'rula' or 'reba'.");
        }

        $this->usageMeter?->assertLiveSessionAvailable($organizationId);
        $this->enforceOrganizationConcurrentSessionLimit($organizationId);
        $this->enforceConcurrentSessionLimit();

        $now = gmdate('Y-m-d H:i:s');

        $sessionId = $this->repo->create([
            'organization_id'    => $organizationId,
            'user_id'            => $userId,
            'task_id'            => $taskId,
            'model'              => $model,
            'pose_engine'        => $engine,
            'target_fps'         => $this->config['target_fps'],
            'batch_window_ms'    => $this->config['batch_window_ms'],
            'max_e2e_latency_ms' => $this->config['max_e2e_latency_ms'],
            'started_at'         => $now,
            'created_at'         => $now,
        ]);

        // Enqueue for the live-worker to pick up
        $this->queue->enqueue(WorkerContract::liveQueueName(), WorkerContract::liveJobPayload([
            'session_id' => $sessionId,
            'organization_id' => $organizationId,
            'pose_engine' => $engine,
            'multi_person_mode' => $multiPersonMode,
            'model_variant' => $engine === 'yolo26'
                ? (string) ($this->config['yolo_model_variant'] ?? 'yolo26n-pose')
                : (string) ($this->config['mediapipe_model_variant'] ?? 'pose_landmarker_lite'),
            'model' => $model,
            'target_fps' => $this->config['target_fps'],
            'batch_window_ms' => $this->config['batch_window_ms'],
            'max_e2e_latency_ms' => $this->config['max_e2e_latency_ms'],
            'smoothing_alpha' => $this->config['temporal_smoothing_alpha'] ?? 0.35,
            'min_joint_confidence' => $this->config['min_joint_confidence'] ?? 0.45,
            'tracking_max_distance' => $this->config['tracking_max_distance'] ?? 0.15,
        ]));

        $session = $this->repo->findById($organizationId, $sessionId);
        $this->touchStreamVersion($sessionId);

        return $this->augmentRuntimeSessionFields($this->normalizeSession($session));
    }

    /**
     * Queue browser-captured frame batch for the live worker.
     *
     * @param array<int,array<string,mixed>> $frames
     * @return array<string,int|string>
     */
    public function ingestFrameBatch(int $organizationId, int $sessionId, array $frames, array $telemetry = []): array
    {
        $session = $this->repo->findById($organizationId, $sessionId);

        if (($session['status'] ?? null) !== 'active') {
            throw new RuntimeException('Session is not active');
        }

        $normalizedFrames = $this->normalizeIncomingFrames($frames);
        if ($normalizedFrames === []) {
            throw new RuntimeException('No valid frames were provided');
        }

        $frameCount = count($normalizedFrames);
        $frameQueueName = WorkerContract::liveFrameQueueName();
        $maxPendingFrameBatches = max(1, (int) ($this->config['max_pending_frame_batches'] ?? 12));
        $queueDepth = $this->queue->size($frameQueueName);
        if ($queueDepth >= $maxPendingFrameBatches) {
            $this->storeTelemetry($sessionId, $session, [
                'server_dropped_frame_batches' => 1,
                'server_dropped_frames' => $frameCount,
                'current_frame_queue_depth' => $queueDepth,
                'max_pending_frame_batches' => $maxPendingFrameBatches,
                'last_backpressure_at' => gmdate('c'),
            ]);
            $this->touchStreamVersion($sessionId);

            return [
                'queued' => 0,
                'dropped' => $frameCount,
                'queue_depth' => $queueDepth,
                'session_id' => $sessionId,
                'status' => 'dropped_backpressure',
            ];
        }

        $engine = (string) ($session['pose_engine'] ?? $this->config['pose_engine']);

        $this->queue->enqueue(
            $frameQueueName,
            WorkerContract::liveFrameBatchPayload([
                'session_id' => $sessionId,
                'organization_id' => $organizationId,
                'pose_engine' => $engine,
                'multi_person_mode' => (bool) ($this->config['multi_person_mode'] ?? false),
                'model_variant' => $this->modelVariantForEngine($engine),
                'model' => (string) ($session['model'] ?? $this->config['scoring_model']),
                'target_fps' => (float) ($session['target_fps'] ?? $this->config['target_fps']),
                'batch_window_ms' => (int) ($session['batch_window_ms'] ?? $this->config['batch_window_ms']),
                'max_e2e_latency_ms' => (int) ($session['max_e2e_latency_ms'] ?? $this->config['max_e2e_latency_ms']),
                'smoothing_alpha' => (float) ($this->config['temporal_smoothing_alpha'] ?? 0.35),
                'min_joint_confidence' => (float) ($this->config['min_joint_confidence'] ?? 0.45),
                'tracking_max_distance' => (float) ($this->config['tracking_max_distance'] ?? 0.15),
                'stale_batch_drop_multiplier' => (float) ($this->config['stale_batch_drop_multiplier'] ?? 1.0),
                'frames' => $normalizedFrames,
            ])
        );

        $this->repo->incrementCapturedFrameCount($sessionId, $frameCount);
        $queueDepthAfterEnqueue = $queueDepth + 1;

        $ingestTelemetry = $this->buildIngestTelemetry($normalizedFrames, $telemetry);
        $ingestTelemetry['current_frame_queue_depth'] = $queueDepthAfterEnqueue;
        $ingestTelemetry['max_pending_frame_batches'] = $maxPendingFrameBatches;
        if ($ingestTelemetry !== []) {
            $this->storeTelemetry($sessionId, $session, $ingestTelemetry);
        }
        $this->touchStreamVersion($sessionId);

        return [
            'queued' => $frameCount,
            'queue_depth' => $queueDepthAfterEnqueue,
            'session_id' => $sessionId,
            'status' => 'queued',
        ];
    }

    /**
     * Get a live session by ID.
     */
    public function getSession(int $organizationId, int $sessionId): array
    {
        return $this->augmentRuntimeSessionFields(
            $this->normalizeSession($this->repo->findById($organizationId, $sessionId))
        );
    }

    public function isSessionAcceptingFrames(int $organizationId, int $sessionId): bool
    {
        try {
            $session = $this->repo->findById($organizationId, $sessionId);
        } catch (RuntimeException) {
            return false;
        }

        return (($session['status'] ?? null) === 'active');
    }

    /**
     * List sessions for an organization, optionally filtered by status.
     */
    public function listSessions(int $organizationId, ?string $status = null): array
    {
        return array_map(
            fn (array $row): array => $this->augmentRuntimeSessionFields($this->normalizeSession($row)),
            $this->repo->listByOrganization($organizationId, $status)
        );
    }

    /**
     * Stop (complete) an active session.
     */
    public function stopSession(int $organizationId, int $sessionId): array
    {
        $session = $this->repo->findById($organizationId, $sessionId);

        if ($session['status'] !== 'active' && $session['status'] !== 'paused') {
            throw new RuntimeException('Session is not active or paused');
        }

        $this->repo->updateStatus($sessionId, 'completed');
        $this->touchStreamVersion($sessionId);

        return $this->augmentRuntimeSessionFields(
            $this->normalizeSession($this->repo->findById($organizationId, $sessionId))
        );
    }

    /**
     * Get recent frame data for real-time display.
     */
    public function getRecentFrames(int $organizationId, int $sessionId, int $limit = 50): array
    {
        // Validate session belongs to org
        $this->repo->findById($organizationId, $sessionId);

        $cached = $this->cache?->get($this->recentFramesCacheKey($sessionId), null);
        if (is_array($cached) && $cached !== []) {
            return array_slice($cached, 0, max(1, $limit));
        }

        $rows = $this->repo->getRecentFrames($sessionId, $limit);
        if ($rows !== [] && $this->cache !== null) {
            $this->cache->set(
                $this->recentFramesCacheKey($sessionId),
                $rows,
                max(60, (int) ($this->config['recent_frames_cache_ttl_seconds'] ?? 900))
            );
        }

        return $rows;
    }

    /**
     * Build a push-friendly snapshot for one live session.
     *
     * @return array<string,mixed>
     */
    public function streamSnapshot(int $organizationId, int $sessionId, int $limit = 30): array
    {
        $session = $this->getSession($organizationId, $sessionId);

        return [
            'session' => $session,
            'frames' => $this->getRecentFrames($organizationId, $sessionId, $limit),
            'stream_version' => $this->streamVersion($sessionId),
            'generated_at' => gmdate('c'),
        ];
    }

    /**
     * Return available pose engines and their current configuration.
     */
    public function getEngineConfig(): array
    {
        return [
            'available_engines' => [
                [
                    'id'          => 'mediapipe',
                    'name'        => 'MediaPipe Pose Landmarker',
                    'description' => 'Google MediaPipe — lighter model, lower GPU requirement, single-person live tracking.',
                    'supports_multi_person' => false,
                    'variant'     => $this->config['mediapipe_model_variant'],
                ],
                [
                    'id'          => 'yolo26',
                    'name'        => 'YOLOv26 Pose',
                    'description' => 'Ultralytics YOLO26 — NMS-free, faster CPU/GPU inference, multi-person capable.',
                    'supports_multi_person' => true,
                    'variant'     => $this->config['yolo_model_variant'],
                    'fallback_variants' => $this->config['yolo_model_fallback_variants'] ?? [],
                ],
            ],
            'default_engine' => $this->config['pose_engine'],
            'multi_person_mode' => (bool) ($this->config['multi_person_mode'] ?? false),
            'concurrency_limits' => [
                'max_concurrent_sessions_per_worker' => (int) ($this->config['max_concurrent_sessions'] ?? 4),
                'worker_count' => (int) ($this->config['worker_count'] ?? 1),
                'max_total_concurrent_sessions' => $this->maxTotalConcurrentSessions(),
                'max_concurrent_sessions_per_org' => max(1, (int) ($this->config['max_concurrent_sessions_per_org'] ?? 2)),
                'current_open_sessions' => $this->repo->countOpenSessions(),
            ],
            'latency_defaults' => [
                'target_fps'         => $this->config['target_fps'],
                'batch_window_ms'    => $this->config['batch_window_ms'],
                'max_e2e_latency_ms' => $this->config['max_e2e_latency_ms'],
            ],
            'stability_controls' => [
                'temporal_smoothing_alpha' => (float) ($this->config['temporal_smoothing_alpha'] ?? 0.35),
                'min_joint_confidence' => (float) ($this->config['min_joint_confidence'] ?? 0.45),
                'tracking_max_distance' => (float) ($this->config['tracking_max_distance'] ?? 0.15),
            ],
            'scoring_model' => $this->config['scoring_model'],
        ];
    }

    // ─── Worker-facing (called by LiveWorkerController) ───────────────

    /**
     * Record a batch of scored frames from the live-worker.
     *
     * The live worker sends already-extracted frame metrics.
     * This method validates session state and persists that batch as-is.
     */
    public function recordFrameBatch(
        int    $sessionId,
        int    $organizationId,
        array  $frames,
        array  $telemetry = [],
    ): array {
        $session = $this->repo->findById($organizationId, $sessionId);

        if ($session['status'] !== 'active') {
            throw new RuntimeException('Session is not active');
        }

        $scored = [];
        $latencies = [];

        foreach ($frames as $frame) {
            $metrics = $frame['metrics'] ?? [];
            $scored[] = [
                'frame_number' => (int) ($frame['frame_number'] ?? 0),
                'metrics'      => $metrics,
                'latency_ms'   => $frame['latency_ms'] ?? null,
            ];

            if (isset($frame['latency_ms'])) {
                $latencies[] = (float) $frame['latency_ms'];
            }
        }

        $this->repo->insertFrames($sessionId, $scored);
        $this->cacheRecentFrames($sessionId, $scored);

        $avgLatency = count($latencies) > 0
            ? array_sum($latencies) / count($latencies)
            : 0.0;

        $this->repo->updateFrameStats($sessionId, count($scored), $avgLatency);
        if ($telemetry !== []) {
            $telemetry['current_frame_queue_depth'] = $this->queue->size(WorkerContract::liveFrameQueueName());
            $telemetry['max_pending_frame_batches'] = max(1, (int) ($this->config['max_pending_frame_batches'] ?? 12));
            $this->storeTelemetry($sessionId, $session, $telemetry);
        }
        $this->touchStreamVersion($sessionId);

        return ['recorded' => count($scored), 'avg_latency_ms' => round($avgLatency, 2)];
    }

    /**
     * Complete a session from the worker side with summary metrics.
     */
    public function completeSessionFromWorker(
        int   $sessionId,
        int   $organizationId,
        array $summaryMetrics,
    ): array {
        $session = $this->repo->findById($organizationId, $sessionId);

        $this->repo->storeSummary($sessionId, $summaryMetrics);
        $this->repo->updateStatus($sessionId, 'completed');
        $this->touchStreamVersion($sessionId);

        return $this->normalizeSession($this->repo->findById($organizationId, $sessionId));
    }

    /**
     * Mark a session as failed from the worker side.
     */
    public function failSessionFromWorker(
        int    $sessionId,
        int    $organizationId,
        string $errorMessage,
    ): void {
        $this->repo->updateStatus($sessionId, 'failed', $errorMessage);
        $this->touchStreamVersion($sessionId);
    }

    private function enforceConcurrentSessionLimit(): void
    {
        $openSessions = $this->repo->countOpenSessions();
        $maxTotal = $this->maxTotalConcurrentSessions();

        if ($openSessions >= $maxTotal) {
            throw new RuntimeException(
                sprintf(
                    'Concurrent live session limit reached (%d/%d). '
                    . 'Increase LIVE_MAX_CONCURRENT_SESSIONS or LIVE_WORKER_COUNT to scale out.',
                    $openSessions,
                    $maxTotal,
                )
            );
        }
    }

    private function maxTotalConcurrentSessions(): int
    {
        $perWorker = max(1, (int) ($this->config['max_concurrent_sessions'] ?? 4));
        $workerCount = max(1, (int) ($this->config['worker_count'] ?? 1));

        return $perWorker * $workerCount;
    }

    private function enforceOrganizationConcurrentSessionLimit(int $organizationId): void
    {
        $maxPerOrg = $this->maxConcurrentSessionsPerOrg($organizationId);
        $openInOrg = $this->repo->countOpenSessionsByOrganization($organizationId);

        if ($openInOrg >= $maxPerOrg) {
            throw new RuntimeException(
                sprintf(
                    'Organization live session limit reached (%d/%d). '
                    . 'Increase LIVE_MAX_CONCURRENT_SESSIONS_PER_ORG to allow more parallel streams.',
                    $openInOrg,
                    $maxPerOrg,
                )
            );
        }
    }

    private function maxConcurrentSessionsPerOrg(int $organizationId): int
    {
        $configured = max(1, (int) ($this->config['max_concurrent_sessions_per_org'] ?? 2));
        if ($this->usageMeter !== null) {
            $billingLimit = $this->usageMeter->maxConcurrentSessionsPerOrg($organizationId, $configured);
            if ($billingLimit !== $configured) {
                return $billingLimit;
            }
        }

        $planLimits = $this->config['plan_concurrency_limits'] ?? [];
        if (!is_array($planLimits)) {
            return $configured;
        }

        try {
            $plan = $this->workspaces->activePlan($organizationId);
            $planName = strtolower(trim((string) ($plan['name'] ?? '')));

            if ($planName !== '' && array_key_exists($planName, $planLimits)) {
                return max(1, (int) $planLimits[$planName]);
            }
        } catch (RuntimeException) {
            // Fall back to global org cap when subscription data is unavailable.
        }

        return $configured;
    }

    /**
     * @param array<int,array<string,mixed>> $frames
     * @return array<int,array<string,mixed>>
     */
    private function normalizeIncomingFrames(array $frames): array
    {
        $normalized = [];

        foreach ($frames as $frame) {
            if (!is_array($frame)) {
                continue;
            }

            $frameNumber = isset($frame['frame_number']) ? (int) $frame['frame_number'] : 0;
            $imageBase64 = trim((string) ($frame['image_jpeg_base64'] ?? ''));

            if ($frameNumber < 1 || $imageBase64 === '') {
                continue;
            }

            if (strlen($imageBase64) > 2_000_000) {
                throw new RuntimeException('Frame payload is too large');
            }

            $normalized[] = [
                'frame_number' => $frameNumber,
                'captured_at_ms' => isset($frame['captured_at_ms']) ? (int) $frame['captured_at_ms'] : null,
                'width' => isset($frame['width']) ? max(1, (int) $frame['width']) : null,
                'height' => isset($frame['height']) ? max(1, (int) $frame['height']) : null,
                'image_jpeg_base64' => $imageBase64,
            ];

            if (count($normalized) >= 12) {
                break;
            }
        }

        return $normalized;
    }

    private function modelVariantForEngine(string $engine): string
    {
        return $engine === 'mediapipe'
            ? (string) ($this->config['mediapipe_model_variant'] ?? 'pose_landmarker_lite')
            : (string) ($this->config['yolo_model_variant'] ?? 'yolo26n-pose');
    }

    private function recentFramesCacheKey(int $sessionId): string
    {
        return 'live:recent-frames:' . $sessionId;
    }

    /**
     * @param array<int,array<string,mixed>> $frames
     */
    private function cacheRecentFrames(int $sessionId, array $frames): void
    {
        if ($this->cache === null || $frames === []) {
            return;
        }

        $existing = $this->cache->get($this->recentFramesCacheKey($sessionId), []);
        $existingRows = is_array($existing) ? $existing : [];
        $merged = [];

        foreach ($frames as $frame) {
            $frameNumber = (int) ($frame['frame_number'] ?? 0);
            if ($frameNumber < 1) {
                continue;
            }

            $metrics = is_array($frame['metrics'] ?? null) ? $frame['metrics'] : [];
            $merged[$frameNumber] = [
                'session_id' => $sessionId,
                'frame_number' => $frameNumber,
                'metrics_json' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
                'trunk_angle' => $metrics['trunk_angle'] ?? null,
                'neck_angle' => $metrics['neck_angle'] ?? null,
                'upper_arm_angle' => $metrics['upper_arm_angle'] ?? null,
                'lower_arm_angle' => $metrics['lower_arm_angle'] ?? null,
                'wrist_angle' => $metrics['wrist_angle'] ?? null,
                'confidence' => $metrics['confidence'] ?? null,
                'latency_ms' => $frame['latency_ms'] ?? null,
            ];
        }

        foreach ($existingRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $frameNumber = (int) ($row['frame_number'] ?? 0);
            if ($frameNumber < 1 || isset($merged[$frameNumber])) {
                continue;
            }

            $merged[$frameNumber] = $row;
        }

        krsort($merged);

        $this->cache->set(
            $this->recentFramesCacheKey($sessionId),
            array_slice(array_values($merged), 0, max(10, (int) ($this->config['recent_frames_cache_size'] ?? 60))),
            max(60, (int) ($this->config['recent_frames_cache_ttl_seconds'] ?? 900))
        );
    }

    /**
     * @param array<string,mixed> $session
     * @return array<string,mixed>
     */
    private function normalizeSession(array $session): array
    {
        $session['telemetry'] = $this->decodeJsonObject($session['telemetry_json'] ?? null);
        $session['summary_metrics'] = $this->decodeJsonObject($session['summary_metrics_json'] ?? null);
        unset($session['telemetry_json'], $session['summary_metrics_json']);

        return $session;
    }

    /**
     * @param array<string,mixed> $session
     * @return array<string,mixed>
     */
    private function augmentRuntimeSessionFields(array $session): array
    {
        $session['frame_queue_depth'] = $this->queue->size(WorkerContract::liveFrameQueueName());
        $session['max_pending_frame_batches'] = max(1, (int) ($this->config['max_pending_frame_batches'] ?? 12));

        return $session;
    }

    /**
     * @param array<string,mixed>|string|null $raw
     * @return array<string,mixed>
     */
    private function decodeJsonObject(array|string|null $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param array<int,array<string,mixed>> $frames
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function buildIngestTelemetry(array $frames, array $extra = []): array
    {
        $lags = [];
        $nowMs = (int) floor(microtime(true) * 1000);

        foreach ($frames as $frame) {
            $capturedAt = isset($frame['captured_at_ms']) ? (int) $frame['captured_at_ms'] : 0;
            if ($capturedAt > 0) {
                $lags[] = max(0, $nowMs - $capturedAt);
            }
        }

        return [
            'queued_frame_batches' => 1,
            'queued_frames' => count($frames),
            'client_dropped_frames' => max(0, (int) ($extra['client_dropped_frames'] ?? 0)),
            'upload_lag_samples' => count($lags),
            'upload_lag_ms_avg' => count($lags) > 0 ? round(array_sum($lags) / count($lags), 2) : 0.0,
            'upload_lag_ms_max' => count($lags) > 0 ? max($lags) : 0.0,
            'last_ingest_at' => gmdate('c'),
        ];
    }

    /**
     * @param array<string,mixed> $session
     * @param array<string,mixed> $patch
     */
    private function storeTelemetry(int $sessionId, array $session, array $patch): void
    {
        if (isset($session['organization_id'])) {
            $session = $this->repo->findById((int) $session['organization_id'], $sessionId);
        }

        $current = $this->decodeJsonObject($session['telemetry_json'] ?? null);
        $merged = $this->mergeTelemetry($current, $patch);
        $this->repo->storeTelemetry($sessionId, $merged);
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $patch
     * @return array<string,mixed>
     */
    private function mergeTelemetry(array $current, array $patch): array
    {
        $sumKeys = [
            'queued_frame_batches',
            'queued_frames',
            'client_dropped_frames',
            'worker_processed_frames',
            'worker_skipped_frames',
            'worker_decode_failures',
            'upload_lag_samples',
            'worker_lag_samples',
            'server_dropped_frame_batches',
            'server_dropped_frames',
            'stale_frame_batches_dropped',
            'stale_frames_dropped',
        ];

        foreach ($sumKeys as $key) {
            if (array_key_exists($key, $patch)) {
                $current[$key] = (int) ($current[$key] ?? 0) + (int) $patch[$key];
            }
        }

        $current['upload_lag_ms_avg'] = $this->mergeWeightedAverage(
            (float) ($current['upload_lag_ms_avg'] ?? 0.0),
            (int) ($current['upload_lag_samples'] ?? 0) - (int) ($patch['upload_lag_samples'] ?? 0),
            isset($patch['upload_lag_ms_avg']) ? (float) $patch['upload_lag_ms_avg'] : null,
            isset($patch['upload_lag_samples']) ? (int) $patch['upload_lag_samples'] : 0,
        );
        $current['worker_lag_ms_avg'] = $this->mergeWeightedAverage(
            (float) ($current['worker_lag_ms_avg'] ?? 0.0),
            (int) ($current['worker_lag_samples'] ?? 0) - (int) ($patch['worker_lag_samples'] ?? 0),
            isset($patch['worker_lag_ms_avg']) ? (float) $patch['worker_lag_ms_avg'] : null,
            isset($patch['worker_lag_samples']) ? (int) $patch['worker_lag_samples'] : 0,
        );

        if (array_key_exists('upload_lag_ms_max', $patch)) {
            $current['upload_lag_ms_max'] = max((float) ($current['upload_lag_ms_max'] ?? 0.0), (float) $patch['upload_lag_ms_max']);
        }
        if (array_key_exists('worker_lag_ms_max', $patch)) {
            $current['worker_lag_ms_max'] = max((float) ($current['worker_lag_ms_max'] ?? 0.0), (float) $patch['worker_lag_ms_max']);
        }

        foreach (['last_ingest_at', 'last_worker_at', 'last_backpressure_at'] as $key) {
            if (array_key_exists($key, $patch)) {
                $current[$key] = (string) $patch[$key];
            }
        }

        foreach (['current_frame_queue_depth', 'max_pending_frame_batches'] as $key) {
            if (array_key_exists($key, $patch)) {
                $current[$key] = (int) $patch[$key];
            }
        }

        return $current;
    }

    private function mergeWeightedAverage(float $currentAverage, int $currentSamples, ?float $patchAverage, int $patchSamples): float
    {
        if ($patchAverage === null || $patchSamples <= 0) {
            return round($currentAverage, 2);
        }

        if ($currentSamples <= 0) {
            return round($patchAverage, 2);
        }

        return round((($currentAverage * $currentSamples) + ($patchAverage * $patchSamples)) / ($currentSamples + $patchSamples), 2);
    }

    private function streamVersion(int $sessionId): int
    {
        return (int) ($this->cache?->get($this->streamVersionCacheKey($sessionId), 1) ?? 1);
    }

    private function touchStreamVersion(int $sessionId): void
    {
        if ($this->cache === null) {
            return;
        }

        $key = $this->streamVersionCacheKey($sessionId);
        $current = (int) ($this->cache->get($key, 1) ?? 1);
        $this->cache->set($key, $current + 1, max(300, (int) ($this->config['recent_frames_cache_ttl_seconds'] ?? 900)));
    }

    private function streamVersionCacheKey(int $sessionId): string
    {
        return 'live:stream-version:' . $sessionId;
    }
}
