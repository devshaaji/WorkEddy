<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use WorkEddy\Repositories\UserRepository;
use WorkEddy\Repositories\WorkspaceRepository;

final class AuthService
{
    private Google2FA $g2fa;

    public function __construct(
        private readonly UserRepository      $users,
        private readonly WorkspaceRepository $workspaces,
        private readonly JwtService          $jwt,
        private readonly EmailService        $email,
    ) {
        $this->g2fa = new Google2FA();
    }

    public function signup(string $name, string $email, string $password, string $organizationName): array
    {
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('Email already registered');
        }

        $orgId  = $this->workspaces->create($organizationName);
        $planId = $this->workspaces->starterPlanId();
        $this->workspaces->createSubscription($orgId, $planId);

        $userId = $this->users->create(
            $orgId,
            $name,
            $email,
            password_hash($password, PASSWORD_BCRYPT),
            'admin'
        );

        // Send welcome email (async-safe — non-blocking)
        $this->email->sendWelcome($email, $name);

        return [
            'token' => $this->jwt->issueToken($userId, $orgId, 'admin', $name, 'Free'),
            'user'  => [
                'id'              => $userId,
                'organization_id' => $orgId,
                'name'            => $name,
                'email'           => strtolower($email),
                'role'            => 'admin',
            ],
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('Invalid credentials');
        }

        // Check if 2FA is enabled — if so, return a partial response requiring TOTP
        if (!empty($user['two_factor_enabled'])) {
            // Generate a temporary token that cannot be used for normal API access
            return [
                'requires_2fa' => true,
                'temp_token'   => $this->jwt->issueToken(
                    (int)    $user['id'],
                    (int)    $user['organization_id'],
                    '2fa_pending',
                    (string) $user['name'],
                    '',
                ),
                'user' => [
                    'id'   => (int) $user['id'],
                    'name' => (string) $user['name'],
                ],
            ];
        }

        return $this->issueFullLogin($user);
    }

    /** Verify TOTP code and issue a real JWT after 2FA challenge. */
    public function verify2fa(array $claims, string $code): array
    {
        if (($claims['role'] ?? '') !== '2fa_pending') {
            throw new RuntimeException('Invalid 2FA verification context');
        }

        $userId = (int) $claims['sub'];
        $secret = $this->users->get2faSecret($userId);

        if (!$secret || !$this->g2fa->verifyKey($secret, $code)) {
            throw new RuntimeException('Invalid 2FA code');
        }

        $user = $this->findUserById($userId);
        if (!$user) {
            throw new RuntimeException('User not found');
        }

        return $this->issueFullLogin($user);
    }

    /** Send an email OTP for login verification. */
    public function sendLoginOtp(string $email): array
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
            // Don't reveal whether the email exists
            return ['message' => 'If the email exists, an OTP has been sent.'];
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->users->setEmailOtp((int) $user['id'], $otp, 10);
        $this->email->sendOtp($email, (string) $user['name'], $otp);

        return ['message' => 'If the email exists, an OTP has been sent.'];
    }

    /** Verify email OTP during login. */
    public function verifyEmailOtp(string $email, string $otp): array
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
            throw new RuntimeException('Invalid OTP');
        }

        if (!$this->users->verifyEmailOtp((int) $user['id'], $otp)) {
            throw new RuntimeException('Invalid or expired OTP');
        }

        $this->users->markEmailVerified((int) $user['id']);

        // If 2FA is enabled, they still need the TOTP step
        if (!empty($user['two_factor_enabled'])) {
            return [
                'requires_2fa' => true,
                'temp_token'   => $this->jwt->issueToken(
                    (int)    $user['id'],
                    (int)    $user['organization_id'],
                    '2fa_pending',
                    (string) $user['name'],
                    '',
                ),
            ];
        }

        return $this->issueFullLogin($user);
    }

    /* ── 2FA setup methods ─────────────────────────────────────────────── */

    /** Generate a 2FA secret and return the provisioning URI for QR codes. */
    public function setup2fa(array $claims): array
    {
        $userId = (int) $claims['sub'];
        $user = $this->findUserById($userId);
        if (!$user) {
            throw new RuntimeException('User not found');
        }

        $secret = $this->g2fa->generateSecretKey();
        $qrUri  = $this->g2fa->getQRCodeUrl(
            'WorkEddy',
            (string) $user['email'],
            $secret,
        );

        return [
            'secret'  => $secret,
            'qr_uri'  => $qrUri,
        ];
    }

    /** Confirm 2FA setup by verifying one TOTP code. */
    public function confirm2fa(array $claims, string $secret, string $code): array
    {
        if (!$this->g2fa->verifyKey($secret, $code)) {
            throw new RuntimeException('Invalid verification code. Please try again.');
        }

        $userId = (int) $claims['sub'];
        $this->users->enable2fa($userId, $secret);

        $user = $this->findUserById($userId);
        if ($user) {
            $this->email->send2faEnabled((string) $user['email'], (string) $user['name']);
        }

        return ['message' => 'Two-factor authentication enabled'];
    }

    /** Disable 2FA for the current user. */
    public function disable2fa(array $claims): array
    {
        $userId = (int) $claims['sub'];
        $this->users->disable2fa($userId);

        $user = $this->findUserById($userId);
        if ($user) {
            $this->email->send2faDisabled((string) $user['email'], (string) $user['name']);
        }

        return ['message' => 'Two-factor authentication disabled'];
    }

    /** Get 2FA status for the current user. */
    public function get2faStatus(array $claims): array
    {
        $userId = (int) $claims['sub'];
        $secret = $this->users->get2faSecret($userId);
        return ['enabled' => $secret !== null];
    }

    /**
     * Re-issue a fresh JWT from existing valid claims (silent refresh).
     */
    public function refresh(array $claims): string
    {
        return $this->jwt->issueToken(
            (int)    $claims['sub'],
            (int)    $claims['org'],
            (string) $claims['role'],
            (string) ($claims['name'] ?? ''),
            (string) ($claims['plan'] ?? ''),
        );
    }

    /* ── Private helpers ───────────────────────────────────────────────── */

    private function issueFullLogin(array $user): array
    {
        $planName = 'Free';
        try {
            $plan = $this->workspaces->activePlan((int) $user['organization_id']);
            $rawPlanName = strtolower((string) ($plan['name'] ?? ''));
            $planName = $rawPlanName === 'starter' ? 'Free' : ucfirst($rawPlanName);
        } catch (\Throwable) { /* no active subscription — default to Free */ }

        return [
            'token' => $this->jwt->issueToken(
                (int)    $user['id'],
                (int)    $user['organization_id'],
                (string) $user['role'],
                (string) $user['name'],
                $planName,
            ),
            'user'  => [
                'id'              => (int)    $user['id'],
                'organization_id' => (int)    $user['organization_id'],
                'name'            => (string) $user['name'],
                'email'           => (string) $user['email'],
                'role'            => (string) $user['role'],
            ],
        ];
    }

    private function findUserById(int $id): ?array
    {
        return $this->users->findById($id);
    }
}