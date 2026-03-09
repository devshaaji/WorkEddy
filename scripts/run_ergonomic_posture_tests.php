<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Tests\Support\ErgonomicFixtureRunner;
use WorkEddy\Tests\Support\PostureFixtureLoader;

$argv = $_SERVER['argv'] ?? [];
$debug = in_array('--debug', $argv, true) || getenv('ERGONOMICS_DEBUG_TRACE') === '1';
$modelFilter = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--model=')) {
        $modelFilter = strtolower(trim(substr($arg, strlen('--model='))));
    }
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo "Usage: php scripts/run_ergonomic_posture_tests.php [--model=rula|reba] [--debug]\n";
    exit(0);
}

$fixturesDir = __DIR__ . '/../tests/postures';
$cases = PostureFixtureLoader::loadManualCases($fixturesDir);

if ($modelFilter !== null && $modelFilter !== '') {
    $cases = array_values(array_filter(
        $cases,
        static fn(array $case): bool => strtolower((string) ($case['data']['model'] ?? '')) === $modelFilter
    ));
}

if ($cases === []) {
    fwrite(STDERR, "No posture fixtures found for the selected filter.\n");
    exit(1);
}

$runner = new ErgonomicFixtureRunner(new AssessmentEngine());
$result = $runner->run(
    $cases,
    $debug,
    static function (string $line): void {
        echo $line . PHP_EOL;
    }
);

echo str_repeat('-', 72) . PHP_EOL;
printf(
    "Summary: total=%d passed=%d failed=%d\n",
    $result['total'],
    $result['passed'],
    $result['failed']
);

if ($result['failed'] > 0) {
    echo "Mismatches:\n";
    foreach ($result['failures'] as $failure) {
        printf(
            "- %s (expected %.2f, actual %.2f) [%s]\n",
            $failure['name'],
            $failure['expected'],
            $failure['actual'],
            $failure['path']
        );
    }
    exit(1);
}

exit(0);
