<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use WorkEddy\Api\Config\Database;

$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Could not read schema.sql');
}

$db = Database::connection();
$db->exec($schema);

echo "Migration completed\n";
