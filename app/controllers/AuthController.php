<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth) {}

    public function signup(array $body): never
    {
        Validator::requireFields($body, ['name', 'email', 'password', 'organization_name']);
        Validator::email($body['email']);
        Validator::password($body['password']);

        $result = $this->auth->signup(
            $body['name'],
            $body['email'],
            $body['password'],
            $body['organization_name']
        );

        Response::created($result);
    }

    public function login(array $body): never
    {
        Validator::requireFields($body, ['email', 'password']);

        $result = $this->auth->login($body['email'], $body['password']);

        Response::json($result);
    }
}