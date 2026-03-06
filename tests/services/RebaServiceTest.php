<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use WorkEddy\Services\Ergonomics\RebaService;

final class RebaServiceTest extends TestCase
{
    private RebaService $reba;

    protected function setUp(): void
    {
        $this->reba = new RebaService();
    }

    public function testModelNameIsReba(): void
    {
        $this->assertSame('reba', $this->reba->modelName());
    }

    public function testSupportsManualAndVideo(): void
    {
        $types = $this->reba->supportedInputTypes();
        $this->assertContains('manual', $types);
        $this->assertContains('video', $types);
    }

    public function testLowRiskInputReturnsLowCategory(): void
    {
        $metrics = [
            'trunk_angle'     => 5,
            'neck_angle'      => 10,
            'upper_arm_angle' => 15,
            'lower_arm_angle' => 80,
            'wrist_angle'     => 5,
            'leg_score'       => 1,
            'load_weight'     => 0,
            'coupling'        => 'good',
        ];

        $result = $this->reba->calculateScore($metrics);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('risk_level', $result);
        $this->assertArrayHasKey('normalized_score', $result);
        $this->assertArrayHasKey('risk_category', $result);
        $this->assertArrayHasKey('recommendation', $result);

        $this->assertLessThanOrEqual(3, $result['score']);
        $this->assertSame('low', $result['risk_category']);
    }

    public function testHighRiskInputReturnsHighCategory(): void
    {
        $metrics = [
            'trunk_angle'     => 70,
            'neck_angle'      => 30,
            'upper_arm_angle' => 100,
            'lower_arm_angle' => 40,
            'wrist_angle'     => 25,
            'leg_score'       => 2,
            'load_weight'     => 15,
            'coupling'        => 'poor',
            'static_posture'  => true,
            'repetitive'      => true,
            'rapid_change'    => true,
        ];

        $result = $this->reba->calculateScore($metrics);

        $this->assertGreaterThanOrEqual(8, $result['score']);
        $this->assertSame('high', $result['risk_category']);
    }

    public function testScoreIsClampedToValidRange(): void
    {
        $metrics = [
            'trunk_angle'     => 0,
            'neck_angle'      => 0,
            'upper_arm_angle' => 0,
            'lower_arm_angle' => 80,
            'wrist_angle'     => 0,
        ];

        $result = $this->reba->calculateScore($metrics);
        $this->assertGreaterThanOrEqual(1, $result['score']);
        $this->assertLessThanOrEqual(15, $result['score']);
    }

    public function testNormalizedScoreIsPercentage(): void
    {
        $metrics = [
            'trunk_angle'     => 30,
            'neck_angle'      => 15,
            'upper_arm_angle' => 45,
            'lower_arm_angle' => 80,
            'wrist_angle'     => 10,
        ];

        $result = $this->reba->calculateScore($metrics);
        $this->assertGreaterThanOrEqual(0, $result['normalized_score']);
        $this->assertLessThanOrEqual(100, $result['normalized_score']);
    }

    public function testValidationThrowsOnMissingField(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('REBA requires field: trunk_angle');

        $this->reba->validate(['neck_angle' => 10]); // missing trunk_angle
    }

    public function testRiskLevelStrings(): void
    {
        $this->assertStringContainsString('Negligible', $this->reba->getRiskLevel(1));
        $this->assertStringContainsString('Low', $this->reba->getRiskLevel(2));
        $this->assertStringContainsString('Medium', $this->reba->getRiskLevel(5));
        $this->assertStringContainsString('High', $this->reba->getRiskLevel(9));
        $this->assertStringContainsString('Very High', $this->reba->getRiskLevel(12));
    }
}
