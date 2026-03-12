<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603120001_add_osha_metadata_to_scan_control_recommendations';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['scan_control_recommendations'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('scan_control_recommendations');

        if (!array_key_exists('control_type', $columns)) {
            $db->executeStatement(
                'ALTER TABLE scan_control_recommendations
                 ADD COLUMN control_type ENUM("permanent","interim") NOT NULL DEFAULT "permanent" AFTER throughput_impact'
            );
        }

        if (!array_key_exists('feasibility_score', $columns)) {
            $db->executeStatement(
                'ALTER TABLE scan_control_recommendations
                 ADD COLUMN feasibility_score DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER control_type'
            );
        }

        if (!array_key_exists('feasibility_status', $columns)) {
            $db->executeStatement(
                'ALTER TABLE scan_control_recommendations
                 ADD COLUMN feasibility_status ENUM("feasible","conditional","not_feasible") NOT NULL DEFAULT "conditional" AFTER feasibility_score'
            );
        }

        if (!array_key_exists('interim_for_control_code', $columns)) {
            $db->executeStatement(
                'ALTER TABLE scan_control_recommendations
                 ADD COLUMN interim_for_control_code VARCHAR(120) NULL AFTER feasibility_status'
            );
        }

        $indexes = $schemaManager->listTableIndexes('scan_control_recommendations');
        if (!array_key_exists('idx_ctrl_feasibility', $indexes)) {
            $db->executeStatement(
                'CREATE INDEX idx_ctrl_feasibility ON scan_control_recommendations (feasibility_status)'
            );
        }
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['scan_control_recommendations'])) {
            return;
        }

        $indexes = $schemaManager->listTableIndexes('scan_control_recommendations');
        if (array_key_exists('idx_ctrl_feasibility', $indexes)) {
            $db->executeStatement('DROP INDEX idx_ctrl_feasibility ON scan_control_recommendations');
        }

        $columns = $schemaManager->listTableColumns('scan_control_recommendations');
        if (array_key_exists('interim_for_control_code', $columns)) {
            $db->executeStatement('ALTER TABLE scan_control_recommendations DROP COLUMN interim_for_control_code');
        }
        if (array_key_exists('feasibility_status', $columns)) {
            $db->executeStatement('ALTER TABLE scan_control_recommendations DROP COLUMN feasibility_status');
        }
        if (array_key_exists('feasibility_score', $columns)) {
            $db->executeStatement('ALTER TABLE scan_control_recommendations DROP COLUMN feasibility_score');
        }
        if (array_key_exists('control_type', $columns)) {
            $db->executeStatement('ALTER TABLE scan_control_recommendations DROP COLUMN control_type');
        }
    }
};

