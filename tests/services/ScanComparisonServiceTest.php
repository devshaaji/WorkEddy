<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\ScanComparisonService;

final class ScanComparisonServiceTest extends TestCase
{
    public function testRulaComparisonReturnsScoreNodesAndPoseDelta(): void
    {
        $service = $this->makeService(
            [
                101 => $this->scanRow(101, 'rula', 'video', 6.0, 85.71, 'high'),
                102 => $this->scanRow(102, 'rula', 'video', 3.0, 42.86, 'moderate'),
            ],
            [
                101 => ['trunk_angle' => 50, 'neck_angle' => 25, 'upper_arm_angle' => 60, 'lower_arm_angle' => 80, 'wrist_angle' => 18],
                102 => ['trunk_angle' => 20, 'neck_angle' => 10, 'upper_arm_angle' => 30, 'lower_arm_angle' => 85, 'wrist_angle' => 7],
            ]
        );

        $result = $service->compare(10, 101, 102);

        $this->assertSame('rula', $result['model']);
        $this->assertSame('rula_official_v1', $result['algorithm_version']);
        $this->assertSame(-3.0, $result['score_delta']['raw']);
        $this->assertSame(-42.85, $result['score_delta']['normalized']);
        $this->assertSame('improved', $result['summary']['direction']);
        $this->assertNotEmpty($result['nodes']);
        $this->assertTrue($result['pose_delta']['available']);
        $this->assertArrayHasKey('trunk_angle', $result['pose_delta']['angles']);
    }

    public function testRebaComparisonReturnsNodeDeltas(): void
    {
        $service = $this->makeService(
            [
                201 => $this->scanRow(201, 'reba', 'manual', 9.0, 60.00, 'high'),
                202 => $this->scanRow(202, 'reba', 'manual', 5.0, 33.33, 'low'),
            ],
            [
                201 => ['trunk_angle' => 45, 'neck_angle' => 20, 'upper_arm_angle' => 70, 'lower_arm_angle' => 70, 'wrist_angle' => 20, 'leg_score' => 2, 'load_weight' => 10],
                202 => ['trunk_angle' => 15, 'neck_angle' => 10, 'upper_arm_angle' => 30, 'lower_arm_angle' => 85, 'wrist_angle' => 8, 'leg_score' => 1, 'load_weight' => 3],
            ]
        );

        $result = $service->compare(10, 201, 202);

        $this->assertSame('reba', $result['model']);
        $this->assertNotEmpty($result['nodes']);

        $nodeKeys = array_column($result['nodes'], 'key');
        $this->assertContains('trunk_angle', $nodeKeys);
        $this->assertContains('upper_arm_angle', $nodeKeys);
    }

    public function testNioshComparisonWorksAndPoseIsUnavailableWhenAnglesMissing(): void
    {
        $service = $this->makeService(
            [
                301 => $this->scanRow(301, 'niosh', 'manual', 2.4, 80.00, 'high'),
                302 => $this->scanRow(302, 'niosh', 'manual', 1.2, 40.00, 'moderate'),
            ],
            [
                301 => ['load_weight' => 18, 'horizontal_distance' => 45, 'vertical_start' => 60, 'vertical_travel' => 40, 'twist_angle' => 35, 'frequency' => 4, 'coupling' => 'fair'],
                302 => ['load_weight' => 10, 'horizontal_distance' => 30, 'vertical_start' => 70, 'vertical_travel' => 25, 'twist_angle' => 15, 'frequency' => 2, 'coupling' => 'good'],
            ]
        );

        $result = $service->compare(10, 301, 302);

        $this->assertSame('niosh', $result['model']);
        $this->assertNotEmpty($result['nodes']);
        $this->assertFalse($result['pose_delta']['available']);
        $this->assertSame('Missing pose angle data on one or both scans', $result['pose_delta']['reason']);
    }

