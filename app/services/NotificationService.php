<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use Psr\Log\LoggerInterface;

/**
 * Stub notification service – extend to support email / webhooks.
 */
final class NotificationService
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function notifyScanComplete(int $organizationId, int $scanId, string $riskCategory): void
    {
        $this->logger->info('scan_complete', [
            'organization_id' => $organizationId,
            'scan_id'         => $scanId,
            'risk_category'   => $riskCategory,
        ]);
    }

    public function notifyHighRisk(int $organizationId, int $scanId): void
    {
        $this->logger->warning('high_risk_scan', [
            'organization_id' => $organizationId,
            'scan_id'         => $scanId,
        ]);
    }
}