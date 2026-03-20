<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\LiveSessionService;

/**
 * User-facing live-session endpoints.
 *
 * Start / stop / list / inspect real-time pose-estimation sessions.
 */
final class LiveSessionController
{
    public function __construct(
        private readonly LiveSessionService $service,
    ) {}

    /**
     * GET /api/v1/live/engines — available pose engines and latency defaults.
     */
    public function engines(): never
    {
        Response::json(['data' => $this->service->getEngineConfig()]);
    }

    /**
     * POST /api/v1/live/sessions — start a new live session.
     */
    public function start(array $auth, array $body): never
    {
        Auth::requireRoles($auth, ['admin', 'supervisor', 'observer']);
        Validator::requireFields($body, ['task_id']);

        $session = $this->service->startSession(
            (int) $auth['org'],
            (int) $auth['sub'],
            (int) $body['task_id'],
            isset($body['pose_engine']) ? (string) $body['pose_engine'] : null,
            isset($body['model']) ? (string) $body['model'] : null,
        );

        Response::created(['data' => $session]);
    }

    /**
     * GET /api/v1/live/sessions — list sessions for the org.
     */
    public function index(array $auth): never
    {
        $status = isset($_GET['status']) ? (string) $_GET['status'] : null;
        $sessions = $this->service->listSessions((int) $auth['org'], $status);

        Response::json(['data' => $sessions]);
    }

    /**
     * GET /api/v1/live/sessions/{id} — show a single session.
     */
    public function show(array $auth, int $sessionId): never
    {
        $session = $this->service->getSession((int) $auth['org'], $sessionId);

        Response::json(['data' => $session]);
    }

    /**
     * POST /api/v1/live/sessions/{id}/stop — stop an active session.
     */
    public function stop(array $auth, int $sessionId): never
    {
        Auth::requireRoles($auth, ['admin', 'supervisor', 'observer']);

        $session = $this->service->stopSession((int) $auth['org'], $sessionId);

        Response::json(['data' => $session]);
    }

    /**
     * POST /api/v1/live/sessions/{id}/frames — ingest browser-captured frame batch.
     */
    public function ingestFrames(array $auth, int $sessionId, array $body): never
    {
        Auth::requireRoles($auth, ['admin', 'supervisor', 'observer']);
        Validator::requireFields($body, ['frames']);

        if (!is_array($body['frames'])) {
            throw new \RuntimeException('frames must be an array');
        }

        $result = $this->service->ingestFrameBatch(
            (int) $auth['org'],
            $sessionId,
            $body['frames'],
            is_array($body['telemetry'] ?? null) ? $body['telemetry'] : [],
        );

        Response::json(['data' => $result]);
    }

    /**
     * GET /api/v1/live/sessions/{id}/frames — recent frames for real-time display.
     */
    public function frames(array $auth, int $sessionId): never
    {
        $limit  = isset($_GET['limit']) ? min((int) $_GET['limit'], 200) : 50;
        $frames = $this->service->getRecentFrames((int) $auth['org'], $sessionId, $limit);

        Response::json(['data' => $frames]);
    }

    /**
     * GET /api/v1/live/sessions/{id}/stream — SSE stream with session + recent frame updates.
     */
    public function stream(array $auth, int $sessionId): never
    {
        Auth::requireClaims($auth);

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        ignore_user_abort(true);
        set_time_limit(0);

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        echo "retry: 1500\n\n";
        @flush();

        $deadline = microtime(true) + 30.0;
        $lastVersion = 0;

        while (!connection_aborted() && microtime(true) < $deadline) {
            try {
                $snapshot = $this->service->streamSnapshot((int) $auth['org'], $sessionId, 30);
                $version = (int) ($snapshot['stream_version'] ?? 0);

                if ($lastVersion === 0 || $version !== $lastVersion) {
                    echo "event: snapshot\n";
                    echo 'data: ' . json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                    $lastVersion = $version;
                } else {
                    echo ": keepalive\n\n";
                }

                @flush();

                $status = strtolower((string) ($snapshot['session']['status'] ?? ''));
                if (in_array($status, ['completed', 'failed'], true)) {
                    break;
                }
            } catch (\Throwable $e) {
                echo "event: error\n";
                echo 'data: ' . json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                @flush();
                break;
            }

            usleep(1000000);
        }

        exit;
    }
}
