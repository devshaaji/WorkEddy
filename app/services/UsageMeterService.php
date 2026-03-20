<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use DateTimeImmutable;
use RuntimeException;
use WorkEddy\Repositories\CopilotAuditRepository;
use WorkEddy\Repositories\LiveSessionRepository;
use WorkEddy\Repositories\UserRepository;
use WorkEddy\Repositories\WorkspaceRepository;

final class UsageMeterService
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly BillingPeriodService $periods,
        private readonly ?LiveSessionRepository $liveSessions = null,
        private readonly ?CopilotAuditRepository $copilotAudits = null,
        private readonly ?UserRepository $users = null,
    ) {}

    public function currentUsage(int $organizationId, ?DateTimeImmutable $now = null): array
    {
        $plan = $this->workspaces->activePlan($organizationId);
        $currentTime = $now ?? new DateTimeImmutable('now');
        $limit = $plan['scan_limit'] !== null ? (int) $plan['scan_limit'] : null;
        $billingLimits = PlanBillingDefaults::normalize(
            is_array($plan['billing_limits'] ?? null) ? $plan['billing_limits'] : ($plan['billing_limits_json'] ?? null),
            (string) ($plan['name'] ?? ''),
            $limit,
        );
        $period = $this->periods->currentPeriod(
            (string) $plan['start_date'],
            (string) ($plan['billing_cycle'] ?? 'monthly'),
            $currentTime,
        );

        $billed = $this->workspaces->usageCountForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
        );
        $reserved = $this->workspaces->reservationCountForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
        );
        $manualBilled = $this->workspaces->usageCountForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
            'manual_scan',
        );
        $videoBilled = $this->workspaces->usageCountForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
            'video_scan',
        );
        $manualReserved = $this->workspaces->reservationCountForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
            'manual_scan',
        );
        $videoReserved = $this->workspaces->reservationCountForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
            'video_scan',
        );
        $usedForEnforcement = $billed + $reserved;
        $liveSessionCount = $this->liveSessions?->countStartedSessionsForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
        ) ?? 0;
        $liveSessionMinutes = $this->liveSessions?->sumSessionMinutesForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
            $currentTime,
        ) ?? 0;
        $llmRequests = $this->copilotAudits?->sumLlmRequestCountForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
        ) ?? 0;
        $llmTokens = $this->copilotAudits?->sumLlmTotalTokensForPeriod(
            $organizationId,
            $period['period_start'],
            $period['period_end'],
        ) ?? 0;
        $memberCount = $this->users?->countActiveByOrganization($organizationId) ?? 0;
        $retentionDays = 30;
        try {
            $retentionDays = $this->normalizeInt(
                $this->workspaces->organizationSetting($organizationId, 'video_retention_days', 30),
                30
            );
        } catch (RuntimeException) {
            $retentionDays = 30;
        }
        $openLiveSessions = $this->liveSessions?->countOpenSessionsByOrganization($organizationId) ?? 0;

        $metrics = [
            'manual_scans' => $this->metricSnapshot($manualBilled, $limit, $manualReserved),
            'video_scans' => $this->metricSnapshot($videoBilled, $billingLimits['video_scan_limit'], $videoReserved),
            'live_sessions' => $this->metricSnapshot($liveSessionCount, $billingLimits['live_session_limit']),
            'live_session_minutes' => $this->metricSnapshot($liveSessionMinutes, $billingLimits['live_session_minutes_limit']),
            'llm_requests' => $this->metricSnapshot($llmRequests, $billingLimits['llm_request_limit']),
            'llm_tokens' => $this->metricSnapshot($llmTokens, $billingLimits['llm_token_limit']),
            'org_members' => $this->metricSnapshot($memberCount, $billingLimits['max_org_members']),
            'video_retention_days' => $this->metricSnapshot($retentionDays, $billingLimits['max_video_retention_days']),
            'live_concurrent_sessions' => $this->metricSnapshot($openLiveSessions, $billingLimits['max_live_concurrent_sessions']),
        ];
        $violations = [];
        foreach ($metrics as $key => $metric) {
            if (($metric['exceeded'] ?? false) === true) {
                $violations[] = $key;
            }
        }

        return [
            'plan' => [
                'id' => (int) $plan['id'],
                'subscription_id' => (int) ($plan['subscription_id'] ?? 0),
                'name' => (string) $plan['name'],
                'scan_limit' => $limit,
                'price' => (float) $plan['price'],
                'status' => (string) $plan['status'],
                'billing_cycle' => (string) ($plan['billing_cycle'] ?? 'monthly'),
                'billing_limits' => $billingLimits,
                'member_limit' => $billingLimits['max_org_members'],
                'live_concurrent_session_limit' => $billingLimits['max_live_concurrent_sessions'],
            ],
            'usage' => [
                // Backwards-compatible key.
                'month' => $currentTime->format('Y-m'),
                'used_scans' => $usedForEnforcement,
                'remaining_scans' => $limit === null ? null : max(0, $limit - $usedForEnforcement),
                'limit_exceeded' => $limit !== null && $usedForEnforcement >= $limit,
                'billed_scans' => $billed,
                'reserved_scans' => $reserved,
                'period_start' => $period['period_start']->format('Y-m-d H:i:s'),
                'period_end' => $period['period_end']->format('Y-m-d H:i:s'),
                'billing_cycle' => $period['billing_cycle'],
                'metrics' => $metrics,
                'violations' => $violations,
            ],
        ];
    }

    public function assertAvailable(int $organizationId): void
    {
        $snapshot = $this->currentUsage($organizationId);
        if ($snapshot['usage']['limit_exceeded'] === true) {
            throw new RuntimeException('Scan limit exceeded for current plan period');
        }
    }

    public function assertVideoScanAvailable(int $organizationId): void
    {
        $this->assertAvailable($organizationId);

        $snapshot = $this->currentUsage($organizationId);
        $metric = $snapshot['usage']['metrics']['video_scans'] ?? null;
        if (is_array($metric) && ($metric['exceeded'] ?? false) === true) {
            throw new RuntimeException('Video worker limit exceeded for current plan period');
        }
    }

    public function assertLiveSessionAvailable(int $organizationId): void
    {
        $snapshot = $this->currentUsage($organizationId);

        foreach (['live_sessions' => 'Live session limit exceeded for current plan period', 'live_session_minutes' => 'Live session minute budget exceeded for current plan period'] as $key => $message) {
            $metric = $snapshot['usage']['metrics'][$key] ?? null;
            if (is_array($metric) && ($metric['exceeded'] ?? false) === true) {
                throw new RuntimeException($message);
            }
        }
    }

    public function assertOrgMemberLimitAvailable(int $organizationId, int $additionalMembers = 1): void
    {
        $snapshot = $this->currentUsage($organizationId);
        $metric = $snapshot['usage']['metrics']['org_members'] ?? null;
        if (!is_array($metric) || ($metric['limit'] ?? null) === null) {
            return;
        }

        $limit = (int) $metric['limit'];
        $current = (int) ($metric['used'] ?? 0);
        if (($current + max(0, $additionalMembers)) > $limit) {
            throw new RuntimeException('Organization member limit exceeded for current plan');
        }
    }

    public function assertRetentionDaysAllowed(int $organizationId, int $days): void
    {
        $snapshot = $this->currentUsage($organizationId);
        $metric = $snapshot['usage']['metrics']['video_retention_days'] ?? null;
        if (!is_array($metric) || ($metric['limit'] ?? null) === null) {
            return;
        }

        if ($days > (int) $metric['limit']) {
            throw new RuntimeException('Video retention period exceeds plan allowance');
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function llmBudget(int $organizationId): array
    {
        $snapshot = $this->currentUsage($organizationId);
        $requests = $snapshot['usage']['metrics']['llm_requests'] ?? [];
        $tokens = $snapshot['usage']['metrics']['llm_tokens'] ?? [];

        if (is_array($requests) && ($requests['exceeded'] ?? false) === true) {
            return [
                'allowed' => false,
                'error_code' => 'llm_request_limit_exceeded',
                'metrics' => ['llm_requests' => $requests, 'llm_tokens' => $tokens],
            ];
        }

        if (is_array($tokens) && ($tokens['exceeded'] ?? false) === true) {
            return [
                'allowed' => false,
                'error_code' => 'llm_token_limit_exceeded',
                'metrics' => ['llm_requests' => $requests, 'llm_tokens' => $tokens],
            ];
        }

        return [
            'allowed' => true,
            'error_code' => null,
            'metrics' => ['llm_requests' => $requests, 'llm_tokens' => $tokens],
        ];
    }

    public function maxConcurrentSessionsPerOrg(int $organizationId, int $fallback): int
    {
        $snapshot = $this->currentUsage($organizationId);
        $limit = $snapshot['plan']['billing_limits']['max_live_concurrent_sessions'] ?? null;

        return $limit === null ? $fallback : max(0, (int) $limit);
    }

    /**
     * @return array<string,int|null|bool>
     */
    private function metricSnapshot(int $billed, ?int $limit, int $reserved = 0): array
    {
        $used = $billed + $reserved;

        return [
            'used' => $used,
            'billed' => $billed,
            'reserved' => $reserved,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'exceeded' => $limit !== null && $used >= $limit,
        ];
    }

    private function normalizeInt(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return max(0, (int) $value);
    }
}
