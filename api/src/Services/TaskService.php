<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class TaskService
{
    public function __construct(private Connection $db)
    {
    }

    public function create(int $organizationId, string $name, ?string $description, ?string $department): array
    {
        $this->db->executeStatement(
            'INSERT INTO tasks (organization_id, name, description, department, created_at) VALUES (:organization_id, :name, :description, :department, NOW())',
            ['organization_id' => $organizationId, 'name' => $name, 'description' => $description, 'department' => $department]
        );

        return $this->getById($organizationId, (int) $this->db->lastInsertId());
    }

    public function listByOrganization(int $organizationId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, organization_id, name, description, department, created_at FROM tasks WHERE organization_id = :organization_id ORDER BY id DESC',
            ['organization_id' => $organizationId]
        );
    }

    public function getById(int $organizationId, int $taskId): array
    {
        $task = $this->db->fetchAssociative(
            'SELECT id, organization_id, name, description, department, created_at FROM tasks WHERE organization_id = :organization_id AND id = :id LIMIT 1',
            ['organization_id' => $organizationId, 'id' => $taskId]
        );
        if (!$task) {
            throw new RuntimeException('Task not found');
        }
        return $task;
    }
}
