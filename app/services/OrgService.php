<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Repositories\UserRepository;
use WorkEddy\Repositories\WorkspaceRepository;

final class OrgService
{
    public function __construct(
        private readonly WorkspaceRepository $workspaceRepo,
        private readonly UserRepository $userRepo
    ) {}

    /* ── Settings ─────────────────────────────────────────────────────── */

    public function getSettings(int $orgId): array
    {
        return $this->workspaceRepo->findById($orgId);
    }

    public function updateSettings(int $orgId, array $data): void
    {
        $allowed  = ['name', 'slug', 'contact_email'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if (isset($filtered['name']) && !isset($filtered['slug'])) {
            $filtered['slug'] = trim(
                strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($filtered['name']))),
                '-'
            );
        }

        $this->workspaceRepo->updateOrg($orgId, $filtered);
    }

    /* ── Members ──────────────────────────────────────────────────────── */

    public function listMembers(int $orgId): array
    {
        return $this->userRepo->listByOrganization($orgId);
    }

    public function inviteMember(
        int $orgId,
        string $name,
        string $email,
        string $role,
        string $password
    ): array {
        $allowed = ['admin', 'supervisor', 'worker', 'observer'];
        if (!in_array($role, $allowed, true)) {
            throw new RuntimeException('Invalid role. Allowed: ' . implode(', ', $allowed));
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $id   = $this->userRepo->create($orgId, $name, $email, $hash, $role);

        return [
            'id'    => $id,
            'name'  => $name,
            'email' => strtolower($email),
            'role'  => $role,
        ];
    }

    public function updateMemberRole(int $orgId, int $userId, string $role): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || (int) $user['organization_id'] !== $orgId) {
            throw new RuntimeException('User not found in this organization');
        }

        $allowed = ['admin', 'supervisor', 'worker', 'observer'];
        if (!in_array($role, $allowed, true)) {
            throw new RuntimeException('Invalid role. Allowed: ' . implode(', ', $allowed));
        }

        $this->userRepo->updateRole($userId, $role);
    }

    public function removeMember(int $orgId, int $userId): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || (int) $user['organization_id'] !== $orgId) {
            throw new RuntimeException('User not found in this organization');
        }

        $this->userRepo->updateStatus($userId, 'inactive');
    }

    /* ── Billing / Subscription ───────────────────────────────────────── */

    public function getSubscription(int $orgId): array
    {
        $plan = $this->workspaceRepo->activePlan($orgId);
        $used = $this->workspaceRepo->monthlyUsageCount($orgId);
        $limit = $plan['scan_limit'] !== null ? (int) $plan['scan_limit'] : null;

        return [
            'plan'  => $plan,
            'usage' => [
                'month'     => date('Y-m'),
                'used'      => $used,
                'limit'     => $limit,
                'remaining' => $limit !== null ? max(0, $limit - $used) : null,
            ],
        ];
    }

    public function changePlan(int $orgId, int $planId): void
    {
        $this->workspaceRepo->deactivateSubscriptions($orgId);
        $this->workspaceRepo->createSubscription($orgId, $planId);
    }
}
