<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603240001_add_user_feedback';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['user_feedback'])) {
            return;
        }

        $db->executeStatement(
            'CREATE TABLE user_feedback (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(255)   NULL,
                email       VARCHAR(255)   NULL,
                type        ENUM("improvement","issue","feature","other") NOT NULL DEFAULT "other",
                message     TEXT           NOT NULL,
                status      ENUM("new","reviewed","actioned") NOT NULL DEFAULT "new",
                created_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_feedback_status  (status),
                INDEX idx_feedback_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['user_feedback'])) {
            $db->executeStatement('DROP TABLE user_feedback');
        }
    }
};
