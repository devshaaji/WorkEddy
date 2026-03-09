<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603080002_add_scan_results_algorithm_version';
    }

    public function up(Connection $db): void
    {
        $columns = $db->createSchemaManager()->listTableColumns('scan_results');
        if (isset($columns['algorithm_version'])) {
            return;
        }

        $db->executeStatement(
            "ALTER TABLE scan_results ADD algorithm_version VARCHAR(64) NOT NULL DEFAULT 'legacy_v1'"
        );
    }

    public function down(Connection $db): void
    {
        $columns = $db->createSchemaManager()->listTableColumns('scan_results');
        if (!isset($columns['algorithm_version'])) {
            return;
        }

        $db->executeStatement('ALTER TABLE scan_results DROP COLUMN algorithm_version');
    }
};