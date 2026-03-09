<?php

declare(strict_types=1);

namespace WorkEddy\Services\Payments;

use WorkEddy\Repositories\BillingRepository;

final class PaymentGatewayService
{
    public function __construct(private readonly BillingRepository $billingRepo) {}

    /**
     * @param array<string, mixed> $invoice
     * @return array{gateway:string,status:string,provider_reference:?string,message:string,raw:array<string, mixed>}
     */
    public function chargeInvoice(array $invoice, ?string $paymentToken = null): array
    {
        $settings = $this->billingRepo->paymentSettings();

        $gateway = strtolower(trim((string) ($settings['payment_gateway'] ?? '')));
        $secret = trim((string) ($settings['payment_secret_key'] ?? ''));

        if ($gateway === '') {
            return [
                'gateway' => 'none',
                'status' => 'pending',
                'provider_reference' => null,
                'message' => 'Payment gateway is not configured',
                'raw' => ['code' => 'gateway_not_configured'],
            ];
        }

        if ($secret === '') {
            return [
                'gateway' => $gateway,
                'status' => 'failed',
                'provider_reference' => null,
                'message' => 'Payment gateway secret key is missing',
                'raw' => ['code' => 'missing_secret_key'],
            ];
        }

        if ($paymentToken === null || trim($paymentToken) === '') {
            return [
                'gateway' => $gateway,
                'status' => 'pending',
                'provider_reference' => null,
                'message' => 'Payment token is required for automatic charge capture',
                'raw' => ['code' => 'payment_token_required'],
            ];
        }

        $invoiceId = (int) ($invoice['id'] ?? 0);
        $reference = sprintf(
            'SIM-%s-%d-%d',
            strtoupper($gateway),
            $invoiceId,
            time(),
        );

        return [
            'gateway' => $gateway,
            'status' => 'paid',
            'provider_reference' => $reference,
            'message' => 'Charge captured',
            'raw' => [
                'code' => 'simulated_capture',
                'invoice_id' => $invoiceId,
                'token_suffix' => substr($paymentToken, -4),
            ],
        ];
    }
}
