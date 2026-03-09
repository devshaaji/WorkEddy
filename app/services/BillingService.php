<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use DateTimeImmutable;
use WorkEddy\Repositories\BillingRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\Payments\PaymentGatewayService;

final class BillingService
{
    public function __construct(
        private readonly WorkspaceRepository $workspaces,
        private readonly UsageMeterService $usageMeter,
        private readonly BillingRepository $billingRepo,
        private readonly PaymentGatewayService $paymentGateway,
        private readonly BillingPeriodService $periods,
    ) {}

    public function plans(): array
    {
        return $this->workspaces->allPlans();
    }

    public function currentUsageSummary(int $organizationId): array
    {
        return $this->usageMeter->currentUsage($organizationId);
    }

    public function invoices(int $organizationId, int $limit = 50): array
    {
        // Ensure there is always an invoice row for the active subscription period.
        $this->ensureCurrentPeriodInvoice($organizationId);

        return $this->billingRepo->listInvoicesByOrganization($organizationId, $limit);
    }

    public function ensureCurrentPeriodInvoice(int $organizationId, ?DateTimeImmutable $now = null): array
    {
        $plan = $this->workspaces->activePlan($organizationId);
        $period = $this->periods->currentPeriod(
            (string) $plan['start_date'],
            (string) ($plan['billing_cycle'] ?? 'monthly'),
            $now,
        );

        $periodStart = $period['period_start']->format('Y-m-d H:i:s');
        $periodEnd = $period['period_end']->format('Y-m-d H:i:s');
        $existing = $this->billingRepo->findInvoiceByPeriod(
            $organizationId,
            (int) $plan['id'],
            $periodStart,
            $periodEnd,
        );

        if ($existing !== null) {
            return $existing;
        }

        $amount = (float) $plan['price'];
        $status = $amount <= 0.0 ? 'paid' : 'pending';
        $paidAt = $amount <= 0.0 ? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s') : null;

        $invoiceId = $this->billingRepo->createInvoice(
            $organizationId,
            (int) ($plan['subscription_id'] ?? 0),
            (int) $plan['id'],
            (string) ($plan['billing_cycle'] ?? 'monthly'),
            $periodStart,
            $periodEnd,
            $amount,
            'USD',
            $status,
            null,
            null,
            [
                'source' => 'subscription_period',
                'plan_name' => (string) $plan['name'],
            ],
            $paidAt,
        );

        return $this->billingRepo->findInvoiceByIdForOrganization($organizationId, $invoiceId);
    }

    public function chargeInvoice(int $organizationId, int $invoiceId, ?string $paymentToken = null): array
    {
        $invoice = $this->billingRepo->findInvoiceByIdForOrganization($organizationId, $invoiceId);
        $amount = (float) ($invoice['amount'] ?? 0.0);
        $currency = (string) ($invoice['currency'] ?? 'USD');

        if ($amount <= 0.0) {
            $this->billingRepo->updateInvoiceStatus(
                $invoiceId,
                'paid',
                gateway: 'none',
                metadata: ['code' => 'zero_amount_invoice'],
                paidAt: (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            );

            $updated = $this->billingRepo->findInvoiceByIdForOrganization($organizationId, $invoiceId);

            return [
                'invoice' => $updated,
                'charge' => [
                    'gateway' => 'none',
                    'status' => 'paid',
                    'message' => 'Zero amount invoice marked paid',
                ],
            ];
        }

        $charge = $this->paymentGateway->chargeInvoice($invoice, $paymentToken);
        $this->billingRepo->recordPaymentTransaction(
            $invoiceId,
            $organizationId,
            (string) $charge['gateway'],
            (string) $charge['status'],
            $amount,
            $currency,
            isset($charge['provider_reference']) ? (string) $charge['provider_reference'] : null,
            isset($charge['raw']) && is_array($charge['raw']) ? $charge['raw'] : [],
        );

        $status = (string) ($charge['status'] ?? 'pending');
        $paidAt = $status === 'paid' ? (new DateTimeImmutable('now'))->format('Y-m-d H:i:s') : null;

        $this->billingRepo->updateInvoiceStatus(
            $invoiceId,
            $status,
            gateway: (string) ($charge['gateway'] ?? 'none'),
            providerReference: isset($charge['provider_reference']) ? (string) $charge['provider_reference'] : null,
            metadata: [
                'message' => (string) ($charge['message'] ?? ''),
                'raw' => isset($charge['raw']) && is_array($charge['raw']) ? $charge['raw'] : [],
            ],
            paidAt: $paidAt,
        );

        $updated = $this->billingRepo->findInvoiceByIdForOrganization($organizationId, $invoiceId);

        return [
            'invoice' => $updated,
            'charge' => $charge,
        ];
    }
}