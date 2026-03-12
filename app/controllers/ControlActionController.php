<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\ControlActionService;

final class ControlActionController
{
    public function __construct(private readonly ControlActionService $service) {}

    public function index(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);

        $filters = [
            'scan_id' => isset($_GET['scan_id']) ? (int) $_GET['scan_id'] : null,
            'status' => isset($_GET['status']) ? (string) $_GET['status'] : null,
            'assignee_id' => isset($_GET['assignee_id']) ? (int) $_GET['assignee_id'] : null,
            'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 100,
        ];

        if (($claims['role'] ?? '') === 'worker') {
            $filters['assignee_id'] = Auth::userId($claims);
        }

        $data = $this->service->listByOrganization(Auth::orgId($claims), $filters);
        Response::json(['data' => $data]);
    }

    public function show(array $claims, int $id): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);

        $action = $this->service->findById(Auth::orgId($claims), $id);
        if (($claims['role'] ?? '') === 'worker' && (int) ($action['assigned_to_user_id'] ?? 0) !== Auth::userId($claims)) {
            Response::error('Forbidden: workers can only access assigned actions', 403);
        }

        Response::json(['data' => $action]);
    }

    public function createFromControl(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor']);
        Validator::requireFields($body, ['scan_id', 'control_recommendation_id']);

        $action = $this->service->createFromControlRecommendation(
            Auth::orgId($claims),
            Auth::userId($claims),
            (int) $body['scan_id'],
            (int) $body['control_recommendation_id'],
            $body
        );

        Response::created(['data' => $action]);
    }

    public function update(array $claims, int $id, array $body): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor']);
        $action = $this->service->updateAction(Auth::orgId($claims), $id, $body);
        Response::json(['data' => $action]);
    }

    public function verify(array $claims, int $id, array $body): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor']);
        Validator::requireFields($body, ['verification_scan_id']);

        $action = $this->service->verifyAction(Auth::orgId($claims), $id, $body);
        Response::json(['data' => $action]);
    }
}

