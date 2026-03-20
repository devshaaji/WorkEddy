<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603120003_add_checkin_type_to_worker_leading_indicators';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['worker_leading_indicators'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('worker_leading_indicators');
        if (array_key_exists('checkin_type', $columns)) {
            return;
        }

        $db->executeStatement(
            'ALTER TABLE worker_leading_indicators
             ADD COLUMN checkin_type ENUM("pre_shift","mid_shift","post_shift") NOT NULL DEFAULT "post_shift" AFTER task_id'
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['worker_leading_indicators'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('worker_leading_indicators');
        if (!array_key_exists('checkin_type', $columns)) {
            return;
        }

        $db->executeStatement('ALTER TABLE worker_leading_indicators DROP COLUMN checkin_type');
    }
};

