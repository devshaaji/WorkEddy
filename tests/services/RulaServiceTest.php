<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use WorkEddy\Services\Ergonomics\RulaService;

final class RulaServiceTest extends TestCase
{
    private RulaService $rula;

    protected function setUp(): void
    {
        $this->rula = new RulaService();
    }

    public function testModelNameIsRula(): void
    {
        $this->assertSame('rula', $this->rula->modelName());
    }

    public function testSupportsManualAndVideo(): void
    {
        $types = $this->rula->supportedInputTypes();
        $this->assertContains('manual', $types);
        $this->assertContains('video', $types);
    }

    public function testLowRiskInput(): void
    {
        $metrics = [
            'upper_arm_angle' => 10,
            'lower_arm_angle' => 80,
            'wrist_angle' => 3,
            'neck_angle' => 8,
            'trunk_angle' => 5,
            'leg_score' => 1,
            'load_weight' => 0,
        ];

        $result = $this->rula->calculateScore($metrics);

        foreach (['score', 'risk_level', 'normalized_score', 'risk_category', 'action_level_code', 'action_level_label', 'algorithm_version'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }

        $this->assertLessThanOrEqual(4, $result['score']);
        $this->assertContains($result['risk_category'], ['low', 'moderate']);
        $this->assertSame('rula_official_v1', $result['algorithm_version']);
    }

    public function testHighRiskInput(): void
    {
        $metrics = [
            'upper_arm_angle' => 120,
            'lower_arm_angle' => 40,
            'wrist_angle' => 20,
            'wrist_twist' => true,
            'neck_angle' => 30,
            'trunk_angle' => 70,
            'leg_score' => 2,
            'load_weight' => 15,
            'static_posture' => true,
            'repetitive' => true,
            'trunk_twisted' => true,
        ];

        $result = $this->rula->calculateScore($metrics);

        $this->assertGreaterThanOrEqual(5, $result['score']);
        $this->assertSame('high', $result['risk_category']);
        $this->assertGreaterThanOrEqual(3, $result['action_level_code']);
    }

    public function testScoreIsClampedBetween1And7(): void
    {
        $metrics = [
            'upper_arm_angle' => 15,
            'lower_arm_angle' => 80,
            'wrist_angle' => 5,
            'neck_angle' => 10,
            'trunk_angle' => 10,
        ];

        $result = $this->rula->calculateScore($metrics);
        $this->assertGreaterThanOrEqual(1, $result['score']);
        $this->assertLessThanOrEqual(7, $result['score']);
    }

    public function testValidationThrowsOnMissingField(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RULA requires field');

        $this->rula->validate(['upper_arm_angle' => 20]);
    }

    public function testRiskLevelStrings(): void
    {
        $this->assertStringContainsString('Low', $this->rula->getRiskLevel(1));
        $this->assertStringContainsString('Moderate', $this->rula->getRiskLevel(3));
        $this->assertStringContainsString('High', $this->rula->getRiskLevel(6));
        $this->assertStringContainsString('Very High', $this->rula->getRiskLevel(7));
    }
}