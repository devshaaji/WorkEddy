<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Repositories\UserRepository;
use WorkEddy\Repositories\WorkspaceRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository      $users,
        private readonly WorkspaceRepository $workspaces,
        private readonly JwtService          $jwt,
    ) {}

    public function signup(string $name, string $email, string $password, string $organizationName): array
    {
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('Email already registered');
        }

        $orgId  = $this->workspaces->create($organizationName);
        $planId = $this->workspaces->starterPlanId();
        $this->workspaces->createSubscription($orgId, $planId);

        $userId = $this->users->create(
            $orgId,
            $name,
            $email,
            password_hash($password, PASSWORD_BCRYPT),
            'admin'
        );

        return [
            'token' => $this->jwt->issueToken($userId, $orgId, 'admin'),
            'user'  => [
                'id'              => $userId,
                'organization_id' => $orgId,
                'name'            => $name,
                'email'           => strtolower($email),
                'role'            => 'admin',
            ],
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Invalid credentials');
        }

        return [
            'token' => $this->jwt->issueToken((int) $user['id'], (int) $user['organization_id'], (string) $user['role']),
            'user'  => [
                'id'              => (int)    $user['id'],
                'organization_id' => (int)    $user['organization_id'],
                'name'            => (string) $user['name'],
                'email'           => (string) $user['email'],
                'role'            => (string) $user['role'],
            ],
        ];
    }
}
