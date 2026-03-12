<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Contracts\CacheInterface;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Repositories\LiveSessionRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\Ergonomics\AssessmentEngine;

final class LiveSessionService
{
    public function __construct(
        private readonly LiveSessionRepository $repo,
        private readonly TaskRepository        $tasks,
        private readonly WorkspaceRepository   $workspaces,
        private readonly AssessmentEngine      $engine,
        private readonly QueueInterface        $queue,
        private readonly CacheInterface        $cache,
        private readonly array                 $config,
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
        $queueName = (string) ($this->config['queue_name'] ?? 'live_session_jobs');
        $this->queue->enqueue($queueName, [
            'session_id'      => $sessionId,
            'organization_id' => $organizationId,
            'pose_engine'     => $engine,
            'multi_person_mode' => $multiPersonMode,
            'model_variant'   => $engine === 'yolo26'
                ? (string) ($this->config['yolo_model_variant'] ?? 'yolo26n-pose')
                : (string) ($this->config['mediapipe_model_variant'] ?? 'pose_landmarker_lite'),
            'model'           => $model,
            'target_fps'      => $this->config['target_fps'],
            'batch_window_ms' => $this->config['batch_window_ms'],
            'max_e2e_latency_ms' => $this->config['max_e2e_latency_ms'],
            'smoothing_alpha' => $this->config['temporal_smoothing_alpha'] ?? 0.35,
            'min_joint_confidence' => $this->config['min_joint_confidence'] ?? 0.45,
            'tracking_max_distance' => $this->config['tracking_max_distance'] ?? 0.15,
        ]);

        return $this->repo->findById($organizationId, $sessionId);
    }

    /**
     * Get a live session by ID.
     */
    public function getSession(int $organizationId, int $sessionId): array
    {
        return $this->repo->findById($organizationId, $sessionId);
    }

    /**
     * List sessions for an organization, optionally filtered by status.
     */
    public function listSessions(int $organizationId, ?string $status = null): array
    {
        return $this->repo->listByOrganization($organizationId, $status);
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

        return $this->repo->findById($organizationId, $sessionId);
    }

    /**
     * Get recent frame data for real-time display.
     */
    public function getRecentFrames(int $organizationId, int $sessionId, int $limit = 50): array
    {
        // Validate session belongs to org
        $this->repo->findById($organizationId, $sessionId);

        return $this->repo->getRecentFrames($sessionId, $limit);
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
     * PHP is the scoring authority: the worker sends raw pose metrics per frame,
     * and this method runs each through AssessmentEngine before persisting.
     */
    public function recordFrameBatch(
        int    $sessionId,
        int    $organizationId,
        string $model,
        array  $frames,
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

        $avgLatency = count($latencies) > 0
            ? array_sum($latencies) / count($latencies)
            : 0.0;

        $this->repo->updateFrameStats($sessionId, count($scored), $avgLatency);

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

        return $this->repo->findById($organizationId, $sessionId);
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
}
