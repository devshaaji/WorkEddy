<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class BillingRepository
{
    public function __construct(private readonly Connection $db) {}

    public function listInvoicesByOrganization(int $organizationId, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        return $this->db->fetchAllAssociative(
            'SELECT i.id, i.organization_id, i.subscription_id, i.plan_id, i.billing_cycle,
                    i.period_start, i.period_end, i.amount, i.currency, i.status,
                    i.gateway, i.provider_reference, i.metadata, i.created_at, i.paid_at,
                    p.name AS plan_name
             FROM invoices i
             LEFT JOIN plans p ON p.id = i.plan_id
             WHERE i.organization_id = :org_id
             ORDER BY i.id DESC
             LIMIT ' . $limit,
            ['org_id' => $organizationId]
        );
    }

    public function findInvoiceByIdForOrganization(int $organizationId, int $invoiceId): array
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM invoices WHERE id = :id AND organization_id = :org_id LIMIT 1',
            ['id' => $invoiceId, 'org_id' => $organizationId]
        );

        if (!$row) {
            throw new RuntimeException('Invoice not found');
        }

        return $row;
    }

    public function findInvoiceByPeriod(int $organizationId, int $planId, string $periodStart, string $periodEnd): ?array
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM invoices
             WHERE organization_id = :org_id
               AND plan_id = :plan_id
               AND period_start = :period_start
               AND period_end = :period_end
             LIMIT 1',
            [
                'org_id' => $organizationId,
                'plan_id' => $planId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]
        );

        return $row ?: null;
    }

    public function createInvoice(
        int $organizationId,
        int $subscriptionId,
        int $planId,
        string $billingCycle,
        string $periodStart,
        string $periodEnd,
        float $amount,
        string $currency,
        string $status,
        ?string $gateway = null,
        ?string $providerReference = null,
        ?array $metadata = null,
        ?string $paidAt = null,
    ): int {
        $this->db->executeStatement(
            'INSERT INTO invoices (
                organization_id, subscription_id, plan_id, billing_cycle,
                period_start, period_end, amount, currency, status,
                gateway, provider_reference, metadata, created_at, paid_at
             ) VALUES (
                :org_id, :subscription_id, :plan_id, :billing_cycle,
                :period_start, :period_end, :amount, :currency, :status,
                :gateway, :provider_reference, :metadata, NOW(), :paid_at
             )',
            [
                'org_id' => $organizationId,
                'subscription_id' => $subscriptionId,
                'plan_id' => $planId,
                'billing_cycle' => $billingCycle,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'status' => $status,
                'gateway' => $gateway,
                'provider_reference' => $providerReference,
                'metadata' => $metadata !== null ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
                'paid_at' => $paidAt,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateInvoiceStatus(
        int $invoiceId,
        string $status,
        ?string $gateway = null,
        ?string $providerReference = null,
        ?array $metadata = null,
        ?string $paidAt = null,
    ): void {
        $sets = ['status = :status'];
        $params = [
            'id' => $invoiceId,
            'status' => $status,
        ];

        if ($gateway !== null) {
            $sets[] = 'gateway = :gateway';
            $params['gateway'] = $gateway;
        }

        if ($providerReference !== null) {
            $sets[] = 'provider_reference = :provider_reference';
            $params['provider_reference'] = $providerReference;
        }

        if ($metadata !== null) {
            $sets[] = 'metadata = :metadata';
            $params['metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR);
        }

        if ($paidAt !== null) {
            $sets[] = 'paid_at = :paid_at';
            $params['paid_at'] = $paidAt;
        }

        $this->db->executeStatement(
            'UPDATE invoices SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params,
        );
    }

    public function recordPaymentTransaction(
        int $invoiceId,
        int $organizationId,
        string $gateway,
        string $status,
        float $amount,
        string $currency,
        ?string $providerReference,
        array $responsePayload,
    ): int {
        $this->db->executeStatement(
            'INSERT INTO payment_transactions (
                invoice_id, organization_id, gateway, status,
                amount, currency, provider_reference, response_payload, created_at
             ) VALUES (
                :invoice_id, :org_id, :gateway, :status,
                :amount, :currency, :provider_reference, :response_payload, NOW()
             )',
            [
                'invoice_id' => $invoiceId,
                'org_id' => $organizationId,
                'gateway' => $gateway,
                'status' => $status,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'provider_reference' => $providerReference,
                'response_payload' => json_encode($responsePayload, JSON_THROW_ON_ERROR),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function paymentSettings(): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT key_name, value_data
             FROM system_settings
             WHERE key_name IN ("payment_gateway", "payment_public_key", "payment_secret_key")'
        );

        $settings = [
            'payment_gateway' => '',
            'payment_public_key' => '',
            'payment_secret_key' => '',
        ];

        foreach ($rows as $row) {
            $key = (string) ($row['key_name'] ?? '');
            if (!array_key_exists($key, $settings)) {
                continue;
            }

            $value = json_decode((string) ($row['value_data'] ?? ''), true);
            if (is_scalar($value)) {
                $settings[$key] = (string) $value;
            }
        }

        return $settings;
    }
}
