<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class TaskRepository
{
    public function __construct(private readonly Connection $db) {}

    public function create(int $organizationId, string $name, ?string $description, ?string $workstation, ?string $department): array
    {
        $this->db->executeStatement(
            'INSERT INTO tasks (organization_id, name, description, workstation, department, created_at) VALUES (:org_id, :name, :desc, :ws, :dept, NOW())',
            ['org_id' => $organizationId, 'name' => $name, 'desc' => $description, 'ws' => $workstation, 'dept' => $department]
        );
        return $this->findById($organizationId, (int) $this->db->lastInsertId());
    }

    public function listByOrganization(int $organizationId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, organization_id, name, description, workstation, department, created_at FROM tasks WHERE organization_id = :org_id ORDER BY id DESC',
            ['org_id' => $organizationId]
        );
    }

    public function findById(int $organizationId, int $taskId): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, organization_id, name, description, workstation, department, created_at FROM tasks WHERE organization_id = :org_id AND id = :id LIMIT 1',
            ['org_id' => $organizationId, 'id' => $taskId]
        );
        if (!$row) {
            throw new RuntimeException('Task not found');
        }
        return $row;
    }
}