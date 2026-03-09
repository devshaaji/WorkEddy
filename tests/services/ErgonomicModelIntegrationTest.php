<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WorkEddy\Services\Ergonomics\AssessmentEngine;

final class ErgonomicModelIntegrationTest extends TestCase
{
    private AssessmentEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new AssessmentEngine();
    }

    public function testAssessmentEngineReturnsUnifiedOutputContractForRula(): void
    {
        $result = $this->engine->assess('rula', [
            'upper_arm_angle' => 35,
            'lower_arm_angle' => 80,
            'wrist_angle' => 10,
            'neck_angle' => 12,
            'trunk_angle' => 15,
            'leg_score' => 1,
            'load_weight' => 5,
        ]);

        foreach (['score', 'risk_level', 'recommendation', 'raw_score', 'normalized_score', 'risk_category', 'action_level_code', 'action_level_label', 'algorithm_version'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }

        $this->assertGreaterThanOrEqual(1, $result['score']);
        $this->assertLessThanOrEqual(7, $result['score']);
        $this->assertSame('rula_official_v1', $result['algorithm_version']);
    }

    public function testAssessmentEngineReturnsUnifiedOutputContractForReba(): void
    {
        $result = $this->engine->assess('reba', [
            'trunk_angle' => 30,
            'neck_angle' => 18,
            'upper_arm_angle' => 50,
            'lower_arm_angle' => 90,
            'wrist_angle' => 12,
            'leg_score' => 2,
            'load_weight' => 8,
            'coupling' => 'fair',
        ]);

        foreach (['score', 'risk_level', 'recommendation', 'raw_score', 'normalized_score', 'risk_category', 'action_level_code', 'action_level_label', 'algorithm_version'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }

        $this->assertGreaterThanOrEqual(1, $result['score']);
        $this->assertLessThanOrEqual(15, $result['score']);
        $this->assertSame('reba_official_v1', $result['algorithm_version']);
    }

    public function testNioshProvidesRwlAndLiftingIndex(): void
    {
        $result = $this->engine->assess('niosh', [
            'load_weight' => 10,
            'horizontal_distance' => 30,
            'vertical_start' => 75,
            'vertical_travel' => 30,
            'twist_angle' => 10,
            'frequency' => 1,
            'coupling' => 'fair',
        ]);

        $this->assertArrayHasKey('rwl', $result);
        $this->assertArrayHasKey('lifting_index', $result);
        $this->assertArrayHasKey('algorithm_version', $result);
        $this->assertSame($result['score'], $result['lifting_index']);
    }

    public function testEngineRejectsUnsupportedModelAndInputTypeCombination(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Model 'niosh' does not support input type 'video'");

        $this->engine->validateCombination('niosh', 'video');
    }

    public function testModelDescriptorsExposeExpectedFieldsForUiAndValidation(): void
    {
        $descriptors = $this->engine->modelDescriptors();
        $this->assertNotEmpty($descriptors);

        foreach ($descriptors as $descriptor) {
            $this->assertArrayHasKey('value', $descriptor);
            $this->assertArrayHasKey('label', $descriptor);
            $this->assertArrayHasKey('desc', $descriptor);
            $this->assertArrayHasKey('input_types', $descriptor);
            $this->assertArrayHasKey('fields', $descriptor);
        }
    }
}