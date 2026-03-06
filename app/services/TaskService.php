<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use WorkEddy\Repositories\TaskRepository;

final class TaskService
{
    public function __construct(private readonly TaskRepository $tasks) {}

    public function create(int $organizationId, string $name, ?string $description, ?string $workstation, ?string $department): array
    {
        return $this->tasks->create($organizationId, $name, $description, $workstation, $department);
    }

    public function listByOrganization(int $organizationId): array
    {
        return $this->tasks->listByOrganization($organizationId);
    }

    public function getById(int $organizationId, int $taskId): array
    {
        return $this->tasks->findById($organizationId, $taskId);
    }
}
