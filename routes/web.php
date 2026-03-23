<?php

declare(strict_types=1);

use FastRoute\RouteCollector;
use WorkEddy\Helpers\WebView;

return static function (RouteCollector $r): void {
    $liveConfig = require __DIR__ . '/../app/config/live.php';
    $liveEnabled = (bool) ($liveConfig['enabled'] ?? false);

    $public = static fn (string $viewFile) => WebView::publicView($viewFile);
    $auth = static fn (string $viewFile, ?array $allowedRoles = null) => WebView::authView($viewFile, $allowedRoles);
    $guest = static fn (string $viewFile) => WebView::guestView($viewFile);

    $r->addRoute('GET', '/', $public('views/site/index.php'));
    $r->addRoute('GET', '/privacy-policy', $public('views/site/privacy-policy.php'));
    $r->addRoute('GET', '/terms-of-service', $public('views/site/terms-of-service.php'));
    $r->addRoute('GET', '/founder-story', $public('views/site/founder-story.php'));

    $r->addRoute('GET', '/login', $guest('views/auth/login.php'));
    $r->addRoute('GET', '/register', $guest('views/auth/register.php'));
    $r->addRoute('GET', '/forgot-password', $guest('views/auth/forgot-password.php'));

    $r->addRoute('GET', '/dashboard', $auth('views/dashboard/index.php', ['admin', 'supervisor', 'worker', 'observer']));
    $r->addRoute('GET', '/profile', $auth('views/user/profile.php'));
    $r->addRoute('GET', '/observer-rating', $auth('views/observer/rate.php', ['admin', 'observer']));
    $r->addRoute('GET', '/tasks', $auth('views/tasks/index.php', ['admin', 'supervisor', 'worker', 'observer']));
    $r->addRoute('GET', '/tasks/{id:\d+}', $auth('views/tasks/view.php', ['admin', 'supervisor', 'worker', 'observer']));
    $r->addRoute('GET', '/scans/new-manual', $auth('views/scans/new-manual.php', ['admin', 'supervisor', 'worker']));
    $r->addRoute('GET', '/scans/new-video', $auth('views/scans/new-video.php', ['admin', 'supervisor', 'worker']));
    if ($liveEnabled) {
        $r->addRoute('GET', '/scans/live-capture', $auth('views/scans/live-capture.php', ['admin', 'supervisor', 'worker']));
    }
    $r->addRoute('GET', '/scans/compare', $auth('views/scans/advanced-compare.php', ['admin', 'supervisor', 'worker']));
    $r->addRoute('GET', '/leading-indicators/check-in', $auth('views/org/leading-indicators.php', ['admin', 'supervisor', 'worker', 'observer']));
    $r->addRoute('GET', '/copilot', $auth('views/copilot/index.php', ['admin', 'supervisor', 'observer']));
    $r->addRoute('GET', '/scans/{id:\d+}', $auth('views/scans/results.php', ['admin', 'supervisor', 'worker', 'observer']));
    $r->addRoute('GET', '/scans/{id:\d+}/compare', $auth('views/scans/compare.php', ['admin', 'supervisor', 'worker']));
    $r->addRoute('GET', '/scans/{id:\d+}/observe', $auth('views/observer/rate.php', ['admin', 'observer']));

    $r->addRoute('GET', '/admin/dashboard', $auth('views/admin/dashboard.php', ['super_admin']));
    $r->addRoute('GET', '/admin/organizations', $auth('views/admin/organizations.php', ['super_admin']));
    $r->addRoute('GET', '/admin/users', $auth('views/admin/users.php', ['super_admin']));
    $r->addRoute('GET', '/admin/plans', $auth('views/admin/plans.php', ['super_admin']));
    $r->addRoute('GET', '/admin/settings', $auth('views/admin/settings.php', ['super_admin']));

    $r->addRoute('GET', '/org/users', $auth('views/org/users.php', ['admin', 'supervisor']));
    $r->addRoute('GET', '/org/settings', $auth('views/org/settings.php', ['admin', 'supervisor']));
    $r->addRoute('GET', '/org/billing', $auth('views/org/billing.php', ['admin', 'supervisor']));
};
