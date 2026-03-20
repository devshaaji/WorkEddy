<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Services\WorkerCoachingService;

final class WorkerCoachingController
{
    public function __construct(private readonly WorkerCoachingService $service) {}

    public function mine(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'worker']);

        $lang = isset($_GET['lang']) ? (string) $_GET['lang'] : null;
        $data = $this->service->coaching(
            Auth::orgId($claims),
            Auth::userId($claims),
            $lang
        );

        Response::json(['data' => $data]);
    }
}

