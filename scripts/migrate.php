<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use WorkEddy\Core\Database;

$schemaFile = __DIR__ . '/../database/schema.sql';
if (!file_exists($schemaFile)) {
    throw new RuntimeException('Schema file not found: ' . $schemaFile);
}

$sql = file_get_contents($schemaFile);
if ($sql === false) {
    throw new RuntimeException('Could not read schema.sql');
}

$db = Database::connection();

// Execute each statement individually (DBAL exec splits on semicolons automatically
// but we manually split to handle multi-statement files with comments)
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn(string $s): bool => $s !== ''
);

foreach ($statements as $statement) {
    try {
        $db->executeStatement($statement);
    } catch (Throwable $e) {
        // Ignore "already exists" style errors for idempotency
        if (!str_contains($e->getMessage(), 'already exists') && !str_contains($e->getMessage(), 'Duplicate entry')) {
            throw $e;
        }
    }
}

echo "Migration completed successfully.\n";