    public function testCompareThrowsForMismatchedModels(): void
    {
        $service = $this->makeService(
            [
                401 => $this->scanRow(401, 'rula', 'manual', 4.0, 57.14, 'moderate'),
                402 => $this->scanRow(402, 'reba', 'manual', 4.0, 26.67, 'low'),
            ],
            [
                401 => ['trunk_angle' => 20, 'neck_angle' => 15, 'upper_arm_angle' => 40, 'lower_arm_angle' => 90, 'wrist_angle' => 12],
                402 => ['trunk_angle' => 20, 'neck_angle' => 15, 'upper_arm_angle' => 40, 'lower_arm_angle' => 90, 'wrist_angle' => 12],
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot compare scans from different models');

        $service->compare(10, 401, 402);
    }

    public function testCompareThrowsForMismatchedAlgorithmVersions(): void
    {
        $service = $this->makeService(
            [
                801 => $this->scanRow(801, 'rula', 'manual', 4.0, 57.14, 'moderate', 'legacy_v1'),
                802 => $this->scanRow(802, 'rula', 'manual', 4.0, 57.14, 'moderate', 'rula_official_v1'),
            ],
            [
                801 => ['trunk_angle' => 20, 'neck_angle' => 10],
                802 => ['trunk_angle' => 20, 'neck_angle' => 10],
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot compare scans scored with different algorithm versions');

        $service->compare(10, 801, 802);
    }

    public function testCompareHandlesMissingPoseData(): void
    {
        $service = $this->makeService(
            [
                501 => $this->scanRow(501, 'rula', 'manual', 4.0, 57.14, 'moderate'),
                502 => $this->scanRow(502, 'rula', 'manual', 3.0, 42.86, 'moderate'),
            ],
            [
                501 => ['leg_score' => 2],
                502 => ['leg_score' => 1],
            ]
        );

        $result = $service->compare(10, 501, 502);

        $this->assertFalse($result['pose_delta']['available']);
        $this->assertSame([], $result['pose_delta']['angles']);
    }

    public function testCompareThrowsWhenSameScanId(): void
    {
        $service = $this->makeService(
            [601 => $this->scanRow(601, 'rula', 'manual', 4.0, 57.14, 'moderate')],
            [601 => ['trunk_angle' => 20]]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('scanA and scanB must be different scans');

        $service->compare(10, 601, 601);
    }

    public function testCompareThrowsForZeroOrNegativeScanId(): void
    {
        $service = $this->makeService([], []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('scanA and scanB must be positive integers');

        $service->compare(10, 0, -1);
    }

    public function testCompareDirectionUnchangedWhenScoresEqual(): void
    {
        $service = $this->makeService(
            [
                701 => $this->scanRow(701, 'reba', 'manual', 5.0, 33.33, 'low'),
                702 => $this->scanRow(702, 'reba', 'manual', 5.0, 33.33, 'low'),
            ],
            [
                701 => ['trunk_angle' => 20, 'neck_angle' => 10],
                702 => ['trunk_angle' => 20, 'neck_angle' => 10],
            ]
        );

        $result = $service->compare(10, 701, 702);

        $this->assertSame('unchanged', $result['summary']['direction']);
        $this->assertSame(0.0, $result['score_delta']['normalized']);
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

        $repo = new ScanRepository($conn);
        return new ScanComparisonService($repo, new AssessmentEngine());
    }

    private function scanRow(
        int $id,
        string $model,
        string $scanType,
        float $resultScore,
        float $normalizedScore,
        string $riskCategory,
        ?string $algorithmVersion = null
    ): array {
        if ($algorithmVersion === null) {
            $algorithmVersion = match ($model) {
                'rula' => 'rula_official_v1',
                'reba' => 'reba_official_v1',
                'niosh' => 'niosh_official_v1',
                default => 'legacy_v1',
            };
        }

        return [
            'id' => $id,
            'organization_id' => 10,
            'user_id' => 1,
            'task_id' => 1,
            'scan_type' => $scanType,
            'model' => $model,
            'raw_score' => $resultScore,
            'normalized_score' => $normalizedScore,
            'risk_category' => $riskCategory,
            'status' => 'completed',
            'video_path' => null,
            'error_message' => null,
            'parent_scan_id' => null,
            'created_at' => '2026-03-08 10:00:00',
            'result_score' => $resultScore,
            'risk_level' => 'Risk',
            'recommendation' => '',
            'algorithm_version' => $algorithmVersion,
        ];
    }
}