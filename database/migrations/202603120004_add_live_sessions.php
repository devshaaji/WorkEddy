<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603120004_add_live_sessions';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();

        if (!$schemaManager->tablesExist(['live_sessions'])) {
            $db->executeStatement(
                'CREATE TABLE live_sessions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    organization_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    task_id BIGINT UNSIGNED NOT NULL,
                    model ENUM("rula","reba") NOT NULL DEFAULT "reba",
                    pose_engine ENUM("mediapipe","yolo26") NOT NULL DEFAULT "yolo26",
                    status ENUM("active","paused","completed","failed") NOT NULL DEFAULT "active",
                    target_fps DECIMAL(5,2) NOT NULL DEFAULT 5.00,
                    batch_window_ms INT UNSIGNED NOT NULL DEFAULT 500,
                    max_e2e_latency_ms INT UNSIGNED NOT NULL DEFAULT 2000,
                    frame_count INT UNSIGNED NOT NULL DEFAULT 0,
                    analysed_frame_count INT UNSIGNED NOT NULL DEFAULT 0,
                    avg_latency_ms DECIMAL(10,2) NULL,
                    summary_metrics_json JSON NULL,
                    error_message TEXT NULL,
                    started_at DATETIME NOT NULL,
                    completed_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NULL,
                    CONSTRAINT fk_live_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                    CONSTRAINT fk_live_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT fk_live_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                    INDEX idx_live_org_status (organization_id, status),
                    INDEX idx_live_org_created (organization_id, created_at)
                )'
            );
        }

        if (!$schemaManager->tablesExist(['live_session_frames'])) {
            $db->executeStatement(
                'CREATE TABLE live_session_frames (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    session_id BIGINT UNSIGNED NOT NULL,
                    frame_number INT UNSIGNED NOT NULL,
                    metrics_json JSON NOT NULL,
                    trunk_angle DECIMAL(10,2) NULL,
                    neck_angle DECIMAL(10,2) NULL,
                    upper_arm_angle DECIMAL(10,2) NULL,
                    lower_arm_angle DECIMAL(10,2) NULL,
                    wrist_angle DECIMAL(10,2) NULL,
                    confidence DECIMAL(5,4) NULL,
                    latency_ms DECIMAL(10,2) NULL,
                    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                    CONSTRAINT fk_lsf_session FOREIGN KEY (session_id) REFERENCES live_sessions(id) ON DELETE CASCADE,
                    INDEX idx_lsf_session_frame (session_id, frame_number)
                )'
            );
        }
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['live_session_frames'])) {
            $db->executeStatement('DROP TABLE live_session_frames');
        }
        if ($schemaManager->tablesExist(['live_sessions'])) {
            $db->executeStatement('DROP TABLE live_sessions');
        }
    }
};
