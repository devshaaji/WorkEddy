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
            null,
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
     * GET /api/v1/live/sessions/{id}/frames — recent frames for real-time display.
     */
    public function frames(array $auth, int $sessionId): never
    {
        $limit  = isset($_GET['limit']) ? min((int) $_GET['limit'], 200) : 50;
        $frames = $this->service->getRecentFrames((int) $auth['org'], $sessionId, $limit);

        Response::json(['data' => $frames]);
    }
}
