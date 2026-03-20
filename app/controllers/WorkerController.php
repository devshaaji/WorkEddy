<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use RuntimeException;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Helpers\InternalRequestAuth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Helpers\WorkerContract;
use WorkEddy\Services\ScanService;

final class WorkerController
{
    public function __construct(
        private readonly ScanService    $scans,
        private readonly QueueInterface $queue,
    ) {}

    public function nextJob(): never
    {
        InternalRequestAuth::requireWorkerToken();

        $job = $this->queue->dequeue(WorkerContract::videoQueueName());
        if ($job === null) {
            Response::noContent();
        }

        Response::json(['data' => $job]);
    }

    public function complete(array $body): never
    {
        InternalRequestAuth::requireWorkerToken();
        Validator::requireFields($body, WorkerContract::requiredFields('video', 'complete'));

        if (!is_array($body['metrics'])) {
            throw new RuntimeException('metrics must be a JSON object');
        }

        $scan = $this->scans->completeVideoScanFromWorker(
            (int) $body['organization_id'],
            (int) $body['scan_id'],
            $body['metrics'],
            isset($body['model']) ? (string) $body['model'] : null,
            isset($body['pose_video_path']) ? (string) $body['pose_video_path'] : null
        );

        Response::json(['data' => $scan]);
    }

    public function fail(array $body): never
    {
        InternalRequestAuth::requireWorkerToken();
        Validator::requireFields($body, WorkerContract::requiredFields('video', 'fail'));

        $this->scans->failVideoScanFromWorker(
            (int) $body['organization_id'],
            (int) $body['scan_id'],
            isset($body['error_message']) ? (string) $body['error_message'] : 'Processing failed'
        );

        Response::json(['message' => 'Scan marked invalid']);
    }
}
