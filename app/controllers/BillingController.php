<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Services\BillingService;

final class BillingController
{
    public function __construct(private readonly BillingService $billing) {}

    public function usage(array $claims): never
    {
        Auth::requireRoles($claims, ['admin']);
        Response::json(['data' => $this->billing->currentUsageSummary(Auth::orgId($claims))]);
    }

    public function plans(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor']);
        Response::json(['data' => $this->billing->plans()]);
    }

    public function invoices(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor']);
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        Response::json(['data' => $this->billing->invoices(Auth::orgId($claims), $limit)]);
    }

    public function chargeInvoice(array $claims, int $invoiceId, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        $paymentToken = isset($body['payment_token']) ? (string) $body['payment_token'] : null;

        Response::json([
            'data' => $this->billing->chargeInvoice(Auth::orgId($claims), $invoiceId, $paymentToken),
        ]);
    }
}
