<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use WorkEddy\Services\Ergonomics\NioshService;

final class NioshServiceTest extends TestCase
{
    private NioshService $niosh;

    protected function setUp(): void
    {
        $this->niosh = new NioshService();
    }

    public function testModelNameIsNiosh(): void
    {
        $this->assertSame('niosh', $this->niosh->modelName());
    }

    public function testSupportsOnlyManual(): void
    {
        $types = $this->niosh->supportedInputTypes();
        $this->assertContains('manual', $types);
        $this->assertNotContains('video', $types);
    }

    public function testLowRiskLifting(): void
    {
        $metrics = [
            'load_weight'         => 5,
            'horizontal_distance' => 25,
            'vertical_start'      => 75,
            'vertical_travel'     => 25,
            'twist_angle'         => 0,
            'frequency'           => 0.2,
            'coupling'            => 'good',
        ];

        $result = $this->niosh->calculateScore($metrics);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('rwl', $result);
        $this->assertArrayHasKey('lifting_index', $result);
        $this->assertLessThan(1.0, $result['lifting_index']);
        $this->assertSame('low', $result['risk_category']);
    }

    public function testHighRiskLifting(): void
    {
        $metrics = [
            'load_weight'         => 30,
            'horizontal_distance' => 60,
            'vertical_start'      => 10,
            'vertical_travel'     => 80,
            'twist_angle'         => 45,
            'frequency'           => 8,
            'coupling'            => 'poor',
        ];

        $result = $this->niosh->calculateScore($metrics);

        $this->assertGreaterThanOrEqual(3.0, $result['lifting_index']);
        $this->assertSame('high', $result['risk_category']);
    }

    public function testRwlIsPositive(): void
    {
        $metrics = [
            'load_weight'         => 10,
            'horizontal_distance' => 30,
            'vertical_start'      => 50,
            'vertical_travel'     => 30,
            'twist_angle'         => 20,
            'frequency'           => 1,
            'coupling'            => 'fair',
        ];

        $result = $this->niosh->calculateScore($metrics);
        $this->assertGreaterThan(0, $result['rwl']);
    }

    public function testValidationThrowsOnMissingField(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NIOSH requires field');

        $this->niosh->validate(['load_weight' => 10]); // missing others
    }

    public function testRiskLevelStrings(): void
    {
        $this->assertStringContainsString('Low', $this->niosh->getRiskLevel(0.5));
        $this->assertStringContainsString('Moderate', $this->niosh->getRiskLevel(1.5));
        $this->assertStringContainsString('High', $this->niosh->getRiskLevel(3.5));
    }
}
