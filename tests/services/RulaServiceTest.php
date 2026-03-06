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
            'wrist_angle'     => 3,
            'neck_angle'      => 8,
            'trunk_angle'     => 5,
            'leg_score'       => 1,
            'load_weight'     => 0,
        ];

        $result = $this->rula->calculateScore($metrics);

        $this->assertArrayHasKey('score', $result);
        $this->assertLessThanOrEqual(4, $result['score']);
        $this->assertContains($result['risk_category'], ['low', 'moderate']);
    }

    public function testHighRiskInput(): void
    {
        $metrics = [
            'upper_arm_angle' => 120,
            'lower_arm_angle' => 40,
            'wrist_angle'     => 20,
            'wrist_twist'     => true,
            'neck_angle'      => 30,
            'trunk_angle'     => 70,
            'leg_score'       => 2,
            'load_weight'     => 15,
            'static_posture'  => true,
            'repetitive'      => true,
        ];

        $result = $this->rula->calculateScore($metrics);

        $this->assertGreaterThanOrEqual(5, $result['score']);
        $this->assertSame('high', $result['risk_category']);
    }

    public function testScoreIsClampedBetween1And7(): void
    {
        $metrics = [
            'upper_arm_angle' => 15,
            'lower_arm_angle' => 80,
            'wrist_angle'     => 5,
            'neck_angle'      => 10,
            'trunk_angle'     => 10,
        ];

        $result = $this->rula->calculateScore($metrics);
        $this->assertGreaterThanOrEqual(1, $result['score']);
        $this->assertLessThanOrEqual(7, $result['score']);
    }

    public function testValidationThrowsOnMissingField(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RULA requires field');

        $this->rula->validate(['upper_arm_angle' => 20]); // missing others
    }

    public function testRiskLevelStrings(): void
    {
        $this->assertStringContainsString('Low', $this->rula->getRiskLevel(1));
        $this->assertStringContainsString('Moderate', $this->rula->getRiskLevel(3));
        $this->assertStringContainsString('High', $this->rula->getRiskLevel(6));
        $this->assertStringContainsString('Very High', $this->rula->getRiskLevel(7));
    }
}
