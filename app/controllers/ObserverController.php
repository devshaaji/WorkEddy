<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\ObserverService;

final class ObserverController
{
    public function __construct(private readonly ObserverService $observer) {}

    public function rate(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['observer', 'admin']);
        Validator::requireFields($body, ['scan_id', 'observer_score', 'observer_category']);

        $rating = $this->observer->rate(
            (int)    $body['scan_id'],
            Auth::userId($claims),
            (float)  $body['observer_score'],
            (string) $body['observer_category'],
            $body['notes'] ?? null
        );

        Response::created(['data' => $rating]);
    }

    public function listByScan(array $claims, int $scanId): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor', 'observer']);
        Response::json(['data' => $this->observer->listByScan($scanId)]);
    }
}
