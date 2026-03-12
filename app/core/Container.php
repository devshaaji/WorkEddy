<?php

declare(strict_types=1);

namespace WorkEddy\Core;

use Doctrine\DBAL\Connection;
use WorkEddy\Contracts\CacheInterface;
use WorkEddy\Contracts\QueueInterface;
use WorkEddy\Controllers\AdminController;
use WorkEddy\Controllers\AuthController;
use WorkEddy\Controllers\BillingController;
use WorkEddy\Controllers\CopilotController;
use WorkEddy\Controllers\ControlActionController;
use WorkEddy\Controllers\DashboardController;
use WorkEddy\Controllers\LeadingIndicatorController;
use WorkEddy\Controllers\NotificationController;
use WorkEddy\Controllers\ObserverController;
use WorkEddy\Controllers\OrgController;
use WorkEddy\Controllers\ProfileController;
use WorkEddy\Controllers\ScanController;
use WorkEddy\Controllers\TaskController;
use WorkEddy\Controllers\WorkspaceController;
use WorkEddy\Controllers\WorkerController;
use WorkEddy\Controllers\WorkerCoachingController;
use WorkEddy\Controllers\LiveSessionController;
use WorkEddy\Controllers\LiveWorkerController;
use WorkEddy\Middleware\AuthMiddleware;
use WorkEddy\Middleware\RateLimitMiddleware;
use WorkEddy\Repositories\AdminRepository;
use WorkEddy\Repositories\BillingRepository;
use WorkEddy\Repositories\CopilotAuditRepository;
use WorkEddy\Repositories\ControlRecommendationRepository;
use WorkEddy\Repositories\ControlActionRepository;
use WorkEddy\Repositories\LeadingIndicatorRepository;
use WorkEddy\Repositories\NotificationRepository;
use WorkEddy\Repositories\LiveSessionRepository;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Repositories\TaskRepository;
use WorkEddy\Repositories\UserRepository;
use WorkEddy\Repositories\WorkspaceRepository;
use WorkEddy\Services\AdminService;
use WorkEddy\Services\AuthService;
use WorkEddy\Services\BillingPeriodService;
use WorkEddy\Services\BillingService;
use WorkEddy\Services\CopilotAuditService;
use WorkEddy\Services\CopilotDeterministicService;
use WorkEddy\Services\CopilotNarrativeService;
use WorkEddy\Services\CopilotRedactionService;
use WorkEddy\Services\ControlRecommendationService;
use WorkEddy\Services\ErgonomicsCopilotService;
use WorkEddy\Services\ControlActionService;
use WorkEddy\Services\Cache\ArrayCacheDriver;
use WorkEddy\Services\Cache\FileCacheDriver;
use WorkEddy\Services\Cache\RedisCacheDriver;
use WorkEddy\Services\DashboardService;
use WorkEddy\Services\EmailService;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\JwtService;
use WorkEddy\Services\LeadingIndicatorService;
use WorkEddy\Services\ImprovementProofService;
use WorkEddy\Services\NotificationService;
use WorkEddy\Services\ObserverService;
use WorkEddy\Services\OrgService;
use WorkEddy\Services\Payments\PaymentGatewayService;
use WorkEddy\Services\Queue\DatabaseQueueDriver;
use WorkEddy\Services\Queue\RedisQueueDriver;
use WorkEddy\Services\ScanComparisonService;
use WorkEddy\Services\LiveSessionService;
use WorkEddy\Services\ScanService;
use WorkEddy\Services\TaskService;
use WorkEddy\Services\UsageMeterService;
use WorkEddy\Services\UserService;
use WorkEddy\Services\VideoProcessingService;
use WorkEddy\Services\WorkerCoachingService;

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

    public function leadingIndicatorRepo(): LeadingIndicatorRepository
    {
        return $this->make('leadingIndicatorRepo', fn () => new LeadingIndicatorRepository($this->db()));
    }

    public function controlRecommendationRepo(): ControlRecommendationRepository
    {
        return $this->make('controlRecommendationRepo', fn () => new ControlRecommendationRepository($this->db()));
    }

    public function controlActionRepo(): ControlActionRepository
    {
        return $this->make('controlActionRepo', fn () => new ControlActionRepository($this->db()));
    }

    public function copilotAuditRepo(): CopilotAuditRepository
    {
        return $this->make('copilotAuditRepo', fn () => new CopilotAuditRepository($this->db()));
    }

    public function liveSessionRepo(): LiveSessionRepository
    {
        return $this->make('liveSessionRepo', fn () => new LiveSessionRepository($this->db()));
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

    public function controlRecommendationEngine(): ControlRecommendationService
    {
        return $this->make('controlRecommendationEngine', fn () => new ControlRecommendationService());
    }

    public function improvementProofService(): ImprovementProofService
    {
        return $this->make('improvementProofService', fn () => new ImprovementProofService());
    }

    public function emailService(): EmailService
    {
        return $this->make('emailService', fn () => new EmailService());
    }

    public function leadingIndicatorService(): LeadingIndicatorService
    {
        return $this->make('leadingIndicatorService', fn () => new LeadingIndicatorService($this->leadingIndicatorRepo()));
    }

    public function workerCoachingService(): WorkerCoachingService
    {
        return $this->make('workerCoachingService', fn () => new WorkerCoachingService(
            $this->leadingIndicatorRepo(),
            $this->scanRepo(),
            $this->controlActionRepo(),
        ));
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
            $this->workspaceRepo(),
            $this->controlRecommendationRepo(),
            $this->controlRecommendationEngine(),
            $this->improvementProofService(),
            $this->videoService(),
        ));
    }

    public function scanComparisonService(): ScanComparisonService
    {
        return $this->make('scanComparisonService', fn () => new ScanComparisonService(
            $this->scanRepo(),
            $this->assessmentEngine(),
            $this->improvementProofService(),
        ));
    }

    public function controlActionService(): ControlActionService
    {
        return $this->make('controlActionService', fn () => new ControlActionService(
            $this->controlActionRepo(),
            $this->scanRepo(),
            $this->userRepo(),
            $this->improvementProofService(),
            $this->scanComparisonService(),
        ));
    }

    public function dashboardService(): DashboardService
    {
        return $this->make('dashboardService', fn () => new DashboardService($this->db()));
    }

    public function copilotDeterministicService(): CopilotDeterministicService
    {
        return $this->make('copilotDeterministicService', fn () => new CopilotDeterministicService(
            $this->db(),
            $this->scanRepo(),
            $this->controlActionRepo(),
            $this->scanComparisonService(),
        ));
    }

    public function copilotNarrativeService(): CopilotNarrativeService
    {
        return $this->make('copilotNarrativeService', fn () => new CopilotNarrativeService());
    }

    public function copilotRedactionService(): CopilotRedactionService
    {
        return $this->make('copilotRedactionService', fn () => new CopilotRedactionService());
    }

    public function copilotAuditService(): CopilotAuditService
    {
        return $this->make('copilotAuditService', fn () => new CopilotAuditService(
            $this->copilotAuditRepo(),
            $this->copilotRedactionService(),
        ));
    }

    public function ergonomicsCopilotService(): ErgonomicsCopilotService
    {
        return $this->make('ergonomicsCopilotService', fn () => new ErgonomicsCopilotService(
            $this->copilotDeterministicService(),
            $this->copilotNarrativeService(),
            $this->copilotAuditService(),
        ));
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

    public function liveSessionService(): LiveSessionService
    {
        return $this->make('liveSessionService', fn () => new LiveSessionService(
            $this->liveSessionRepo(),
            $this->taskRepo(),
            $this->workspaceRepo(),
            $this->assessmentEngine(),
            $this->queue(),
            $this->cache(),
            (array) (require __DIR__ . '/../config/live.php'),
        ));
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

    public function leadingIndicatorCtrl(): LeadingIndicatorController
    {
        return $this->make('leadingIndicatorCtrl', fn () => new LeadingIndicatorController($this->leadingIndicatorService()));
    }

    public function workerCoachingCtrl(): WorkerCoachingController
    {
        return $this->make('workerCoachingCtrl', fn () => new WorkerCoachingController($this->workerCoachingService()));
    }

    public function copilotCtrl(): CopilotController
    {
        return $this->make('copilotCtrl', fn () => new CopilotController($this->ergonomicsCopilotService()));
    }

    public function controlActionCtrl(): ControlActionController
    {
        return $this->make('controlActionCtrl', fn () => new ControlActionController($this->controlActionService()));
    }

    public function liveSessionCtrl(): LiveSessionController
    {
        return $this->make('liveSessionCtrl', fn () => new LiveSessionController($this->liveSessionService()));
    }

    public function liveWorkerCtrl(): LiveWorkerController
    {
        return $this->make('liveWorkerCtrl', fn () => new LiveWorkerController(
            $this->liveSessionService(),
            $this->queue(),
            (string) ((require __DIR__ . '/../config/live.php')['queue_name'] ?? 'live_session_jobs'),
        ));
    }

    // ─── Internal ─────────────────────────────────────────────────────

    /** @template T */
    private function make(string $key, callable $factory): mixed
    {
        return $this->cache[$key] ??= $factory();
    }
}
