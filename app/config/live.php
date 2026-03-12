<?php

$envBool = static fn (string $name, bool $default): bool => filter_var(
    getenv($name),
    FILTER_VALIDATE_BOOL,
    FILTER_NULL_ON_FAILURE
) ?? $default;

$planConcurrencyOverridesRaw = trim((string) (getenv('LIVE_PLAN_CONCURRENCY_LIMITS_JSON') ?: ''));
$planConcurrencyOverrides = [];
if ($planConcurrencyOverridesRaw !== '') {
    $decoded = json_decode($planConcurrencyOverridesRaw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $planName => $value) {
            if (is_string($planName) && (is_int($value) || is_numeric($value))) {
                $planConcurrencyOverrides[strtolower(trim($planName))] = max(1, (int) $value);
            }
        }
    }
}

/**
 * Live streaming configuration.
 *
 * Controls the real-time pose estimation pipeline used by live-worker.
 * Both MediaPipe and YOLO26 engines are available; switch via LIVE_POSE_ENGINE.
 *
 * Latency defaults are tuned for a balance between responsiveness and accuracy.
 * Override any setting via env vars for per-deployment tuning.
 */

return [
    // ── Pose engine ────────────────────────────────────────────────────
    // Which model backend to use for live-stream pose estimation.
    //   "mediapipe" — MediaPipe Pose Landmarker (lighter, lower GPU requirement)
    //   "yolo26"    — YOLOv26n-pose (faster inference, NMS-free, higher throughput)
    'pose_engine' => getenv('LIVE_POSE_ENGINE') ?: 'yolo26',

    // ── Ergonomic scoring model ────────────────────────────────────────
    // Which assessment model PHP AssessmentEngine uses to score live frames.
    'scoring_model' => getenv('LIVE_SCORING_MODEL') ?: 'reba',

    // ── Latency & throughput ───────────────────────────────────────────

    // Target frames-per-second sent from the client to the live worker.
    // The client should down-sample to this rate before sending frames.
    // Default: 5 FPS — good balance between CPU and responsiveness.
    'target_fps' => (float) (getenv('LIVE_TARGET_FPS') ?: 5.0),

    // Maximum number of milliseconds a single batch of frames should take
    // (worker-side processing window). Batches exceeding this are split.
    // Default: 500 ms — keeps per-batch latency under half a second.
    'batch_window_ms' => (int) (getenv('LIVE_BATCH_WINDOW_MS') ?: 500),

    // Maximum acceptable end-to-end latency from frame capture to scored
    // result delivery. The worker will skip frames if it falls behind.
    // Default: 2000 ms — users perceive feedback as "live" under 2 s.
    'max_e2e_latency_ms' => (int) (getenv('LIVE_MAX_E2E_LATENCY_MS') ?: 2000),

    // Temporal smoothing factor for angle metrics (EMA alpha).
    // Lower values smooth more strongly.
    'temporal_smoothing_alpha' => (float) (getenv('LIVE_TEMPORAL_SMOOTHING_ALPHA') ?: 0.35),

    // Minimum confidence threshold for accepting frame metrics.
    // Frames below threshold are ignored to avoid noisy risk spikes.
    'min_joint_confidence' => (float) (getenv('LIVE_MIN_JOINT_CONFIDENCE') ?: 0.45),

    // Max normalized center-point movement between frames before assigning
    // a new tracked subject ID (0-1 normalized frame distance).
    'tracking_max_distance' => (float) (getenv('LIVE_TRACKING_MAX_DISTANCE') ?: 0.15),

    // ── Worker behaviour ───────────────────────────────────────────────

    // How often the live-worker polls for new live-session jobs (seconds).
    'worker_poll_interval_seconds' => (float) (getenv('LIVE_WORKER_POLL_INTERVAL_SECONDS') ?: 1.0),

    // Enables multi-person extraction for live sessions.
    // IMPORTANT: MediaPipe live mode is single-person only.
    // If enabled, choose yolo26 as pose engine.
    'multi_person_mode' => $envBool('LIVE_MULTI_PERSON_MODE', false),

    // YOLO26 model variant.
    // Examples: yolo26n-pose, yolo26s-pose, yolo26m-pose, yolo26l-pose, custom weights path.
    'yolo_model_variant' => getenv('LIVE_YOLO_MODEL_VARIANT') ?: 'yolo26n-pose',

    // Optional fallback variants in order, comma-separated.
    // Example: yolo26n-pose,yolo26s-pose,yolo26m-pose
    'yolo_model_fallback_variants' => array_values(array_filter(array_map(
        static fn (string $s): string => trim($s),
        explode(',', (string) (getenv('LIVE_YOLO_MODEL_FALLBACK_VARIANTS') ?: ''))
    ))),

    // MediaPipe model variant for live mode.
    // Options: pose_landmarker_lite, pose_landmarker_full, pose_landmarker_heavy
    'mediapipe_model_variant' => getenv('LIVE_MEDIAPIPE_MODEL_VARIANT') ?: 'pose_landmarker_lite',

    // Maximum number of concurrent live sessions per live-worker instance.
    'max_concurrent_sessions' => (int) (getenv('LIVE_MAX_CONCURRENT_SESSIONS') ?: 4),

    // Fair-share cap per organization.
    // Prevents one tenant from exhausting all live session capacity.
    'max_concurrent_sessions_per_org' => (int) (getenv('LIVE_MAX_CONCURRENT_SESSIONS_PER_ORG') ?: 2),

    // Per-plan overrides for org fair-share cap.
    // Default map can be overridden by LIVE_PLAN_CONCURRENCY_LIMITS_JSON.
    // Example JSON: {"starter":1,"professional":4,"enterprise":12}
    'plan_concurrency_limits' => array_replace(
        [
            'starter' => 1,
            'professional' => 4,
            'enterprise' => 12,
        ],
        $planConcurrencyOverrides,
    ),

    // Number of live-worker replicas provisioned for processing.
    // Effective cluster capacity = max_concurrent_sessions * worker_count.
    'worker_count' => (int) (getenv('LIVE_WORKER_COUNT') ?: 1),

    // Seconds of inactivity before a live session is auto-completed.
    'session_timeout_seconds' => (int) (getenv('LIVE_SESSION_TIMEOUT_SECONDS') ?: 300),

    // ── Queue ──────────────────────────────────────────────────────────
    // Separate queue for live-session jobs so video-worker is not affected.
    'queue_name' => getenv('LIVE_QUEUE_NAME') ?: 'live_session_jobs',
];
