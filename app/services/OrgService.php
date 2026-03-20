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
        'recommendation_policy',
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
        $org['recommendation_policy_defaults'] = RecommendationPolicyDefaults::defaults();

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
                $normalized = $this->normalizeSetting($key, $data[$key]);
                if ($key === 'video_retention_days') {
                    $this->usageMeter->assertRetentionDaysAllowed($orgId, (int) $normalized);
                }
                $current[$key] = $normalized;
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

        $this->usageMeter->assertOrgMemberLimitAvailable($orgId);
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
        $metrics = is_array($usage['metrics'] ?? null) ? $usage['metrics'] : [];
        $memberMetric = is_array($metrics['org_members'] ?? null) ? $metrics['org_members'] : [];

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
                'metrics' => $metrics,
                'violations' => $usage['violations'] ?? [],
                'members_used' => (int) ($memberMetric['used'] ?? 0),
                'member_limit' => $memberMetric['limit'] ?? ($plan['member_limit'] ?? null),
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

    private function normalizeSetting(string $key, mixed $value): mixed
    {
        return match ($key) {
            'default_model' => $this->normalizeDefaultModel($value),
            'video_retention_days' => $this->normalizeRetentionDays($value),
            'auto_delete_video' => $this->toBool($value),
            'recommendation_policy' => $this->normalizeRecommendationPolicy($value),
            default => $value,
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeRecommendationPolicy(mixed $value): array
    {
        $defaults = RecommendationPolicyDefaults::defaults();

        if (!is_array($value)) {
            return $defaults;
        }

        $thresholds = is_array($value['thresholds'] ?? null) ? $value['thresholds'] : [];
        $riskMultipliers = is_array($value['risk_multipliers'] ?? null) ? $value['risk_multipliers'] : [];
        $ranking = is_array($value['ranking'] ?? null) ? $value['ranking'] : [];
        $feasibility = is_array($value['feasibility'] ?? null) ? $value['feasibility'] : [];
        $interim = is_array($value['interim'] ?? null) ? $value['interim'] : [];
        $catalog = is_array($value['catalog'] ?? null) ? $value['catalog'] : [];

        $defaultThresholds = $defaults['thresholds'];
        $defaultRiskMultipliers = $defaults['risk_multipliers'];
        $defaultRanking = $defaults['ranking'];
        $defaultFeasibility = $defaults['feasibility'];
        $defaultInterim = $defaults['interim'];

        return [
            'thresholds' => [
                'trunk_flexion_high' => isset($thresholds['trunk_flexion_high']) ? (float) $thresholds['trunk_flexion_high'] : (float) $defaultThresholds['trunk_flexion_high'],
                'trunk_flexion_moderate' => isset($thresholds['trunk_flexion_moderate']) ? (float) $thresholds['trunk_flexion_moderate'] : (float) $defaultThresholds['trunk_flexion_moderate'],
                'upper_arm_elevation_high' => isset($thresholds['upper_arm_elevation_high']) ? (float) $thresholds['upper_arm_elevation_high'] : (float) $defaultThresholds['upper_arm_elevation_high'],
                'repetition_high' => isset($thresholds['repetition_high']) ? (int) $thresholds['repetition_high'] : (int) $defaultThresholds['repetition_high'],
                'lifting_load' => isset($thresholds['lifting_load']) ? (float) $thresholds['lifting_load'] : (float) $defaultThresholds['lifting_load'],
            ],
            'risk_multipliers' => [
                'high' => isset($riskMultipliers['high']) ? (float) $riskMultipliers['high'] : (float) $defaultRiskMultipliers['high'],
                'moderate' => isset($riskMultipliers['moderate']) ? (float) $riskMultipliers['moderate'] : (float) $defaultRiskMultipliers['moderate'],
                'low' => isset($riskMultipliers['low']) ? (float) $riskMultipliers['low'] : (float) $defaultRiskMultipliers['low'],
            ],
            'ranking' => [
                'cost_penalty_factor' => isset($ranking['cost_penalty_factor']) ? (float) $ranking['cost_penalty_factor'] : (float) $defaultRanking['cost_penalty_factor'],
                'impact_penalty_factor' => isset($ranking['impact_penalty_factor']) ? (float) $ranking['impact_penalty_factor'] : (float) $defaultRanking['impact_penalty_factor'],
                'reduction_factor' => isset($ranking['reduction_factor']) ? (float) $ranking['reduction_factor'] : (float) $defaultRanking['reduction_factor'],
                'strict_hierarchy' => array_key_exists('strict_hierarchy', $ranking) ? $this->toBool($ranking['strict_hierarchy']) : (bool) $defaultRanking['strict_hierarchy'],
                'cost_weight' => is_array($ranking['cost_weight'] ?? null) ? $ranking['cost_weight'] : $defaultRanking['cost_weight'],
                'impact_weight' => is_array($ranking['impact_weight'] ?? null) ? $ranking['impact_weight'] : $defaultRanking['impact_weight'],
                'hierarchy_bonus' => is_array($ranking['hierarchy_bonus'] ?? null) ? $ranking['hierarchy_bonus'] : $defaultRanking['hierarchy_bonus'],
            ],
            'feasibility' => [
                'minimum_total_score' => isset($feasibility['minimum_total_score']) ? (float) $feasibility['minimum_total_score'] : (float) $defaultFeasibility['minimum_total_score'],
                'minimum_policy_compliance' => isset($feasibility['minimum_policy_compliance']) ? (float) $feasibility['minimum_policy_compliance'] : (float) $defaultFeasibility['minimum_policy_compliance'],
                'weights' => is_array($feasibility['weights'] ?? null) ? $feasibility['weights'] : $defaultFeasibility['weights'],
            ],
            'interim' => [
                'max_days_without_interim' => isset($interim['max_days_without_interim']) ? max(1, (int) $interim['max_days_without_interim']) : (int) $defaultInterim['max_days_without_interim'],
                'allow_ppe_interim' => array_key_exists('allow_ppe_interim', $interim) ? $this->toBool($interim['allow_ppe_interim']) : (bool) $defaultInterim['allow_ppe_interim'],
            ],
            'catalog' => $catalog,
        ];
    }

    private function normalizeDefaultModel(mixed $value): ?string
    {
        $model = strtolower(trim((string) $value));
        if ($model === '') {
            return null;
        }

        return in_array($model, ['rula', 'reba', 'niosh'], true) ? $model : null;
    }

    private function normalizeRetentionDays(mixed $value): int
    {
        $days = (int) $value;
        if ($days < 0) {
            return 30;
        }

        return min(3650, $days);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
