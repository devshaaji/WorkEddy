<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603110002_add_worker_leading_indicators';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();

        if ($schemaManager->tablesExist(['worker_leading_indicators'])) {
            return;
        }

        $db->executeStatement(
            'CREATE TABLE worker_leading_indicators (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                task_id BIGINT UNSIGNED NULL,
                checkin_type ENUM("pre_shift","mid_shift","post_shift") NOT NULL DEFAULT "post_shift",
                shift_date DATE NOT NULL,
                discomfort_level TINYINT UNSIGNED NOT NULL,
                fatigue_level TINYINT UNSIGNED NOT NULL,
                micro_breaks_taken SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                recovery_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                overtime_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                task_rotation_quality ENUM("poor","fair","good") NOT NULL DEFAULT "fair",
                psychosocial_load ENUM("low","moderate","high") NOT NULL DEFAULT "moderate",
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_wli_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                CONSTRAINT fk_wli_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_wli_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
                INDEX idx_wli_org_shift (organization_id, shift_date),
                INDEX idx_wli_org_created (organization_id, created_at)
            )'
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['worker_leading_indicators'])) {
            $db->executeStatement('DROP TABLE worker_leading_indicators');
        }
    }
};
