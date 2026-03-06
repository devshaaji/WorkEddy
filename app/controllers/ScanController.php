<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\ScanService;
use WorkEddy\Services\VideoProcessingService;

final class ScanController
{
    public function __construct(
        private readonly ScanService            $scans,
        private readonly VideoProcessingService $videoService,
        private readonly AssessmentEngine       $assessmentEngine,
    ) {}

    /**
     * GET /scans/models — returns available assessment models with metadata.
     */
    public function listModels(): never
    {
        Response::json(['data' => $this->assessmentEngine->modelDescriptors()]);
    }

    public function indexManual(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);
        Response::json(['data' => $this->scans->listByOrganization(Auth::orgId($claims))]);
    }

    public function createManual(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker']);
        Validator::requireFields($body, ['task_id']);

        $model = $body['model'] ?? 'reba';

        $scan = $this->scans->createManualScan(
            Auth::orgId($claims),
            Auth::userId($claims),
            (int) $body['task_id'],
            $model,
            $body
        );

        Response::created(['data' => $scan]);
    }

    public function createVideo(array $claims, array $body, array $files): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker']);
        Validator::requireFields($body, ['task_id']);

        if (empty($files['video'])) {
            Response::error('Missing video file', 422);
        }

        $model = $body['model'] ?? 'reba';

        $videoPath = $this->videoService->storeUploadedFile($files['video']);

        $scan = $this->scans->createVideoScan(
            Auth::orgId($claims),
            Auth::userId($claims),
            (int) $body['task_id'],
            $model,
            $videoPath,
            isset($body['parent_scan_id']) ? (int) $body['parent_scan_id'] : null
        );

        Response::created(['data' => $scan]);
    }

    public function show(array $claims, int $id): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);
        Response::json(['data' => $this->scans->getById(Auth::orgId($claims), $id)]);
    }

    /**
     * GET /scans/{id}/compare — compare current scan with its parent (repeat scan).
     */
    public function compare(array $claims, int $id): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);
        Response::json(['data' => $this->scans->compare(Auth::orgId($claims), $id)]);
    }
}