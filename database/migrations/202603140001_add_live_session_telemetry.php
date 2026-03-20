<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603140001_add_live_session_telemetry';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['live_sessions'])) {
            return;
        }

        $columns = array_map(
            static fn ($column): string => strtolower($column->getName()),
            $schemaManager->listTableColumns('live_sessions')
        );

        if (!in_array('telemetry_json', $columns, true)) {
            $db->executeStatement('ALTER TABLE live_sessions ADD telemetry_json JSON NULL AFTER summary_metrics_json');
        }
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['live_sessions'])) {
            return;
        }

        $columns = array_map(
            static fn ($column): string => strtolower($column->getName()),
            $schemaManager->listTableColumns('live_sessions')
        );

        if (in_array('telemetry_json', $columns, true)) {
            $db->executeStatement('ALTER TABLE live_sessions DROP COLUMN telemetry_json');
        }
    }
};
