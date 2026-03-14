<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use RuntimeException;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Helpers\InternalRequestAuth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Helpers\WorkerContract;
use WorkEddy\Services\LiveSessionService;

/**
 * Internal live-worker callback endpoints (token-authenticated).
 *
 * The live-worker calls these to report frame batches, completions, and failures.
 * Same security model as WorkerController: X-Worker-Token header.
 */
final class LiveWorkerController
{
    public function __construct(
        private readonly LiveSessionService $sessions,
        private readonly QueueInterface     $queue,
    ) {}

    /**
     * POST /api/v1/internal/live-worker/jobs/next — dequeue next live session job.
     */
    public function nextJob(): never
    {
        InternalRequestAuth::requireWorkerToken();

        $job = $this->queue->dequeue(WorkerContract::liveQueueName());
        if ($job === null) {
            Response::noContent();
        }

        Response::json(['data' => $job]);
    }

    /**
     * POST /api/v1/internal/live-worker/frame-batches/next — dequeue next browser frame batch.
     */
    public function nextFrameBatch(): never
    {
        InternalRequestAuth::requireWorkerToken();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $job = $this->queue->dequeue(WorkerContract::liveFrameQueueName());
            if ($job === null) {
                Response::noContent();
            }

            $sessionId = (int) ($job['session_id'] ?? 0);
            $organizationId = (int) ($job['organization_id'] ?? 0);

            if ($sessionId < 1 || $organizationId < 1) {
                continue;
            }

            if (!$this->sessions->isSessionAcceptingFrames($organizationId, $sessionId)) {
                continue;
            }

            Response::json(['data' => $job]);
        }

        Response::noContent();
    }

    /**
     * POST /api/v1/internal/live-worker/frames — report a batch of analysed frames.
     */
    public function reportFrames(array $body): never
    {
        InternalRequestAuth::requireWorkerToken();
        Validator::requireFields($body, WorkerContract::requiredFields('live', 'frames'));

        if (!is_array($body['frames'])) {
            throw new RuntimeException('frames must be an array');
        }

        $telemetry = $body['telemetry'] ?? [];
        if (!is_array($telemetry)) {
            throw new RuntimeException('telemetry must be a JSON object');
        }

        try {
            $result = $this->sessions->recordFrameBatch(
                (int) $body['session_id'],
                (int) $body['organization_id'],
                $body['frames'],
                $telemetry,
            );
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Session is not active') {
                Response::json([
                    'data' => [
                        'ignored' => count($body['frames']),
                        'reason' => 'session_inactive',
                    ],
                ]);
            }

            throw $e;
        }

        Response::json(['data' => $result]);
    }

    /**
     * POST /api/v1/internal/live-worker/sessions/complete — complete a live session.
     */
    public function complete(array $body): never
    {
        InternalRequestAuth::requireWorkerToken();
        Validator::requireFields($body, WorkerContract::requiredFields('live', 'complete'));

        $summary = $body['summary_metrics'] ?? [];
        if (!is_array($summary)) {
            throw new RuntimeException('summary_metrics must be a JSON object');
        }

        $session = $this->sessions->completeSessionFromWorker(
            (int) $body['session_id'],
            (int) $body['organization_id'],
            $summary,
        );

        Response::json(['data' => $session]);
    }

    /**
     * POST /api/v1/internal/live-worker/sessions/fail — mark session as failed.
     */
    public function fail(array $body): never
    {
        InternalRequestAuth::requireWorkerToken();
        Validator::requireFields($body, WorkerContract::requiredFields('live', 'fail'));

        $this->sessions->failSessionFromWorker(
            (int) $body['session_id'],
            (int) $body['organization_id'],
            isset($body['error_message']) ? (string) $body['error_message'] : 'Live processing failed',
        );

        Response::json(['message' => 'Session marked as failed']);
    }
}
