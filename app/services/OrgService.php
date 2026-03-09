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
        private readonly UserRepository $userRepo,
        private readonly UsageMeterService $usageMeter,
        private readonly BillingService $billing,
    ) {}

    /* ── Settings ─────────────────────────────────────────────────────── */

    /** Keys stored inside the JSON `settings` column. */
    private const SETTINGS_KEYS = [
        'industry', 'size', 'website', 'theme_color',
        'video_retention_days', 'auto_delete_video', 'default_model',
    ];

    public function getSettings(int $orgId): array
    {
        $org = $this->workspaceRepo->findById($orgId);

        // Decode JSON settings and merge into the flat org array
        $json = [];
        if (!empty($org['settings'])) {
            $json = is_string($org['settings'])
                ? (json_decode($org['settings'], true) ?: [])
                : (array) $org['settings'];
        }
        unset($org['settings']);

        foreach (self::SETTINGS_KEYS as $key) {
            $org[$key] = $json[$key] ?? null;
        }

        // Add member count
        $members = $this->userRepo->listByOrganization($orgId);
        $org['member_count'] = count($members);

        return $org;
    }

    public function updateSettings(int $orgId, array $data): void
    {
        // Core org columns
        $allowed  = ['name', 'slug', 'contact_email'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if (isset($filtered['name']) && !isset($filtered['slug'])) {
            $filtered['slug'] = trim(
                strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($filtered['name']))),
                '-'
            );
        }

        // Build JSON settings payload (merge with existing)
        $existing = $this->workspaceRepo->findById($orgId);
        $current  = [];
        if (!empty($existing['settings'])) {
            $current = is_string($existing['settings'])
                ? (json_decode($existing['settings'], true) ?: [])
                : (array) $existing['settings'];
        }
        $changed = false;
        foreach (self::SETTINGS_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $current[$key] = $data[$key];
                $changed = true;
            }
        }
        if ($changed) {
            $filtered['settings'] = json_encode($current, JSON_UNESCAPED_UNICODE);
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
        $snapshot = $this->usageMeter->currentUsage($orgId);
        $plan = $snapshot['plan'];
        $usage = $snapshot['usage'];

        return [
            'plan'  => $plan,
            'usage' => [
                'month' => $usage['month'],
                'used' => $usage['used_scans'],
                'limit' => $plan['scan_limit'],
                'remaining' => $usage['remaining_scans'],
                'reserved_scans' => $usage['reserved_scans'],
                'billed_scans' => $usage['billed_scans'],
                'billing_cycle' => $usage['billing_cycle'],
                'period_start' => $usage['period_start'],
                'period_end' => $usage['period_end'],
            ],
        ];
    }

    public function changePlan(int $orgId, int $planId): void
    {
        $this->workspaceRepo->deactivateSubscriptions($orgId);
        $this->workspaceRepo->createSubscription($orgId, $planId);

        // Create the invoice for the new active plan period.
        $this->billing->ensureCurrentPeriodInvoice($orgId);
    }
}
