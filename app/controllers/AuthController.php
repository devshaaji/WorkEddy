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

    /* ── OTP verification ──────────────────────────────────────────────── */

    /** POST /auth/send-otp — send an OTP to the user's email. */
    public function sendOtp(array $body): never
    {
        Validator::requireFields($body, ['email']);
        $result = $this->auth->sendLoginOtp($body['email']);
        Response::json($result);
    }

    /** POST /auth/verify-otp — verify an email OTP code. */
    public function verifyOtp(array $body): never
    {
        Validator::requireFields($body, ['email', 'otp']);
        $result = $this->auth->verifyEmailOtp($body['email'], $body['otp']);
        Response::json($result);
    }

    /* ── 2FA management ────────────────────────────────────────────────── */

    /** POST /auth/2fa/verify — verify TOTP code after login. */
    public function verify2fa(array $claims, array $body): never
    {
        Validator::requireFields($body, ['code']);
        $result = $this->auth->verify2fa($claims, $body['code']);
        Response::json($result);
    }

    /** GET /auth/2fa/status — check if 2FA is enabled for user. */
    public function get2faStatus(array $claims): never
    {
        $result = $this->auth->get2faStatus($claims);
        Response::json($result);
    }

    /** POST /auth/2fa/setup — generate a 2FA secret and QR URI. */
    public function setup2fa(array $claims): never
    {
        $result = $this->auth->setup2fa($claims);
        Response::json($result);
    }

    /** POST /auth/2fa/confirm — confirm setup by verifying one TOTP code. */
    public function confirm2fa(array $claims, array $body): never
    {
        Validator::requireFields($body, ['secret', 'code']);
        $result = $this->auth->confirm2fa($claims, $body['secret'], $body['code']);
        Response::json($result);
    }

    /** POST /auth/2fa/disable — disable 2FA for the current user. */
    public function disable2fa(array $claims): never
    {
        $result = $this->auth->disable2fa($claims);
        Response::json($result);
    }

    /**
     * POST /auth/refresh — re-issues a fresh JWT from the current valid token.
     */
    public function refresh(array $claims): never
    {
        $token = $this->auth->refresh($claims);
        Response::json(['token' => $token]);
    }
}