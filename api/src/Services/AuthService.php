<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class AuthService
{
    public function __construct(
        private Connection $db,
        private JwtService $jwtService,
        private BillingService $billingService,
    ) {
    }

    public function signup(string $name, string $email, string $password, string $organizationName): array
    {
        return $this->db->transactional(function () use ($name, $email, $password, $organizationName): array {
            $this->db->executeStatement(
                'INSERT INTO organizations (name, plan, created_at) VALUES (:name, :plan, NOW())',
                ['name' => $organizationName, 'plan' => 'starter']
            );
            $organizationId = (int) $this->db->lastInsertId();

            $this->db->executeStatement(
                'INSERT INTO users (organization_id, name, email, password_hash, role, created_at) VALUES (:organization_id, :name, :email, :password_hash, :role, NOW())',
                [
                    'organization_id' => $organizationId,
                    'name' => $name,
                    'email' => strtolower($email),
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'role' => 'admin',
                ]
            );
            $userId = (int) $this->db->lastInsertId();

            $this->billingService->createDefaultSubscription($organizationId);

            return [
                'token' => $this->jwtService->issueToken($userId, $organizationId, 'admin'),
                'user' => ['id' => $userId, 'organization_id' => $organizationId, 'name' => $name, 'email' => strtolower($email), 'role' => 'admin'],
            ];
        });
    }

    public function login(string $email, string $password): array
    {
        $user = $this->db->fetchAssociative(
            'SELECT id, organization_id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1',
            ['email' => strtolower($email)]
        );

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Invalid credentials');
        }

        return [
            'token' => $this->jwtService->issueToken((int) $user['id'], (int) $user['organization_id'], (string) $user['role']),
            'user' => [
                'id' => (int) $user['id'],
                'organization_id' => (int) $user['organization_id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'role' => (string) $user['role'],
            ],
        ];
    }
}
