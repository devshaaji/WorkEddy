<?php

declare(strict_types=1);

/**
 * Web (server-rendered view) routes — FastRoute format.
 *
 * Each handler is a closure that receives ($vars) and requires
 * the corresponding view file.  Route params like {id} are extracted
 * into local variables available inside the view.
 *
 * Auth-protected routes verify the JWT from the `we_token` cookie
 * server-side *before* serving any HTML.
 *
 * @return Closure(FastRoute\RouteCollector): void
 */

$basePath = dirname(__DIR__);

/* ──────────────────────────────────────────────────────────────────────────
 * JWT helpers (lightweight — no controller/middleware overhead)
 * ────────────────────────────────────────────────────────────────────────── */

/**
 * Read and validate the JWT stored in the `we_token` cookie.
 * Returns the decoded claims array, or null if absent / invalid / expired.
 */
$readJwt = static function () use ($basePath): ?array {
    $token = $_COOKIE['we_token'] ?? '';
    if ($token === '') return null;

    // Lazy-load Firebase JWT (already in vendor via composer)
    $secret = (string) (getenv('JWT_SECRET') ?: 'dev-secret-change-me');
    try {
        $decoded = \Firebase\JWT\JWT::decode(
            $token,
            new \Firebase\JWT\Key($secret, 'HS256')
        );
        return (array) $decoded;
    } catch (\Throwable) {
        // Expired, tampered, or malformed — treat as unauthenticated
        return null;
    }
};

/* ──────────────────────────────────────────────────────────────────────────
 * View rendering helpers
 * ────────────────────────────────────────────────────────────────────────── */

/**
 * Build a closure that renders a PUBLIC view (no auth check).
 */
$view = static function (string $viewFile) use ($basePath): Closure {
    return static function (array $vars = []) use ($basePath, $viewFile): void {
        extract($vars, EXTR_SKIP);
        $viewPath = $basePath . '/' . $viewFile;
        if (!is_file($viewPath)) {
            http_response_code(404);
            require $basePath . '/views/errors/404.php';
            exit;
        }
        require $viewPath;
        exit;
    };
};

/**
 * Build a closure that first validates the JWT cookie, then renders the view.
 * If the token is missing/invalid the user is redirected to /login.
 *
 * @param string        $viewFile       Path relative to project root.
 * @param string[]|null $allowedRoles   If set, only these roles may access (e.g. ['admin']).
 */
$auth = static function (string $viewFile, ?array $allowedRoles = null) use ($basePath, $readJwt): Closure {
    return static function (array $vars = []) use ($basePath, $viewFile, $allowedRoles, $readJwt): void {
        $claims = $readJwt();

        // Not authenticated → redirect to login
        if ($claims === null) {
            header('Location: /login');
            exit;
        }

        // Role gate (e.g. admin-only pages)
        if ($allowedRoles !== null && (($claims['role'] ?? '') !== 'super_admin') && !in_array($claims['role'] ?? '', $allowedRoles, true)) {
            http_response_code(403);
            require $basePath . '/views/errors/403.php';
            exit;
        }

        extract($vars, EXTR_SKIP);
        $viewPath = $basePath . '/' . $viewFile;
        if (!is_file($viewPath)) {
            http_response_code(404);
            require $basePath . '/views/errors/404.php';
            exit;
        }
        require $viewPath;
        exit;
    };
};

/**
 * Build a closure for guest-only pages (login, register, forgot-password).
 * If the user already has a valid JWT, redirect to /dashboard.
 */
$guest = static function (string $viewFile) use ($basePath, $readJwt): Closure {
    return static function (array $vars = []) use ($basePath, $viewFile, $readJwt): void {
        $claims = $readJwt();
        if ($claims !== null) {
            header('Location: /dashboard');
            exit;
        }

        extract($vars, EXTR_SKIP);
        $viewPath = $basePath . '/' . $viewFile;
        if (!is_file($viewPath)) {
            http_response_code(404);
            require $basePath . '/views/errors/404.php';
            exit;
        }
        require $viewPath;
        exit;
    };
};

/* ──────────────────────────────────────────────────────────────────────────
 * Route definitions
 * ────────────────────────────────────────────────────────────────────────── */

return static function (FastRoute\RouteCollector $r) use ($view, $auth, $guest): void {

    // ── Public / marketing (no auth) ──────────────────────────────────
    $r->addRoute('GET', '/', $view('views/site/index.php'));

    // ── Guest-only (redirect to dashboard if already logged in) ───────
    $r->addRoute('GET', '/login',           $guest('views/auth/login.php'));
    $r->addRoute('GET', '/register',        $guest('views/auth/register.php'));
    $r->addRoute('GET', '/forgot-password', $guest('views/auth/forgot-password.php'));

    // ── App pages (any authenticated user) ────────────────────────────
    $r->addRoute('GET', '/dashboard',         $auth('views/dashboard/index.php'));
    $r->addRoute('GET', '/profile',           $auth('views/user/profile.php'));
    $r->addRoute('GET', '/observer-rating',   $auth('views/observer/rate.php'));
    $r->addRoute('GET', '/tasks',             $auth('views/tasks/index.php'));
    $r->addRoute('GET', '/tasks/{id:\d+}',    $auth('views/tasks/view.php'));
    $r->addRoute('GET', '/scans/new-manual',  $auth('views/scans/new-manual.php'));
    $r->addRoute('GET', '/scans/new-video',   $auth('views/scans/new-video.php'));
    $r->addRoute('GET', '/scans/compare',     $auth('views/scans/advanced-compare.php'));
    $r->addRoute('GET', '/scans/{id:\d+}',    $auth('views/scans/results.php'));
    $r->addRoute('GET', '/scans/{id:\d+}/compare', $auth('views/scans/compare.php'));
    $r->addRoute('GET', '/scans/{id:\d+}/observe',  $auth('views/observer/rate.php', ['admin', 'observer']));

    // ── Admin (system-wide, role: super_admin only) ───────────────────
    $r->addRoute('GET', '/admin/dashboard',     $auth('views/admin/dashboard.php', ['super_admin']));
    $r->addRoute('GET', '/admin/organizations', $auth('views/admin/organizations.php', ['super_admin']));
    $r->addRoute('GET', '/admin/users',         $auth('views/admin/users.php', ['super_admin']));
    $r->addRoute('GET', '/admin/plans',         $auth('views/admin/plans.php', ['super_admin']));
    $r->addRoute('GET', '/admin/settings',      $auth('views/admin/settings.php', ['super_admin']));

    // ── Org management (admin + supervisor) ───────────────────────────
    $r->addRoute('GET', '/org/users',    $auth('views/org/users.php', ['admin', 'supervisor']));
    $r->addRoute('GET', '/org/settings', $auth('views/org/settings.php', ['admin', 'supervisor']));
    $r->addRoute('GET', '/org/billing',  $auth('views/org/billing.php', ['admin', 'supervisor']));
};