<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Repositories\AdminRepository;

final class AdminService
{
    public function __construct(private readonly AdminRepository $adminRepo) {}

    /* ── Organizations ───────────────────────────────────────────────── */

    public function listOrganizations(): array
    {
        return $this->adminRepo->listAllOrganizations();
    }

    public function showOrganization(int $id): array
    {
        $org = $this->adminRepo->findOrganizationById($id);
        if (!$org) {
            throw new RuntimeException('Organization not found');
        }
        return $org;
    }

    public function createOrganization(string $name, ?string $contactEmail): array
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name)));
        $slug = trim($slug, '-');
        $id   = $this->adminRepo->createOrganization($name, $slug, $contactEmail);

        return ['id' => $id, 'name' => $name, 'slug' => $slug];
    }

    public function updateOrganization(int $id, array $data): void
    {
        $org = $this->adminRepo->findOrganizationById($id);
        if (!$org) {
            throw new RuntimeException('Organization not found');
        }

        $allowed  = ['name', 'slug', 'contact_email', 'status'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if (isset($filtered['name']) && !isset($filtered['slug'])) {
            $filtered['slug'] = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($filtered['name']))), '-');
        }

        $this->adminRepo->updateOrganization($id, $filtered);
    }

    /* ── Users (global) ──────────────────────────────────────────────── */

    public function listUsers(): array
    {
        return $this->adminRepo->listAllUsers();
    }

    public function updateUser(int $id, array $data): void
    {
        $allowed  = ['name', 'email', 'role', 'status', 'organization_id'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if (isset($filtered['role'])) {
            $validRoles = ['super_admin', 'admin', 'supervisor', 'worker', 'observer'];
            if (!in_array($filtered['role'], $validRoles, true)) {
                throw new RuntimeException('Invalid role. Allowed: ' . implode(', ', $validRoles));
            }
        }

        if (isset($filtered['status'])) {
            $validStatuses = ['active', 'inactive', 'invited'];
            if (!in_array($filtered['status'], $validStatuses, true)) {
                throw new RuntimeException('Invalid status. Allowed: ' . implode(', ', $validStatuses));
            }
        }

        $this->adminRepo->updateUser($id, $filtered);
    }

    public function deleteUser(int $id): void
    {
        $this->adminRepo->deleteUser($id);
    }

    /* ── Plans ────────────────────────────────────────────────────────── */

    public function listPlans(): array
    {
        return $this->adminRepo->listPlans();
    }

    public function createPlan(
        string $name,
        ?int $scanLimit,
        float $price,
        string $billingCycle = 'monthly',
        array $billingLimits = [],
        string $status = 'active',
    ): array {
        $normalizedLimits = PlanBillingDefaults::normalize($billingLimits, $name, $scanLimit);
        $id = $this->adminRepo->createPlan($name, $scanLimit, $price, $billingCycle, $normalizedLimits, $status);

        return [
            'id'            => $id,
            'name'          => $name,
            'scan_limit'    => $scanLimit,
            'price'         => $price,
            'billing_cycle' => $billingCycle,
            'status'        => $status,
            'billing_limits' => $normalizedLimits,
        ];
    }

    public function updatePlan(int $id, array $data): void
    {
        $plan = $this->adminRepo->findPlanById($id);
        if (!$plan) {
            throw new RuntimeException('Plan not found');
        }

        $allowed  = ['name', 'scan_limit', 'price', 'billing_cycle', 'status'];
        $filtered = array_intersect_key($data, array_flip($allowed));
        $filtered['billing_limits_json'] = $this->extractBillingLimits($plan, $data);

        $this->adminRepo->updatePlan($id, $filtered);
    }

    public function deletePlan(int $id): void
    {
        $plan = $this->adminRepo->findPlanById($id);
        if (!$plan) {
            throw new RuntimeException('Plan not found');
        }
        $this->adminRepo->deletePlan($id);
    }

    /* ── System Settings ─────────────────────────────────────────────── */

    private const SYSTEM_KEYS = [
        'app_name', 'support_email', 'allow_registrations',
        'payment_gateway', 'payment_public_key', 'payment_secret_key',
    ];

    public function getSystemSettings(): array
    {
        return $this->adminRepo->getSystemSettings();
    }

    public function updateSystemSettings(array $data): void
    {
        foreach (self::SYSTEM_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $this->adminRepo->upsertSystemSetting($key, $data[$key]);
            }
        }
    }

    /* ── System Stats ─────────────────────────────────────────────────── */

    public function systemStats(): array
    {
        return $this->adminRepo->systemStats();
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,int|null>
     */
    public function extractBillingLimitsFromPayload(array $payload, ?string $planName = null, ?int $scanLimit = null): array
    {
        $limits = is_array($payload['billing_limits'] ?? null) ? $payload['billing_limits'] : [];

        foreach (PlanBillingDefaults::KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                $limits[$key] = $payload[$key];
            }
        }

        return PlanBillingDefaults::normalize($limits, $planName, $scanLimit);
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $payload
     * @return array<string,int|null>
     */
    private function extractBillingLimits(array $plan, array $payload): array
    {
        $name = (string) ($payload['name'] ?? $plan['name'] ?? '');
        $scanLimit = array_key_exists('scan_limit', $payload)
            ? ($payload['scan_limit'] !== null && $payload['scan_limit'] !== '' ? (int) $payload['scan_limit'] : null)
            : (isset($plan['scan_limit']) && $plan['scan_limit'] !== null ? (int) $plan['scan_limit'] : null);
        $current = is_array($plan['billing_limits'] ?? null) ? $plan['billing_limits'] : [];
        $limits = is_array($payload['billing_limits'] ?? null) ? array_replace($current, $payload['billing_limits']) : $current;

        foreach (PlanBillingDefaults::KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                $limits[$key] = $payload[$key];
            }
        }

        return PlanBillingDefaults::normalize($limits, $name, $scanLimit);
    }
}
