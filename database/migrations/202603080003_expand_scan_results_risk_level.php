<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603080003_expand_scan_results_risk_level';
    }

    public function up(Connection $db): void
    {
        $columns = $db->createSchemaManager()->listTableColumns('scan_results');
        if (!isset($columns['risk_level'])) {
            return;
        }

        $db->executeStatement('ALTER TABLE scan_results MODIFY risk_level VARCHAR(191) NOT NULL');
    }

    public function down(Connection $db): void
    {
        $columns = $db->createSchemaManager()->listTableColumns('scan_results');
        if (!isset($columns['risk_level'])) {
            return;
        }

        $db->executeStatement('ALTER TABLE scan_results MODIFY risk_level VARCHAR(50) NOT NULL');
    }
};