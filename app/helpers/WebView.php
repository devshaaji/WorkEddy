<?php

declare(strict_types=1);

namespace WorkEddy\Helpers;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class WebView
{
    public static function publicView(string $viewFile): Closure
    {
        return static function (array $vars = []) use ($viewFile): void {
            self::render($viewFile, $vars);
        };
    }

    /**
     * @param string[]|null $allowedRoles
     */
    public static function authView(string $viewFile, ?array $allowedRoles = null): Closure
    {
        return static function (array $vars = []) use ($viewFile, $allowedRoles): void {
            $claims = self::readJwtClaims();

            if ($claims === null) {
                header('Location: /login');
                exit;
            }

            if (
                $allowedRoles !== null
                && (($claims['role'] ?? '') !== 'super_admin')
                && !in_array($claims['role'] ?? '', $allowedRoles, true)
            ) {
                http_response_code(403);
                require self::basePath() . '/views/errors/403.php';
                exit;
            }

            self::render($viewFile, $vars);
        };
    }

    public static function guestView(string $viewFile): Closure
    {
        return static function (array $vars = []) use ($viewFile): void {
            if (self::readJwtClaims() !== null) {
                header('Location: /dashboard');
                exit;
            }

            self::render($viewFile, $vars);
        };
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readJwtClaims(): ?array
    {
        $token = $_COOKIE['we_token'] ?? '';
        if ($token === '') {
            return null;
        }

        $secret = (string) (getenv('JWT_SECRET') ?: 'dev-secret-change-me');

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $vars
     */
    private static function render(string $viewFile, array $vars): never
    {
        $viewPath = self::basePath() . '/' . $viewFile;

        if (!is_file($viewPath)) {
            http_response_code(404);
            require self::basePath() . '/views/errors/404.php';
            exit;
        }

        extract($vars, EXTR_SKIP);
        require $viewPath;
        exit;
    }

    private static function basePath(): string
    {
        return dirname(__DIR__, 2);
    }
}
