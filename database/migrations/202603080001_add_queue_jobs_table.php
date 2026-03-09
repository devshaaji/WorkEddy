<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603080001_add_queue_jobs_table';
    }

    public function up(Connection $db): void
    {
        $db->executeStatement(
            'CREATE TABLE IF NOT EXISTS queue_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue_name VARCHAR(100) NOT NULL,
                payload JSON NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_queue_jobs_queue_id (queue_name, id)
            )'
        );
    }

    public function down(Connection $db): void
    {
        $db->executeStatement('DROP TABLE IF EXISTS queue_jobs');
    }
};