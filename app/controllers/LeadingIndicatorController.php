<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Services\LeadingIndicatorService;

final class LeadingIndicatorController
{
    public function __construct(private readonly LeadingIndicatorService $service) {}

    public function submit(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker']);

        $data = $this->service->submit(
            Auth::orgId($claims),
            Auth::userId($claims),
            $body,
        );

        Response::created(['data' => $data]);
    }

    public function summary(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor']);

        $days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
        $data = $this->service->summary(Auth::orgId($claims), $days);

        Response::json(['data' => $data]);
    }

    public function mine(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker']);

        $days = isset($_GET['days']) ? (int) $_GET['days'] : 14;
        $data = $this->service->mine(Auth::orgId($claims), Auth::userId($claims), $days);

        Response::json(['data' => $data]);
    }
}
