<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603120002_add_control_actions';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['control_actions'])) {
            return;
        }

        $db->executeStatement(
            'CREATE TABLE control_actions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                source_scan_id BIGINT UNSIGNED NOT NULL,
                source_control_id BIGINT UNSIGNED NULL,
                control_code VARCHAR(120) NOT NULL,
                control_title VARCHAR(255) NOT NULL,
                hierarchy_level ENUM("elimination","substitution","engineering","administrative","ppe") NOT NULL,
                control_type ENUM("permanent","interim") NOT NULL DEFAULT "permanent",
                assigned_to_user_id BIGINT UNSIGNED NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                status ENUM("planned","in_progress","implemented","verified","cancelled") NOT NULL DEFAULT "planned",
                priority ENUM("low","medium","high") NOT NULL DEFAULT "medium",
                target_due_date DATE NULL,
                implementation_notes TEXT NULL,
                worker_feedback_json JSON NULL,
                verification_scan_id BIGINT UNSIGNED NULL,
                verification_summary_json JSON NULL,
                implemented_at DATETIME NULL,
                verified_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                CONSTRAINT fk_action_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                CONSTRAINT fk_action_scan FOREIGN KEY (source_scan_id) REFERENCES scans(id) ON DELETE CASCADE,
                CONSTRAINT fk_action_control FOREIGN KEY (source_control_id) REFERENCES scan_control_recommendations(id) ON DELETE SET NULL,
                CONSTRAINT fk_action_assignee FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_action_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_action_verification_scan FOREIGN KEY (verification_scan_id) REFERENCES scans(id) ON DELETE SET NULL,
                INDEX idx_action_org_status (organization_id, status, created_at),
                INDEX idx_action_org_scan (organization_id, source_scan_id),
                INDEX idx_action_assignee (assigned_to_user_id, status)
            )'
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['control_actions'])) {
            $db->executeStatement('DROP TABLE control_actions');
        }
    }
};

