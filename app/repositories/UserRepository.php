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
            'SELECT id, organization_id, name, email, role,
                    email_verified, two_factor_enabled, two_factor_secret, created_at
             FROM users WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT id, organization_id, name, email, password_hash, role, status,
                    email_verified, email_otp, email_otp_expires_at,
                    two_factor_enabled, two_factor_secret, created_at
             FROM users WHERE email = :email LIMIT 1',
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

    public function updateProfile(int $id, string $name, string $email): void
    {
        $this->db->executeStatement(
            'UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'name' => $name, 'email' => strtolower($email)]
        );
    }

    /* ── OTP helpers ───────────────────────────────────────────────────── */

    public function setEmailOtp(int $id, string $otp, int $ttlMinutes = 10): void
    {
        $this->db->executeStatement(
            'UPDATE users SET email_otp = :otp, email_otp_expires_at = DATE_ADD(NOW(), INTERVAL :ttl MINUTE), updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'otp' => $otp, 'ttl' => $ttlMinutes]
        );
    }

    public function clearEmailOtp(int $id): void
    {
        $this->db->executeStatement(
            'UPDATE users SET email_otp = NULL, email_otp_expires_at = NULL, updated_at = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }

    public function verifyEmailOtp(int $id, string $otp): bool
    {
        $row = $this->db->fetchAssociative(
            'SELECT email_otp, email_otp_expires_at FROM users WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!$row || $row['email_otp'] !== $otp) return false;
        if ($row['email_otp_expires_at'] && strtotime($row['email_otp_expires_at']) < time()) return false;
        $this->clearEmailOtp($id);
        return true;
    }

    public function markEmailVerified(int $id): void
    {
        $this->db->executeStatement(
            'UPDATE users SET email_verified = 1, updated_at = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }

    /* ── 2FA helpers ───────────────────────────────────────────────────── */

    public function enable2fa(int $id, string $secret): void
    {
        $this->db->executeStatement(
            'UPDATE users SET two_factor_enabled = 1, two_factor_secret = :secret, updated_at = NOW() WHERE id = :id',
            ['id' => $id, 'secret' => $secret]
        );
    }

    public function disable2fa(int $id): void
    {
        $this->db->executeStatement(
            'UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL, updated_at = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }

    public function get2faSecret(int $id): ?string
    {
        $row = $this->db->fetchAssociative(
            'SELECT two_factor_secret FROM users WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        return $row ? ($row['two_factor_secret'] ?: null) : null;
    }
}