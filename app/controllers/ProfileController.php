<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\UserService;

final class ProfileController
{
    public function __construct(private readonly UserService $userService) {}

    /** GET /user/profile */
    public function getProfile(array $claims): never
    {
        $userId = Auth::userId($claims);
        Response::json(['data' => $this->userService->getProfile($userId)]);
    }

    /** PUT /user/profile */
    public function updateProfile(array $claims, array $body): never
    {
        Validator::requireFields($body, ['name', 'email']);
        Validator::email($body['email']);

        $userId = Auth::userId($claims);
        $user   = $this->userService->updateProfile($userId, $body['name'], $body['email']);
        Response::json(['data' => $user, 'message' => 'Profile updated']);
    }
}
