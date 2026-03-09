<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\OrgService;

final class OrgController
{
    public function __construct(private readonly OrgService $orgService) {}

    /* ── Settings ─────────────────────────────────────────────────────── */

    public function getSettings(array $claims): never
    {
        // All authenticated users can read org settings (used by layout for theme binding)
        Response::json(['data' => $this->orgService->getSettings(Auth::orgId($claims))]);
    }

    public function updateSettings(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        $this->orgService->updateSettings(Auth::orgId($claims), $body);
        Response::json(['message' => 'Settings updated']);
    }

    /* ── Members ──────────────────────────────────────────────────────── */

    public function listMembers(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor']);
        Response::json(['data' => $this->orgService->listMembers(Auth::orgId($claims))]);
    }

    public function inviteMember(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        Validator::requireFields($body, ['name', 'email', 'password', 'role']);
        Validator::email($body['email']);
        $result = $this->orgService->inviteMember(
            Auth::orgId($claims),
            $body['name'],
            $body['email'],
            $body['role'],
            $body['password']
        );
        Response::created(['data' => $result]);
    }

    public function updateMemberRole(array $claims, int $userId, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        Validator::requireFields($body, ['role']);
        $this->orgService->updateMemberRole(Auth::orgId($claims), $userId, $body['role']);
        Response::json(['message' => 'Role updated']);
    }

    public function removeMember(array $claims, int $userId): never
    {
        Auth::requireRoles($claims, ['admin']);
        $this->orgService->removeMember(Auth::orgId($claims), $userId);
        Response::json(['message' => 'Member removed']);
    }

    /* ── Billing / Subscription ───────────────────────────────────────── */

    public function getSubscription(array $claims): never
    {
        Auth::requireRoles($claims, ['admin', 'supervisor']);
        Response::json(['data' => $this->orgService->getSubscription(Auth::orgId($claims))]);
    }

    public function changePlan(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        Validator::requireFields($body, ['plan_id']);
        $this->orgService->changePlan(Auth::orgId($claims), (int) $body['plan_id']);
        Response::json(['message' => 'Plan changed successfully']);
    }
}
