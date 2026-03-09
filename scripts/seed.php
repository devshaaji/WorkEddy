<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Database;
use WorkEddy\Core\SqlScriptRunner;

const SEEDERS_DIR = __DIR__ . '/../database/seeders';

$command = strtolower((string) ($argv[1] ?? 'run'));
$filter = $argv[2] ?? null;

try {
    $db = Database::connection();
    $seeders = discoverSeeders(SEEDERS_DIR);

    if ($command === 'demo') {
        $command = 'run';
        $filter = 'demo';
    }

    if (is_string($filter) && $filter !== '') {
        $seeders = array_values(array_filter(
            $seeders,
            static fn(array $seeder): bool => stripos($seeder['name'], $filter) !== false
        ));
    }

    switch ($command) {
        case 'run':
            runSeeders($db, $seeders);
            break;

        case 'status':
        case 'list':
            listSeeders($seeders);
            break;

        default:
            usage($command);
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[seed-error] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @return list<array{name: string, path: string, type: string}>
 */
function discoverSeeders(string $directory): array
{
    if (!is_dir($directory)) {
        throw new \RuntimeException('Seeders directory not found: ' . $directory);
    }

    $seeders = [];

    $sqlFiles = glob($directory . '/*.sql');
    if ($sqlFiles === false) {
        throw new \RuntimeException('Could not list SQL seeders in: ' . $directory);
    }

    $phpFiles = glob($directory . '/*.php');
    if ($phpFiles === false) {
        throw new \RuntimeException('Could not list PHP seeders in: ' . $directory);
    }

    foreach ($sqlFiles as $path) {
        $seeders[] = ['name' => basename($path), 'path' => $path, 'type' => 'sql'];
    }

    foreach ($phpFiles as $path) {
        $seeders[] = ['name' => basename($path), 'path' => $path, 'type' => 'php'];
    }

    usort(
        $seeders,
        static fn(array $a, array $b): int => strcmp($a['name'], $b['name'])
    );

    return $seeders;
}

/**
 * @param list<array{name: string, path: string, type: string}> $seeders
 */
function runSeeders(Connection $db, array $seeders): void
{
    if ($seeders === []) {
        echo "No seeders found for the current selection.\n";
        return;
    }

    echo "Running seeders...\n";

    foreach ($seeders as $seeder) {
        echo 'Applying seeder ' . $seeder['name'] . " ...\n";

        if ($seeder['type'] === 'sql') {
            SqlScriptRunner::executeFile($db, $seeder['path']);
        } elseif ($seeder['type'] === 'php') {
            $callable = require $seeder['path'];
            if (!is_callable($callable)) {
                throw new \RuntimeException('Seeder file must return a callable: ' . $seeder['path']);
            }

            $callable($db);
        } else {
            throw new \RuntimeException('Unsupported seeder type: ' . $seeder['type']);
        }

        echo 'Applied seeder ' . $seeder['name'] . "\n";
    }

    echo 'Seeding complete. Applied ' . count($seeders) . " seeder(s).\n";
}

/**
 * @param list<array{name: string, path: string, type: string}> $seeders
 */
function listSeeders(array $seeders): void
{
    if ($seeders === []) {
        echo "No seeders found.\n";
        return;
    }

    printf("%-32s %-6s\n", 'Seeder', 'Type');
    printf("%-32s %-6s\n", str_repeat('-', 32), str_repeat('-', 6));

    foreach ($seeders as $seeder) {
        printf("%-32s %-6s\n", $seeder['name'], $seeder['type']);
    }

    echo 'Total seeders: ' . count($seeders) . "\n";
}

function usage(string $invalidCommand): void
{
    fwrite(STDERR, "Unknown command: {$invalidCommand}\n");
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php scripts/seed.php run [name-filter]\n");
    fwrite(STDERR, "  php scripts/seed.php demo\n");
    fwrite(STDERR, "  php scripts/seed.php status\n");
}

