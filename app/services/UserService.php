<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use WorkEddy\Repositories\UserRepository;

final class UserService
{
    public function __construct(private readonly UserRepository $users) {}

    public function listByOrganization(int $organizationId): array
    {
        return $this->users->listByOrganization($organizationId);
    }

    public function create(int $organizationId, string $name, string $email, string $password, string $role): array
    {
        $allowed = ['admin', 'supervisor', 'worker', 'observer'];
        if (!in_array($role, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid role. Allowed: ' . implode(', ', $allowed));
        }

        $userId = $this->users->create(
            $organizationId,
            $name,
            $email,
            password_hash($password, PASSWORD_BCRYPT),
            $role
        );

        return [
            'id'              => $userId,
            'organization_id' => $organizationId,
            'name'            => $name,
            'email'           => strtolower($email),
            'role'            => $role,
        ];
    }

    public function findById(int $userId): ?array
    {
        return $this->users->findById($userId);
    }
}
