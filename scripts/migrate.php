<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Database;
use WorkEddy\Core\Migrations\MigrationInterface;

const MIGRATIONS_DIR = __DIR__ . '/../database/migrations';
const MIGRATIONS_TABLE = 'schema_migrations';
const MIGRATION_LOCK_NAME = 'workeddy_schema_migrations';
const MIGRATION_LOCK_TIMEOUT_SECONDS = 30;

$command = strtolower((string) ($argv[1] ?? 'migrate'));
$argument = $argv[2] ?? null;

try {
    $db = Database::connection();
    ensureMigrationTable($db);

    $migrations = discoverMigrations(MIGRATIONS_DIR);

    switch ($command) {
        case 'migrate':
        case 'up':
            withMigrationLock($db, static function () use ($db, $migrations): void {
                migrate($db, $migrations);
            });
            break;

        case 'rollback':
        case 'down':
            withMigrationLock($db, static function () use ($db, $migrations, $argument): void {
                rollback($db, $migrations, parsePositiveInt($argument ?? '1', 'Rollback batches'));
            });
            break;

        case 'status':
            status($db, $migrations);
            break;

        case 'fresh':
            withMigrationLock($db, static function () use ($db, $migrations): void {
                fresh($db, $migrations);
            });
            break;

        default:
            usage($command);
            exit(1);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, '[migration-error] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

/**
 * @param callable():void $callback
 */
function withMigrationLock(Connection $db, callable $callback): void
{
    $timeout = parsePositiveInt((string) getenv('MIGRATION_LOCK_TIMEOUT_SECONDS') ?: (string) MIGRATION_LOCK_TIMEOUT_SECONDS, 'Migration lock timeout');
    $acquired = (int) $db->fetchOne(
        'SELECT GET_LOCK(:name, :timeout)',
        [
            'name' => MIGRATION_LOCK_NAME,
            'timeout' => $timeout,
        ]
    );

    if ($acquired !== 1) {
        throw new \RuntimeException('Could not acquire migration lock');
    }

    try {
        $callback();
    } finally {
        $db->fetchOne('SELECT RELEASE_LOCK(:name)', ['name' => MIGRATION_LOCK_NAME]);
    }
}

/**
 * @return array<string, array{path: string, migration: MigrationInterface}>
 */
function discoverMigrations(string $directory): array
{
    if (!is_dir($directory)) {
        throw new \RuntimeException('Migrations directory not found: ' . $directory);
    }

    $files = glob($directory . '/*.php');
    if ($files === false) {
        throw new \RuntimeException('Could not read migrations directory: ' . $directory);
    }

    sort($files, SORT_STRING);

    $migrations = [];
    foreach ($files as $filePath) {
        $migration = require $filePath;

        if (!$migration instanceof MigrationInterface) {
            throw new \RuntimeException('Migration file must return MigrationInterface: ' . $filePath);
        }

        $version = pathinfo($filePath, PATHINFO_FILENAME);
        if ($migration->version() !== $version) {
            throw new \RuntimeException(
                'Migration version mismatch for ' . $filePath . '. Expected ' . $version . ', got ' . $migration->version()
            );
        }

        $migrations[$version] = [
            'path' => $filePath,
            'migration' => $migration,
        ];
    }

    return $migrations;
}

function ensureMigrationTable(Connection $db): void
{
    $db->executeStatement(
        'CREATE TABLE IF NOT EXISTS ' . MIGRATIONS_TABLE . ' (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL,
            applied_at DATETIME NOT NULL,
            INDEX idx_schema_migrations_batch (batch)
        )'
    );
}

/**
 * @return array<string, array{batch: int, applied_at: string}>
 */
function loadAppliedMigrationMap(Connection $db): array
{
    $rows = $db->fetchAllAssociative(
        'SELECT version, batch, applied_at FROM ' . MIGRATIONS_TABLE . ' ORDER BY version ASC'
    );

    $applied = [];
    foreach ($rows as $row) {
        $applied[(string) $row['version']] = [
            'batch' => (int) $row['batch'],
            'applied_at' => (string) $row['applied_at'],
        ];
    }

    return $applied;
}

/**
 * @param array<string, array{path: string, migration: MigrationInterface}> $migrations
 */
function migrate(Connection $db, array $migrations): void
{
    if ($migrations === []) {
        echo "No migration files found in database/migrations.\n";
        return;
    }

    $applied = loadAppliedMigrationMap($db);
    $pendingVersions = array_values(array_filter(
        array_keys($migrations),
        static fn(string $version): bool => !isset($applied[$version])
    ));

    if ($pendingVersions === []) {
        echo "No pending migrations.\n";
        return;
    }

    $nextBatch = (int) $db->fetchOne('SELECT COALESCE(MAX(batch), 0) + 1 FROM ' . MIGRATIONS_TABLE);

    foreach ($pendingVersions as $version) {
        echo 'Applying migration ' . $version . " ...\n";

        $migrations[$version]['migration']->up($db);

        $db->executeStatement(
            'INSERT INTO ' . MIGRATIONS_TABLE . ' (version, batch, applied_at) VALUES (:version, :batch, :applied_at)',
            [
                'version' => $version,
                'batch' => $nextBatch,
                'applied_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]
        );

        echo 'Applied migration ' . $version . "\n";
    }

    echo 'Migration complete. Applied ' . count($pendingVersions) . " migration(s).\n";
}

/**
 * @param array<string, array{path: string, migration: MigrationInterface}> $migrations
 */
function rollback(Connection $db, array $migrations, int $batchesToRollback): void
{
    $batchesToRollback = max(1, $batchesToRollback);

    $batchRows = $db->fetchAllAssociative(
        'SELECT DISTINCT batch FROM ' . MIGRATIONS_TABLE . ' ORDER BY batch DESC LIMIT ' . $batchesToRollback
    );

    if ($batchRows === []) {
        echo "No applied migrations to roll back.\n";
        return;
    }

    $batches = array_map(static fn(array $row): int => (int) $row['batch'], $batchRows);
    $batchList = implode(',', $batches);

    $rows = $db->fetchAllAssociative(
        'SELECT version FROM ' . MIGRATIONS_TABLE . ' WHERE batch IN (' . $batchList . ') ORDER BY batch DESC, version DESC'
    );

    foreach ($rows as $row) {
        $version = (string) $row['version'];
        if (!isset($migrations[$version])) {
            throw new \RuntimeException('Migration file missing for rollback version: ' . $version);
        }

        echo 'Rolling back migration ' . $version . " ...\n";

        $migrations[$version]['migration']->down($db);

        $db->executeStatement(
            'DELETE FROM ' . MIGRATIONS_TABLE . ' WHERE version = :version',
            ['version' => $version]
        );

        echo 'Rolled back migration ' . $version . "\n";
    }

    echo 'Rollback complete. Reverted ' . count($rows) . " migration(s).\n";
}

/**
 * @param array<string, array{path: string, migration: MigrationInterface}> $migrations
 */
function status(Connection $db, array $migrations): void
{
    $applied = loadAppliedMigrationMap($db);

    if ($migrations === []) {
        echo "No migration files found in database/migrations.\n";
        return;
    }

    printf("%-32s %-8s %-6s %s\n", 'Version', 'Status', 'Batch', 'Applied At');
    printf("%-32s %-8s %-6s %s\n", str_repeat('-', 32), str_repeat('-', 8), str_repeat('-', 6), str_repeat('-', 19));

    $appliedCount = 0;
    foreach ($migrations as $version => $_migration) {
        if (isset($applied[$version])) {
            $appliedCount++;
            printf(
                "%-32s %-8s %-6d %s\n",
                $version,
                'applied',
                $applied[$version]['batch'],
                $applied[$version]['applied_at']
            );
        } else {
            printf("%-32s %-8s %-6s %s\n", $version, 'pending', '-', '-');
        }
    }

    echo 'Applied: ' . $appliedCount . ', Pending: ' . (count($migrations) - $appliedCount) . "\n";
}

/**
 * @param array<string, array{path: string, migration: MigrationInterface}> $migrations
 */
function fresh(Connection $db, array $migrations): void
{
    $totalBatches = (int) $db->fetchOne('SELECT COUNT(DISTINCT batch) FROM ' . MIGRATIONS_TABLE);
    if ($totalBatches > 0) {
        rollback($db, $migrations, $totalBatches);
    }

    migrate($db, $migrations);
}

function parsePositiveInt(string $raw, string $label): int
{
    if (!preg_match('/^\d+$/', $raw)) {
        throw new \RuntimeException($label . ' must be a positive integer.');
    }

    $value = (int) $raw;
    if ($value < 1) {
        throw new \RuntimeException($label . ' must be greater than zero.');
    }

    return $value;
}

function usage(string $invalidCommand): void
{
    fwrite(STDERR, "Unknown command: {$invalidCommand}\n");
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php scripts/migrate.php migrate\n");
    fwrite(STDERR, "  php scripts/migrate.php rollback [batches]\n");
    fwrite(STDERR, "  php scripts/migrate.php status\n");
    fwrite(STDERR, "  php scripts/migrate.php fresh\n");
}
