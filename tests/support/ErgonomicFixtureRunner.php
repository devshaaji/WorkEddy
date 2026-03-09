<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Support;

use WorkEddy\Services\Ergonomics\AssessmentEngine;

final class ErgonomicFixtureRunner
{
    public function __construct(private readonly AssessmentEngine $engine) {}

    /**
     * @param list<array{path: string, data: array<string, mixed>}> $cases
     * @param callable(string): void|null $printer
     * @return array{total: int, passed: int, failed: int, failures: list<array{name: string, expected: float, actual: float, path: string}>}
     */
    public function run(array $cases, bool $debug = false, ?callable $printer = null): array
    {
        $total = 0;
        $passed = 0;
        $failed = 0;
        $failures = [];

        foreach ($cases as $case) {
            $total++;

            $name = (string) ($case['data']['name'] ?? basename($case['path']));
            $model = (string) ($case['data']['model'] ?? '');
            $inputs = is_array($case['data']['inputs'] ?? null) ? $case['data']['inputs'] : [];

            $expected = (float) ($case['data']['expected_score'] ?? 0.0);
            $result = $this->engine->assess($model, $inputs);
            $actual = (float) ($result['score'] ?? 0.0);

            $ok = abs($expected - $actual) < 0.0001;

            if ($ok) {
                $passed++;
                $status = 'PASS';
            } else {
                $failed++;
                $status = 'FAIL';
                $failures[] = [
                    'name' => $name,
                    'expected' => $expected,
                    'actual' => $actual,
                    'path' => $case['path'],
                ];
            }

            if ($printer !== null) {
                $printer(sprintf(
                    '[%s] %s | expected=%.2f actual=%.2f',
                    $status,
                    $name,
                    $expected,
                    $actual
                ));

                if ($debug) {
                    foreach (ErgonomicDebugTracer::trace($model, $inputs, $result) as $line) {
                        $printer('  ' . $line);
                    }
                }
            }
        }

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'failures' => $failures,
        ];
    }
}
