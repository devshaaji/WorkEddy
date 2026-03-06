<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\AdminService;

final class AdminController
{
    public function __construct(private readonly AdminService $admin) {}

    /* ── Organizations ───────────────────────────────────────────────── */

    public function listOrganizations(array $claims): never
    {
        Auth::requireRoles($claims, ['admin']);
        Response::json(['data' => $this->admin->listOrganizations()]);
    }

    public function showOrganization(array $claims, int $id): never
    {
        Auth::requireRoles($claims, ['admin']);
        Response::json(['data' => $this->admin->showOrganization($id)]);
    }

    public function createOrganization(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        Validator::requireFields($body, ['name']);
        $result = $this->admin->createOrganization(
            $body['name'],
            $body['contact_email'] ?? null
        );
        Response::created(['data' => $result]);
    }

    public function updateOrganization(array $claims, int $id, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        $this->admin->updateOrganization($id, $body);
        Response::json(['message' => 'Organization updated']);
    }

    /* ── Users (global) ──────────────────────────────────────────────── */

    public function listUsers(array $claims): never
    {
        Auth::requireRoles($claims, ['admin']);
        Response::json(['data' => $this->admin->listUsers()]);
    }

    public function updateUser(array $claims, int $id, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        $this->admin->updateUser($id, $body);
        Response::json(['message' => 'User updated']);
    }

    public function deleteUser(array $claims, int $id): never
    {
        Auth::requireRoles($claims, ['admin']);
        $this->admin->deleteUser($id);
        Response::json(['message' => 'User deleted']);
    }

    /* ── Plans ────────────────────────────────────────────────────────── */

    public function listPlans(array $claims): never
    {
        Auth::requireRoles($claims, ['admin']);
        Response::json(['data' => $this->admin->listPlans()]);
    }

    public function createPlan(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        Validator::requireFields($body, ['name', 'price']);
        $result = $this->admin->createPlan(
            $body['name'],
            isset($body['scan_limit']) ? (int) $body['scan_limit'] : null,
            (float) $body['price'],
            $body['billing_cycle'] ?? 'monthly'
        );
        Response::created(['data' => $result]);
    }

    public function updatePlan(array $claims, int $id, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        $this->admin->updatePlan($id, $body);
        Response::json(['message' => 'Plan updated']);
    }

    public function deletePlan(array $claims, int $id): never
    {
        Auth::requireRoles($claims, ['admin']);
        $this->admin->deletePlan($id);
        Response::json(['message' => 'Plan deleted']);
    }

    /* ── System Stats ─────────────────────────────────────────────────── */

    public function stats(array $claims): never
    {
        Auth::requireRoles($claims, ['admin']);
        Response::json(['data' => $this->admin->systemStats()]);
    }
}
