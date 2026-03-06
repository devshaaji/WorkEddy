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
}