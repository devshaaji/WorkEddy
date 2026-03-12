<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603110001_add_scan_control_recommendations';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();

        if ($schemaManager->tablesExist(['scan_control_recommendations'])) {
            return;
        }

        $db->executeStatement(
            'CREATE TABLE scan_control_recommendations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                scan_id BIGINT UNSIGNED NOT NULL,
                rank_order TINYINT UNSIGNED NOT NULL,
                hierarchy_level ENUM("elimination","substitution","engineering","administrative","ppe") NOT NULL,
                control_code VARCHAR(120) NOT NULL,
                title VARCHAR(255) NOT NULL,
                expected_risk_reduction_pct DECIMAL(5,2) NOT NULL,
                implementation_cost ENUM("low","medium","high") NOT NULL,
                time_to_deploy_days INT UNSIGNED NOT NULL,
                throughput_impact ENUM("low","medium","high") NOT NULL,
                control_type ENUM("permanent","interim") NOT NULL DEFAULT "permanent",
                feasibility_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                feasibility_status ENUM("feasible","conditional","not_feasible") NOT NULL DEFAULT "conditional",
                interim_for_control_code VARCHAR(120) NULL,
                rationale TEXT NOT NULL,
                evidence_json JSON NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_ctrl_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE,
                UNIQUE KEY uniq_ctrl_scan_rank (scan_id, rank_order),
                UNIQUE KEY uniq_ctrl_scan_code (scan_id, control_code),
                INDEX idx_ctrl_scan (scan_id),
                INDEX idx_ctrl_hierarchy (hierarchy_level),
                INDEX idx_ctrl_feasibility (feasibility_status)
            )'
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['scan_control_recommendations'])) {
            $db->executeStatement('DROP TABLE scan_control_recommendations');
        }
    }
};
