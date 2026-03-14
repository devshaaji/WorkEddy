<?php

declare(strict_types=1);

namespace WorkEddy\Services;

final class PlanBillingDefaults
{
    /** @var list<string> */
    public const KEYS = [
        'video_scan_limit',
        'live_session_limit',
        'live_session_minutes_limit',
        'llm_request_limit',
        'llm_token_limit',
        'max_video_retention_days',
        'max_org_members',
        'max_live_concurrent_sessions',
    ];

    /**
     * @return array<string,int|null>
     */
    public static function defaults(?string $planName = null, ?int $scanLimit = null): array
    {
        $name = strtolower(trim((string) $planName));

        return match ($name) {
            'starter' => [
                'video_scan_limit' => $scanLimit ?? 10,
                'live_session_limit' => 10,
                'live_session_minutes_limit' => 120,
                'llm_request_limit' => 25,
                'llm_token_limit' => 100000,
                'max_video_retention_days' => 30,
                'max_org_members' => 5,
                'max_live_concurrent_sessions' => 1,
            ],
            'professional' => [
                'video_scan_limit' => $scanLimit ?? 500,
                'live_session_limit' => 250,
                'live_session_minutes_limit' => 3000,
                'llm_request_limit' => 500,
                'llm_token_limit' => 2000000,
                'max_video_retention_days' => 180,
                'max_org_members' => 50,
                'max_live_concurrent_sessions' => 4,
            ],
            'enterprise' => [
                'video_scan_limit' => $scanLimit,
                'live_session_limit' => null,
                'live_session_minutes_limit' => null,
                'llm_request_limit' => null,
                'llm_token_limit' => null,
                'max_video_retention_days' => 3650,
                'max_org_members' => null,
                'max_live_concurrent_sessions' => 12,
            ],
            default => [
                'video_scan_limit' => $scanLimit,
                'live_session_limit' => null,
                'live_session_minutes_limit' => null,
                'llm_request_limit' => null,
                'llm_token_limit' => null,
                'max_video_retention_days' => 30,
                'max_org_members' => null,
                'max_live_concurrent_sessions' => null,
            ],
        };
    }

    /**
     * @param array<string,mixed>|string|null $value
     * @return array<string,int|null>
     */
    public static function normalize(array|string|null $value, ?string $planName = null, ?int $scanLimit = null): array
    {
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        $limits = is_array($value) ? $value : [];
        $defaults = self::defaults($planName, $scanLimit);

        $normalized = [];
        foreach (self::KEYS as $key) {
            $normalized[$key] = self::normalizeNullableInt($limits[$key] ?? $defaults[$key] ?? null);
        }

        return $normalized;
    }

    private static function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }
}
