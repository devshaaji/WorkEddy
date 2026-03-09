<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\UserService;

final class WorkspaceController
{
    public function __construct(private readonly UserService $users) {}

    public function listUsers(array $claims): never
    {
        Auth::requireRoles($claims, ['admin']);
        Response::json(['data' => $this->users->listByOrganization(Auth::orgId($claims))]);
    }

    public function createUser(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['admin']);
        Validator::requireFields($body, ['name', 'email', 'password', 'role']);
        Validator::email($body['email']);
        Validator::password($body['password']);

        $user = $this->users->create(
            Auth::orgId($claims),
            $body['name'],
            $body['email'],
            $body['password'],
            $body['role']
        );

        Response::created(['data' => $user]);
    }
}