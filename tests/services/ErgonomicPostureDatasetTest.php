<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Tests\Support\ErgonomicFixtureRunner;
use WorkEddy\Tests\Support\PostureFixtureLoader;

final class ErgonomicPostureDatasetTest extends TestCase
{
    /**
     * @return array<string, array{path: string, data: array<string, mixed>}>
     */
    public static function manualCasesProvider(): array
    {
        $cases = PostureFixtureLoader::loadManualCases(__DIR__ . '/../postures');

        $out = [];
        foreach ($cases as $case) {
            $name = (string) ($case['data']['name'] ?? basename($case['path']));
            $out[$name] = [$case];
        }

        return $out;
    }

    #[DataProvider('manualCasesProvider')]
    public function testManualFixtureCaseProducesExpectedScore(array $fixture): void
    {
        $engine = new AssessmentEngine();

        $model = (string) $fixture['data']['model'];
        $inputs = (array) $fixture['data']['inputs'];
        $expectedScore = (float) $fixture['data']['expected_score'];

        $result = $engine->assess($model, $inputs);

        $this->assertSame(
            $expectedScore,
            (float) $result['score'],
            sprintf(
                'Fixture mismatch for %s (%s). Expected %.2f, got %.2f',
                $fixture['data']['name'] ?? basename($fixture['path']),
                $fixture['path'],
                $expectedScore,
                (float) $result['score']
            )
        );

        if (array_key_exists('expected_raw_score', $fixture['data'])) {
            $this->assertSame((float) $fixture['data']['expected_raw_score'], (float) $result['raw_score']);
        }
        if (array_key_exists('expected_normalized_score', $fixture['data'])) {
            $this->assertSame((float) $fixture['data']['expected_normalized_score'], (float) $result['normalized_score']);
        }
        if (array_key_exists('expected_risk_level', $fixture['data'])) {
            $this->assertSame((string) $fixture['data']['expected_risk_level'], (string) $result['risk_level']);
        }
        if (array_key_exists('expected_risk_category', $fixture['data'])) {
            $this->assertSame((string) $fixture['data']['expected_risk_category'], (string) $result['risk_category']);
        }
        if (array_key_exists('expected_action_level_code', $fixture['data'])) {
            $this->assertSame((int) $fixture['data']['expected_action_level_code'], (int) ($result['action_level_code'] ?? -1));
        }
        if (array_key_exists('expected_algorithm_version', $fixture['data'])) {
            $this->assertSame((string) $fixture['data']['expected_algorithm_version'], (string) ($result['algorithm_version'] ?? ''));
        }

        $this->assertArrayHasKey('risk_level', $result);
        $this->assertArrayHasKey('normalized_score', $result);
        $this->assertArrayHasKey('risk_category', $result);
        $this->assertArrayHasKey('recommendation', $result);
        $this->assertArrayHasKey('action_level_code', $result);
        $this->assertArrayHasKey('action_level_label', $result);
        $this->assertArrayHasKey('algorithm_version', $result);
    }

    public function testRunnerReportsNoMismatchesAcrossManualFixtures(): void
    {
        $runner = new ErgonomicFixtureRunner(new AssessmentEngine());
        $cases = PostureFixtureLoader::loadManualCases(__DIR__ . '/../postures');

        $summary = $runner->run($cases, false, null);

        $this->assertSame(0, $summary['failed'], 'Fixture runner detected mismatches.');
        $this->assertSame($summary['total'], $summary['passed']);
    }
}