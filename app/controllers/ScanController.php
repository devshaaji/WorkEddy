<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\ScanComparisonService;
use WorkEddy\Services\ScanService;
use WorkEddy\Services\VideoProcessingService;

final class ScanController
{
    public function __construct(
        private readonly ScanService            $scans,
        private readonly VideoProcessingService $videoService,
        private readonly AssessmentEngine       $assessmentEngine,
        private readonly ScanComparisonService  $comparisons,
    ) {}

    /**
     * GET /scans/models — returns available assessment models with metadata.
     */
    public function listModels(): never
    {
        Response::json(['data' => $this->assessmentEngine->modelDescriptors()]);
    }

    public function indexManual(array $claims, ?int $taskId = null): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);
        $orgId = Auth::orgId($claims);
        $status = isset($_GET['status']) ? (string) $_GET['status'] : null;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;
        $data  = $taskId !== null
            ? $this->scans->listByTask($orgId, $taskId, $status, $limit)
            : $this->scans->listByOrganization($orgId, $status, $limit);
        Response::json(['data' => $data]);
    }

    public function createManual(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker']);
        Validator::requireFields($body, ['task_id']);

        $model = isset($body['model']) ? (string) $body['model'] : null;

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

        // Surface PHP upload errors as friendly messages
        $uploadErr = $files['video']['error'] ?? UPLOAD_ERR_OK;
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $msg = match ($uploadErr) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Video exceeds the maximum allowed size (200 MB).',
                UPLOAD_ERR_PARTIAL  => 'Upload was interrupted — please try again.',
                UPLOAD_ERR_NO_FILE  => 'No video file was received.',
                default             => 'Upload failed (error ' . $uploadErr . ').',
            };
            Response::error($msg, 422);
        }

        $model = isset($body['model']) ? (string) $body['model'] : null;

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

    /**
     * GET /scans/compare?scanA={id}&scanB={id}
     */
    public function compareScans(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);

        $scanA = isset($_GET['scanA']) ? (int) $_GET['scanA'] : 0;
        $scanB = isset($_GET['scanB']) ? (int) $_GET['scanB'] : 0;
        if ($scanA <= 0 || $scanB <= 0) {
            Response::error('Query params scanA and scanB are required positive integers', 422);
        }

        try {
            $result = $this->comparisons->compare(Auth::orgId($claims), $scanA, $scanB);
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, 'not found') ? 404 : 422;
            Response::error($msg, $code);
        }
        Response::json(['data' => $result]);
    }
}