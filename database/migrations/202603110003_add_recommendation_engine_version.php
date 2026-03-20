<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603110003_add_recommendation_engine_version';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['scan_control_recommendations'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('scan_control_recommendations');
        if (array_key_exists('recommendation_engine_version', $columns)) {
            return;
        }

        $db->executeStatement(
            'ALTER TABLE scan_control_recommendations
             ADD COLUMN recommendation_engine_version VARCHAR(64) NOT NULL DEFAULT "ctrl_rec_v1" AFTER evidence_json'
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['scan_control_recommendations'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('scan_control_recommendations');
        if (!array_key_exists('recommendation_engine_version', $columns)) {
            return;
        }

        $db->executeStatement('ALTER TABLE scan_control_recommendations DROP COLUMN recommendation_engine_version');
    }
};
