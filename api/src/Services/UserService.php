<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

use Doctrine\DBAL\Connection;

final class UserService
{
    public function __construct(private Connection $db)
    {
    }

    public function listByOrganization(int $organizationId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, organization_id, name, email, role, created_at FROM users WHERE organization_id = :organization_id ORDER BY id DESC',
            ['organization_id' => $organizationId]
        );
    }

    public function create(int $organizationId, string $name, string $email, string $password, string $role): array
    {
        $this->db->executeStatement(
            'INSERT INTO users (organization_id, name, email, password_hash, role, created_at) VALUES (:organization_id, :name, :email, :password_hash, :role, NOW())',
            [
                'organization_id' => $organizationId,
                'name' => $name,
                'email' => strtolower($email),
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role' => $role,
            ]
        );

        return [
            'id' => (int) $this->db->lastInsertId(),
            'organization_id' => $organizationId,
            'name' => $name,
            'email' => strtolower($email),
            'role' => $role,
        ];
    }
}
