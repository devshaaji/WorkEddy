<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use WorkEddy\Repositories\NotificationRepository;

/**
 * In-app notification service — persists notifications to the database
 * and exposes read-state management for the API.
 */
final class NotificationService
{
    public function __construct(private readonly NotificationRepository $repo) {}

    /* ── Write helpers (called by other services / controllers) ─────── */

    /**
     * Notify all org members that a scan completed.
     */
    public function notifyScanComplete(int $orgId, int $scanId, string $riskCategory, ?int $userId = null): void
    {
        $emoji = match ($riskCategory) {
            'high'     => '🔴',
            'moderate' => '🟡',
            default    => '🟢',
        };
        $this->repo->create(
            $orgId,
            null,                                          // org-wide
            'scan_complete',
            "{$emoji} Scan #{$scanId} completed",
            "Risk level: " . ucfirst($riskCategory),
            "/scans/{$scanId}",
        );
    }

    /**
     * Notify supervisors / admins about a high-risk scan.
     */
    public function notifyHighRisk(int $orgId, int $scanId): void
    {
        $this->repo->create(
            $orgId,
            null,
            'high_risk',
            '⚠️ High-risk scan detected',
            "Scan #{$scanId} requires immediate attention.",
            "/scans/{$scanId}",
        );
    }

    /**
     * Notify a user when an observer has rated their scan.
     */
    public function notifyObserverRated(int $orgId, int $scanId, ?int $userId = null): void
    {
        $this->repo->create(
            $orgId,
            $userId,
            'observer_rated',
            '📝 New observer rating',
            "An observer has rated scan #{$scanId}.",
            "/scans/{$scanId}",
        );
    }

    /**
     * Notify org admins when a new member joins.
     */
    public function notifyMemberJoined(int $orgId, string $memberName): void
    {
        $this->repo->create(
            $orgId,
            null,
            'member_joined',
            "👤 {$memberName} joined your team",
            null,
            '/org/users',
        );
    }

    /**
     * Notify org admins when the plan changes.
     */
    public function notifyPlanChanged(int $orgId, string $planName): void
    {
        $this->repo->create(
            $orgId,
            null,
            'plan_changed',
            "📦 Plan changed to {$planName}",
            null,
            '/org/billing',
        );
    }

    /**
     * Generic custom notification.
     */
    public function send(int $orgId, ?int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        $this->repo->create($orgId, $userId, $type, $title, $body, $link);
    }

    /* ── Read helpers (called by NotificationController) ───────────── */

    public function listForUser(int $orgId, int $userId): array
    {
        return $this->repo->listForUser($orgId, $userId);
    }

    public function unreadCount(int $orgId, int $userId): int
    {
        return $this->repo->unreadCount($orgId, $userId);
    }

    public function markRead(int $orgId, int $notificationId): void
    {
        $this->repo->markRead($orgId, $notificationId);
    }

    public function markAllRead(int $orgId, int $userId): void
    {
        $this->repo->markAllRead($orgId, $userId);
    }
}