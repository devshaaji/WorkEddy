<?php

declare(strict_types=1);

namespace WorkEddy\Core;

use Doctrine\DBAL\Connection;
use WorkEddy\Contracts\CacheInterface;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Controllers\AdminController;
use WorkEddy\Controllers\AuthController;
use WorkEddy\Controllers\BillingController;
use WorkEddy\Controllers\DashboardController;
use WorkEddy\Controllers\NotificationController;
use WorkEddy\Controllers\ObserverController;
use WorkEddy\Controllers\OrgController;
use WorkEddy\Controllers\ProfileController;
use WorkEddy\Controllers\ScanController;
use WorkEddy\Controllers\TaskController;
use WorkEddy\Controllers\WorkspaceController;
use WorkEddy\Controllers\WorkerController;
use WorkEddy\Middleware\AuthMiddleware;
use WorkEddy\Middleware\RateLimitMiddleware;
use WorkEddy\Repositories\AdminRepository;
use WorkEddy\Repositories\BillingRepository;
use WorkEddy\Repositories\NotificationRepository;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Repositories\UserRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\AdminService;
use WorkEddy\Services\AuthService;
use WorkEddy\Services\BillingPeriodService;
use WorkEddy\Services\BillingService;
use WorkEddy\Services\Cache\ArrayCacheDriver;
use WorkEddy\Services\Cache\FileCacheDriver;
use WorkEddy\Services\Cache\RedisCacheDriver;
use WorkEddy\Services\DashboardService;
use WorkEddy\Services\EmailService;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\JwtService;
use WorkEddy\Services\NotificationService;
use WorkEddy\Services\ObserverService;
use WorkEddy\Services\OrgService;
use WorkEddy\Services\Payments\PaymentGatewayService;
use WorkEddy\Services\Queue\DatabaseQueueDriver;
use WorkEddy\Services\Queue\RedisQueueDriver;
use WorkEddy\Services\ScanComparisonService;
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

    public function redis(): RedisConnectionFactory
    {
        return $this->make('redis', fn () => new RedisConnectionFactory());
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

    public function billingRepo(): BillingRepository
    {
        return $this->make('billingRepo', fn () => new BillingRepository($this->db()));
    }

    public function notificationRepo(): NotificationRepository
    {
        return $this->make('notificationRepo', fn () => new NotificationRepository($this->db()));
    }

    // ─── Services ─────────────────────────────────────────────────────

    public function jwt(): JwtService
    {
        return $this->make('jwt', fn () => new JwtService());
    }

    public function queue(): QueueInterface
    {
        return $this->make('queue', function (): QueueInterface {
            $config = require __DIR__ . '/../config/queue.php';
            return match ($config['driver']) {
                'db'    => new DatabaseQueueDriver($this->db()),
                default => new RedisQueueDriver($this->redis()),
            };
        });
    }

    public function cache(): CacheInterface
    {
        return $this->make('cache', function (): CacheInterface {
            $config = require __DIR__ . '/../config/cache.php';
            return match ($config['driver']) {
                'file'  => new FileCacheDriver($config['path'] ?? null),
                'array' => new ArrayCacheDriver(),
                default => new RedisCacheDriver($this->redis(), $config['prefix'] ?? 'cache:'),
            };
        });
    }

    public function assessmentEngine(): AssessmentEngine
    {
        return $this->make('assessmentEngine', fn () => new AssessmentEngine());
    }

    public function billingPeriods(): BillingPeriodService
    {
        return $this->make('billingPeriods', fn () => new BillingPeriodService());
    }

    public function usageMeter(): UsageMeterService
    {
        return $this->make('usageMeter', fn () => new UsageMeterService(
            $this->workspaceRepo(),
            $this->billingPeriods(),
        ));
    }

    public function paymentGateway(): PaymentGatewayService
    {
        return $this->make('paymentGateway', fn () => new PaymentGatewayService($this->billingRepo()));
    }

    public function videoService(): VideoProcessingService
    {
        return $this->make('videoService', fn () => new VideoProcessingService());
    }

    public function emailService(): EmailService
    {
        return $this->make('emailService', fn () => new EmailService());
    }

    public function authService(): AuthService
    {
        return $this->make('authService', fn () => new AuthService(
            $this->userRepo(),
            $this->workspaceRepo(),
            $this->jwt(),
            $this->emailService(),
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
            $this->cache(),
            (int) ((require __DIR__ . '/../config/cache.php')['ttl'] ?? 300),
        ));
    }

    public function scanComparisonService(): ScanComparisonService
    {
        return $this->make('scanComparisonService', fn () => new ScanComparisonService(
            $this->scanRepo(),
            $this->assessmentEngine(),
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
        return $this->make('billingService', fn () => new BillingService(
            $this->workspaceRepo(),
            $this->usageMeter(),
            $this->billingRepo(),
            $this->paymentGateway(),
            $this->billingPeriods(),
        ));
    }

    public function notificationService(): NotificationService
    {
        return $this->make('notificationService', fn () => new NotificationService($this->notificationRepo()));
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
            $this->usageMeter(),
            $this->billingService(),
        ));
    }

    // ─── Middleware ───────────────────────────────────────────────────

    public function authMiddleware(): AuthMiddleware
    {
        return $this->make('authMiddleware', fn () => new AuthMiddleware($this->jwt()));
    }

    public function rateLimiter(): RateLimitMiddleware
    {
        return $this->make('rateLimiter', fn () => new RateLimitMiddleware($this->redis()));
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
            $this->scanComparisonService(),
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

    public function notificationCtrl(): NotificationController
    {
        return $this->make('notificationCtrl', fn () => new NotificationController($this->notificationService()));
    }

    public function workerCtrl(): WorkerController
    {
        return $this->make('workerCtrl', fn () => new WorkerController($this->scanService(), $this->queue()));
    }

    public function profileCtrl(): ProfileController
    {
        return $this->make('profileCtrl', fn () => new ProfileController($this->userService()));
    }

    // ─── Internal ─────────────────────────────────────────────────────

    /** @template T */
    private function make(string $key, callable $factory): mixed
    {
        return $this->cache[$key] ??= $factory();
    }
}
