<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Api;

use Doctrine\DBAL\Connection;
use FastRoute\Dispatcher;
use PHPUnit\Framework\TestCase;
use WorkEddy\Core\Container;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\ScanComparisonService;

final class ScanCompareApiIntegrationTest extends TestCase
{
    public function testScansCompareRouteIsRegistered(): void
    {
        $routesFactory = require __DIR__ . '/../../routes/api.php';
        $container = new Container();
        $dispatcher = \FastRoute\simpleDispatcher($routesFactory($container));

        $routeInfo = $dispatcher->dispatch('GET', '/scans/compare');

        $this->assertSame(Dispatcher::FOUND, $routeInfo[0]);
        $this->assertIsCallable($routeInfo[1]);
    }

    public function testComparisonPipelineProducesApiReadyResponseShape(): void
    {
        $service = $this->makeService(
            [
                701 => [
                    'id' => 701,
                    'organization_id' => 7,
                    'user_id' => 1,
                    'task_id' => 5,
                    'scan_type' => 'video',
                    'model' => 'reba',
                    'raw_score' => 8.0,
                    'normalized_score' => 53.33,
                    'risk_category' => 'high',
                    'status' => 'completed',
                    'created_at' => '2026-03-08 09:00:00',
                    'result_score' => 8.0,
                    'risk_level' => 'High',
                    'recommendation' => '',
                ],
                702 => [
                    'id' => 702,
                    'organization_id' => 7,
                    'user_id' => 1,
                    'task_id' => 5,
                    'scan_type' => 'manual',
                    'model' => 'reba',
                    'raw_score' => 4.0,
                    'normalized_score' => 26.67,
                    'risk_category' => 'low',
                    'status' => 'completed',
                    'created_at' => '2026-03-08 09:30:00',
                    'result_score' => 4.0,
                    'risk_level' => 'Low',
                    'recommendation' => '',
                ],
            ],
            [
                701 => ['trunk_angle' => 40, 'neck_angle' => 18, 'upper_arm_angle' => 65, 'lower_arm_angle' => 75, 'wrist_angle' => 16],
                702 => ['trunk_angle' => 15, 'neck_angle' => 10, 'upper_arm_angle' => 35, 'lower_arm_angle' => 85, 'wrist_angle' => 8],
            ]
        );

        $comparison = $service->compare(7, 701, 702);
        $response = ['data' => $comparison];

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('model', $response['data']);
        $this->assertArrayHasKey('summary', $response['data']);
        $this->assertArrayHasKey('score_delta', $response['data']);
        $this->assertArrayHasKey('nodes', $response['data']);
        $this->assertArrayHasKey('pose_delta', $response['data']);
        $this->assertNotEmpty($response['data']['nodes']);
    }

    private function makeService(array $scans, array $metrics): ScanComparisonService
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $params = []) use ($scans, $metrics) {
                $id = (int) ($params['id'] ?? 0);

                if (str_contains($sql, 'FROM scans s')) {
                    return $scans[$id] ?? false;
                }

                if (str_contains($sql, 'FROM scan_metrics')) {
                    return $metrics[$id] ?? false;
                }

                return false;
            });

        return new ScanComparisonService(new ScanRepository($conn), new AssessmentEngine());
    }
}
