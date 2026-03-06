<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\TaskService;

final class TaskController
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);
        Response::json(['data' => $this->tasks->listByOrganization(Auth::orgId($claims))]);
    }

    public function create(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor']);
        Validator::requireFields($body, ['name']);

        $task = $this->tasks->create(
            Auth::orgId($claims),
            $body['name'],
            $body['description'] ?? null,
            $body['workstation'] ?? null,
            $body['department']  ?? null
        );

        Response::created(['data' => $task]);
    }

    public function show(array $claims, int $id): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);
        Response::json(['data' => $this->tasks->getById(Auth::orgId($claims), $id)]);
    }
}