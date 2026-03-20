<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Services\DashboardService;

final class DashboardController
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function show(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);
        Response::json([
            'data' => $this->dashboard->summary(
                Auth::orgId($claims),
                Auth::userId($claims),
                Auth::role($claims),
            ),
        ]);
    }
}