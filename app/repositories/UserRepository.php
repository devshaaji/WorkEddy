<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class UserRepository
{
    public function __construct(private readonly Connection $db) {}

    public function findById(int $id): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, organization_id, name, email, role, created_at FROM users WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, organization_id, name, email, password_hash, role, created_at FROM users WHERE email = :email LIMIT 1',
            ['email' => strtolower($email)]
        );
        return $row ?: null;
    }

    public function listByOrganization(int $organizationId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, organization_id, name, email, role, created_at FROM users WHERE organization_id = :org_id ORDER BY id DESC',
            ['org_id' => $organizationId]
        );
    }

    public function create(int $organizationId, string $name, string $email, string $passwordHash, string $role): int
    {
        $this->db->executeStatement(
            'INSERT INTO users (organization_id, name, email, password_hash, role, created_at) VALUES (:org_id, :name, :email, :hash, :role, NOW())',
            ['org_id' => $organizationId, 'name' => $name, 'email' => strtolower($email), 'hash' => $passwordHash, 'role' => $role]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateRole(int $id, string $role): void
    {
        $this->db->executeStatement(
            'UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'role' => $role]
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->executeStatement(
            'UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'status' => $status]
        );
    }
}