<?php

declare(strict_types=1);

/**
 * API route definitions.
 *
 * Receives the DI container and returns a FastRoute route-collector callback.
 * Each route handler is a closure with signature (array $vars, array $body): void.
 * The container is lazy — controllers are only built if the matched route needs them.
 *
 * @param  WorkEddy\Core\Container  $c
 * @return Closure(FastRoute\RouteCollector): void
 */

use WorkEddy\Core\Container;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\WorkerContract;

return static function (Container $c): Closure {
    return static function (FastRoute\RouteCollector $r) use ($c): void {

        // ── Health ────────────────────────────────────────────────────
        $r->addRoute('GET', '/health', fn () =>
            Response::json(['status' => 'ok', 'service' => 'workeddy-api', 'timestamp' => gmdate('c')])
        );

        // ── Auth ──────────────────────────────────────────────────────
        $r->addRoute('POST', '/auth/signup', fn ($v, $b) => $c->authCtrl()->signup($b));
        $r->addRoute('POST', '/auth/login',  fn ($v, $b) => $c->authCtrl()->login($b));
        $r->addRoute('POST', '/auth/logout', function () use ($c) {
            $c->auth();
            Response::json(['message' => 'Logged out']);
        });
        $r->addRoute('GET',  '/auth/me', function () use ($c) {
            Response::json(['user' => $c->auth()]);
        });
        $r->addRoute('POST', '/auth/refresh', fn ($v, $b) => $c->authCtrl()->refresh($c->auth()));

        // ── OTP & 2FA ─────────────────────────────────────────────────
        $r->addRoute('POST', '/auth/send-otp',     fn ($v, $b) => $c->authCtrl()->sendOtp($b));
        $r->addRoute('POST', '/auth/verify-otp',   fn ($v, $b) => $c->authCtrl()->verifyOtp($b));
        $r->addRoute('POST', '/auth/2fa/verify',   fn ($v, $b) => $c->authCtrl()->verify2fa($c->auth(), $b));
        $r->addRoute('GET',  '/auth/2fa/status',   fn ($v, $b) => $c->authCtrl()->get2faStatus($c->auth()));
        $r->addRoute('POST', '/auth/2fa/setup',    fn ($v, $b) => $c->authCtrl()->setup2fa($c->auth()));
        $r->addRoute('POST', '/auth/2fa/confirm',  fn ($v, $b) => $c->authCtrl()->confirm2fa($c->auth(), $b));
        $r->addRoute('POST', '/auth/2fa/disable',  fn ($v, $b) => $c->authCtrl()->disable2fa($c->auth()));

        // ── Users ─────────────────────────────────────────────────────
        $r->addRoute('GET',  '/users', fn ($v, $b) => $c->workspaceCtrl()->listUsers($c->auth()));
        $r->addRoute('POST', '/users', fn ($v, $b) => $c->workspaceCtrl()->createUser($c->auth(), $b));

        // ── User Profile ──────────────────────────────────────────────
        $r->addRoute('GET',  '/user/profile', fn ($v, $b) => $c->profileCtrl()->getProfile($c->auth()));
        $r->addRoute('PUT',  '/user/profile', fn ($v, $b) => $c->profileCtrl()->updateProfile($c->auth(), $b));

        // ── Tasks ─────────────────────────────────────────────────────
        $r->addRoute('GET',  '/tasks',              fn ($v, $b) => $c->taskCtrl()->index($c->auth()));
        $r->addRoute('POST', '/tasks',              fn ($v, $b) => $c->taskCtrl()->create($c->auth(), $b));
        $r->addRoute('GET',  '/tasks/{id:\d+}',     fn ($v, $b) => $c->taskCtrl()->show($c->auth(), (int) $v['id']));

        // ── Scans ─────────────────────────────────────────────────────
        $r->addRoute('GET',  '/scans/models',       fn ($v, $b) => $c->scanCtrl()->listModels());
        $r->addRoute('POST', '/scans/manual',       fn ($v, $b) => $c->scanCtrl()->createManual($c->auth(), $b));
        $r->addRoute('POST', '/scans/video',        fn ($v, $b) => $c->scanCtrl()->createVideo($c->auth(), $b, $_FILES));
        $r->addRoute('GET',  '/scans',              fn ($v, $b) => $c->scanCtrl()->indexManual($c->auth(), isset($_GET['task_id']) ? (int) $_GET['task_id'] : null));
        $r->addRoute('GET',  '/scans/compare',      fn ($v, $b) => $c->scanCtrl()->compareScans($c->auth()));
        $r->addRoute('GET',  '/scans/{id:\d+}',     fn ($v, $b) => $c->scanCtrl()->show($c->auth(), (int) $v['id']));
        $r->addRoute('GET',  '/scans/{id:\d+}/compare', fn ($v, $b) => $c->scanCtrl()->compare($c->auth(), (int) $v['id']));

        // Closed-loop control actions (Phase 1)
        $r->addRoute('GET',  '/control-actions', fn ($v, $b) => $c->controlActionCtrl()->index($c->auth()));
        $r->addRoute('POST', '/control-actions/from-control', fn ($v, $b) => $c->controlActionCtrl()->createFromControl($c->auth(), $b));
        $r->addRoute('GET',  '/control-actions/{id:\d+}', fn ($v, $b) => $c->controlActionCtrl()->show($c->auth(), (int) $v['id']));
        $r->addRoute('PUT',  '/control-actions/{id:\d+}', fn ($v, $b) => $c->controlActionCtrl()->update($c->auth(), (int) $v['id'], $b));
        $r->addRoute('POST', '/control-actions/{id:\d+}/verify', fn ($v, $b) => $c->controlActionCtrl()->verify($c->auth(), (int) $v['id'], $b));

        // ── Live sessions (user-authenticated) ──────────────────────
        $r->addRoute('GET',  '/live/engines',                  fn ($v, $b) => $c->liveSessionCtrl()->engines());
        $r->addRoute('POST', '/live/sessions',                 fn ($v, $b) => $c->liveSessionCtrl()->start($c->auth(), $b));
        $r->addRoute('GET',  '/live/sessions',                 fn ($v, $b) => $c->liveSessionCtrl()->index($c->auth()));
        $r->addRoute('GET',  '/live/sessions/{id:\d+}',        fn ($v, $b) => $c->liveSessionCtrl()->show($c->auth(), (int) $v['id']));
        $r->addRoute('POST', '/live/sessions/{id:\d+}/stop',   fn ($v, $b) => $c->liveSessionCtrl()->stop($c->auth(), (int) $v['id']));
        $r->addRoute('GET',  '/live/sessions/{id:\d+}/stream', fn ($v, $b) => $c->liveSessionCtrl()->stream($c->auth(), (int) $v['id']));
        $r->addRoute('POST', '/live/sessions/{id:\d+}/frames', fn ($v, $b) => $c->liveSessionCtrl()->ingestFrames($c->auth(), (int) $v['id'], $b));
        $r->addRoute('GET',  '/live/sessions/{id:\d+}/frames', fn ($v, $b) => $c->liveSessionCtrl()->frames($c->auth(), (int) $v['id']));

        // ── Internal worker callbacks (token-authenticated) ───────────
        $r->addRoute('POST', WorkerContract::videoRoute('next_job'), fn ($v, $b) => $c->workerCtrl()->nextJob());
        $r->addRoute('POST', WorkerContract::videoRoute('complete'), fn ($v, $b) => $c->workerCtrl()->complete($b));
        $r->addRoute('POST', WorkerContract::videoRoute('fail'), fn ($v, $b) => $c->workerCtrl()->fail($b));

        // ── Internal live-worker callbacks (token-authenticated) ──────
        $r->addRoute('POST', WorkerContract::liveRoute('next_job'), fn ($v, $b) => $c->liveWorkerCtrl()->nextJob());
        $r->addRoute('POST', WorkerContract::liveRoute('next_batch'), fn ($v, $b) => $c->liveWorkerCtrl()->nextFrameBatch());
        $r->addRoute('POST', WorkerContract::liveRoute('frames'), fn ($v, $b) => $c->liveWorkerCtrl()->reportFrames($b));
        $r->addRoute('POST', WorkerContract::liveRoute('complete'), fn ($v, $b) => $c->liveWorkerCtrl()->complete($b));
        $r->addRoute('POST', WorkerContract::liveRoute('fail'), fn ($v, $b) => $c->liveWorkerCtrl()->fail($b));

        // ── Observer ──────────────────────────────────────────────────
        $r->addRoute('POST', '/observer-rating',            fn ($v, $b) => $c->observerCtrl()->rate($c->auth(), $b));
        $r->addRoute('GET',  '/observer-rating/{id:\d+}',   fn ($v, $b) => $c->observerCtrl()->listByScan($c->auth(), (int) $v['id']));

        // ── Dashboard ─────────────────────────────────────────────────
        $r->addRoute('GET', '/dashboard', fn ($v, $b) => $c->dashCtrl()->show($c->auth()));

        // ── Continuous leading indicators (Phase 2) ─────────────────
        $r->addRoute('POST', '/leading-indicators', fn ($v, $b) => $c->leadingIndicatorCtrl()->submit($c->auth(), $b));
        $r->addRoute('GET', '/leading-indicators/mine', fn ($v, $b) => $c->leadingIndicatorCtrl()->mine($c->auth()));
        $r->addRoute('GET', '/leading-indicators/summary', fn ($v, $b) => $c->leadingIndicatorCtrl()->summary($c->auth()));
        $r->addRoute('GET', '/worker/coaching', fn ($v, $b) => $c->workerCoachingCtrl()->mine($c->auth()));
        $r->addRoute('POST', '/copilot/{persona:[a-z\\-]+}', fn ($v, $b) => $c->copilotCtrl()->assist($c->auth(), (string) $v['persona'], $b));

        // ── Notifications ─────────────────────────────────────────────
        $r->addRoute('GET',  '/notifications',                  fn ($v, $b) => $c->notificationCtrl()->index($c->auth()));
        $r->addRoute('GET',  '/notifications/unread-count',     fn ($v, $b) => $c->notificationCtrl()->unreadCount($c->auth()));
        $r->addRoute('PUT',  '/notifications/read-all',         fn ($v, $b) => $c->notificationCtrl()->markAllRead($c->auth()));
        $r->addRoute('PUT',  '/notifications/{id:\d+}/read',    fn ($v, $b) => $c->notificationCtrl()->markRead($c->auth(), (int) $v['id']));
        $r->addRoute('POST', '/notifications/send',              fn ($v, $b) => $c->notificationCtrl()->send($c->auth(), $b));

        // ── Billing ───────────────────────────────────────────────────
        $r->addRoute('GET', '/billing/usage', fn ($v, $b) => $c->billingCtrl()->usage($c->auth()));
        $r->addRoute('GET', '/billing/plans', fn ($v, $b) => $c->billingCtrl()->plans($c->auth()));
        $r->addRoute('GET', '/billing/invoices', fn ($v, $b) => $c->billingCtrl()->invoices($c->auth()));
        $r->addRoute('POST', '/billing/invoices/{id:\\d+}/charge', fn ($v, $b) => $c->billingCtrl()->chargeInvoice($c->auth(), (int) $v['id'], $b));

        // ── Admin (system-wide, role: super_admin) ────────────────────
        $r->addRoute('GET',    '/admin/stats',                    fn ($v, $b) => $c->adminCtrl()->stats($c->auth()));
        $r->addRoute('GET',    '/admin/organizations',            fn ($v, $b) => $c->adminCtrl()->listOrganizations($c->auth()));
        $r->addRoute('POST',   '/admin/organizations',            fn ($v, $b) => $c->adminCtrl()->createOrganization($c->auth(), $b));
        $r->addRoute('GET',    '/admin/organizations/{id:\d+}',   fn ($v, $b) => $c->adminCtrl()->showOrganization($c->auth(), (int) $v['id']));
        $r->addRoute('PUT',    '/admin/organizations/{id:\d+}',   fn ($v, $b) => $c->adminCtrl()->updateOrganization($c->auth(), (int) $v['id'], $b));
        $r->addRoute('GET',    '/admin/users',                    fn ($v, $b) => $c->adminCtrl()->listUsers($c->auth()));
        $r->addRoute('PUT',    '/admin/users/{id:\d+}',           fn ($v, $b) => $c->adminCtrl()->updateUser($c->auth(), (int) $v['id'], $b));
        $r->addRoute('DELETE', '/admin/users/{id:\d+}',           fn ($v, $b) => $c->adminCtrl()->deleteUser($c->auth(), (int) $v['id']));
        $r->addRoute('GET',    '/admin/plans',                    fn ($v, $b) => $c->adminCtrl()->listPlans($c->auth()));
        $r->addRoute('POST',   '/admin/plans',                    fn ($v, $b) => $c->adminCtrl()->createPlan($c->auth(), $b));
        $r->addRoute('PUT',    '/admin/plans/{id:\d+}',           fn ($v, $b) => $c->adminCtrl()->updatePlan($c->auth(), (int) $v['id'], $b));
        $r->addRoute('DELETE', '/admin/plans/{id:\d+}',           fn ($v, $b) => $c->adminCtrl()->deletePlan($c->auth(), (int) $v['id']));
        $r->addRoute('GET',    '/admin/settings',                 fn ($v, $b) => $c->adminCtrl()->getSystemSettings($c->auth()));
        $r->addRoute('PUT',    '/admin/settings',                 fn ($v, $b) => $c->adminCtrl()->updateSystemSettings($c->auth(), $b));

        // ── Organization management (role: admin / supervisor) ────────
        $r->addRoute('GET',    '/org/settings',          fn ($v, $b) => $c->orgCtrl()->getSettings($c->auth()));
        $r->addRoute('PUT',    '/org/settings',          fn ($v, $b) => $c->orgCtrl()->updateSettings($c->auth(), $b));
        $r->addRoute('GET',    '/org/members',           fn ($v, $b) => $c->orgCtrl()->listMembers($c->auth()));
        $r->addRoute('POST',   '/org/members',           fn ($v, $b) => $c->orgCtrl()->inviteMember($c->auth(), $b));
        $r->addRoute('PUT',    '/org/members/{id:\d+}',  fn ($v, $b) => $c->orgCtrl()->updateMemberRole($c->auth(), (int) $v['id'], $b));
        $r->addRoute('DELETE', '/org/members/{id:\d+}',  fn ($v, $b) => $c->orgCtrl()->removeMember($c->auth(), (int) $v['id']));
        $r->addRoute('GET',    '/org/subscription',      fn ($v, $b) => $c->orgCtrl()->getSubscription($c->auth()));
        $r->addRoute('PUT',    '/org/subscription',      fn ($v, $b) => $c->orgCtrl()->changePlan($c->auth(), $b));
    };
};

