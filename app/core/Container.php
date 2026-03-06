<?php

declare(strict_types=1);

namespace WorkEddy\Core;

use Doctrine\DBAL\Connection;
use WorkEddy\Controllers\AdminController;
use WorkEddy\Controllers\AuthController;
use WorkEddy\Controllers\BillingController;
use WorkEddy\Controllers\DashboardController;
use WorkEddy\Controllers\ObserverController;
use WorkEddy\Controllers\OrgController;
use WorkEddy\Controllers\ScanController;
use WorkEddy\Controllers\TaskController;
use WorkEddy\Controllers\WorkspaceController;
use WorkEddy\Middleware\AuthMiddleware;
use WorkEddy\Repositories\AdminRepository;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Repositories\UserRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\AdminService;
use WorkEddy\Services\AuthService;
use WorkEddy\Services\BillingService;
use WorkEddy\Services\DashboardService;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\JwtService;
use WorkEddy\Services\ObserverService;
use WorkEddy\Services\OrgService;
use WorkEddy\Services\QueueService;
use WorkEddy\Services\ScanService;
use WorkEddy\Services\TaskService;
use WorkEddy\Services\UsageMeterService;
use WorkEddy\Services\UserService;
use WorkEddy\Services\VideoProcessingService;

/**
 * Lightweight lazy-singleton service container.
 *
 * Every dependency is created once on first access and reused for the
 * lifetime of the request.  No reflection, no config files — just
 * explicit wiring in one place.
 */
final class Container
{
    private array $cache = [];

    // ─── Infrastructure ───────────────────────────────────────────────

    public function db(): Connection
    {
        return $this->make('db', fn () => Database::connection());
    }

    // ─── Repositories ─────────────────────────────────────────────────

    public function userRepo(): UserRepository
    {
        return $this->make('userRepo', fn () => new UserRepository($this->db()));
    }

    public function taskRepo(): TaskRepository
    {
        return $this->make('taskRepo', fn () => new TaskRepository($this->db()));
    }

    public function scanRepo(): ScanRepository
    {
        return $this->make('scanRepo', fn () => new ScanRepository($this->db()));
    }

    public function workspaceRepo(): WorkspaceRepository
    {
        return $this->make('workspaceRepo', fn () => new WorkspaceRepository($this->db()));
    }

    public function adminRepo(): AdminRepository
    {
        return $this->make('adminRepo', fn () => new AdminRepository($this->db()));
    }

    // ─── Services ─────────────────────────────────────────────────────

    public function jwt(): JwtService
    {
        return $this->make('jwt', fn () => new JwtService());
    }

    public function queue(): QueueService
    {
        return $this->make('queue', fn () => new QueueService());
    }

    public function assessmentEngine(): AssessmentEngine
    {
        return $this->make('assessmentEngine', fn () => new AssessmentEngine());
    }

    public function usageMeter(): UsageMeterService
    {
        return $this->make('usageMeter', fn () => new UsageMeterService($this->workspaceRepo()));
    }

    public function videoService(): VideoProcessingService
    {
        return $this->make('videoService', fn () => new VideoProcessingService());
    }

    public function authService(): AuthService
    {
        return $this->make('authService', fn () => new AuthService(
            $this->userRepo(),
            $this->workspaceRepo(),
            $this->jwt(),
        ));
    }

    public function userService(): UserService
    {
        return $this->make('userService', fn () => new UserService($this->userRepo()));
    }

    public function taskService(): TaskService
    {
        return $this->make('taskService', fn () => new TaskService($this->taskRepo()));
    }

    public function scanService(): ScanService
    {
        return $this->make('scanService', fn () => new ScanService(
            $this->scanRepo(),
            $this->taskRepo(),
            $this->assessmentEngine(),
            $this->usageMeter(),
            $this->queue(),
        ));
    }

    public function dashboardService(): DashboardService
    {
        return $this->make('dashboardService', fn () => new DashboardService($this->db()));
    }

    public function observerService(): ObserverService
    {
        return $this->make('observerService', fn () => new ObserverService($this->db()));
    }

    public function billingService(): BillingService
    {
        return $this->make('billingService', fn () => new BillingService($this->workspaceRepo()));
    }

    public function adminService(): AdminService
    {
        return $this->make('adminService', fn () => new AdminService($this->adminRepo()));
    }

    public function orgService(): OrgService
    {
        return $this->make('orgService', fn () => new OrgService(
            $this->workspaceRepo(),
            $this->userRepo(),
        ));
    }

    // ─── Middleware ───────────────────────────────────────────────────

    public function authMiddleware(): AuthMiddleware
    {
        return $this->make('authMiddleware', fn () => new AuthMiddleware($this->jwt()));
    }

    /**
     * Shortcut: run auth middleware and return JWT claims.
     */
    public function auth(): array
    {
        return $this->authMiddleware()->handle();
    }

    // ─── Controllers ──────────────────────────────────────────────────

    public function authCtrl(): AuthController
    {
        return $this->make('authCtrl', fn () => new AuthController($this->authService()));
    }

    public function taskCtrl(): TaskController
    {
        return $this->make('taskCtrl', fn () => new TaskController($this->taskService()));
    }

    public function scanCtrl(): ScanController
    {
        return $this->make('scanCtrl', fn () => new ScanController(
            $this->scanService(),
            $this->videoService(),
            $this->assessmentEngine(),
        ));
    }

    public function dashCtrl(): DashboardController
    {
        return $this->make('dashCtrl', fn () => new DashboardController($this->dashboardService()));
    }

    public function observerCtrl(): ObserverController
    {
        return $this->make('observerCtrl', fn () => new ObserverController($this->observerService()));
    }

    public function billingCtrl(): BillingController
    {
        return $this->make('billingCtrl', fn () => new BillingController($this->billingService()));
    }

    public function workspaceCtrl(): WorkspaceController
    {
        return $this->make('workspaceCtrl', fn () => new WorkspaceController($this->userService()));
    }

    public function adminCtrl(): AdminController
    {
        return $this->make('adminCtrl', fn () => new AdminController($this->adminService()));
    }

    public function orgCtrl(): OrgController
    {
        return $this->make('orgCtrl', fn () => new OrgController($this->orgService()));
    }

    // ─── Internal ─────────────────────────────────────────────────────

    /** @template T */
    private function make(string $key, callable $factory): mixed
    {
        return $this->cache[$key] ??= $factory();
    }
}
