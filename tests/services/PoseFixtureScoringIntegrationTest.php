<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Tests\Support\PostureFixtureLoader;

final class PoseFixtureScoringIntegrationTest extends TestCase
{
    /**
     * @return array<string, array{path: string, data: array<string, mixed>}>
     */
    public static function poseCasesProvider(): array
    {
        $cases = PostureFixtureLoader::loadPoseCases(__DIR__ . '/../postures');

        $out = [];
        foreach ($cases as $case) {
            $name = (string) ($case['data']['name'] ?? basename($case['path']));
            $out[$name] = [$case];
        }

        return $out;
    }

    #[DataProvider('poseCasesProvider')]
    public function testPoseFixtureExpectedMetricsProduceExpectedScore(array $fixture): void
    {
        $data = $fixture['data'];
        $model = (string) ($data['model'] ?? '');
        $metrics = is_array($data['expected_metrics'] ?? null) ? $data['expected_metrics'] : [];

        $this->assertNotSame('', $model, 'Pose fixture missing model');
        $this->assertNotEmpty($metrics, 'Pose fixture missing expected_metrics');

        $engine = new AssessmentEngine();
        $result = $engine->assess($model, $metrics);

        if (array_key_exists('expected_score', $data)) {
            $this->assertSame((float) $data['expected_score'], (float) $result['score']);
        }
        if (array_key_exists('expected_risk_category', $data)) {
            $this->assertSame((string) $data['expected_risk_category'], (string) $result['risk_category']);
        }
        if (array_key_exists('expected_normalized_score', $data)) {
            $this->assertSame((float) $data['expected_normalized_score'], (float) $result['normalized_score']);
        }
        if (array_key_exists('expected_action_level_code', $data)) {
            $this->assertSame((int) $data['expected_action_level_code'], (int) ($result['action_level_code'] ?? -1));
        }
        if (array_key_exists('expected_algorithm_version', $data)) {
            $this->assertSame((string) $data['expected_algorithm_version'], (string) ($result['algorithm_version'] ?? ''));
        }
    }
}