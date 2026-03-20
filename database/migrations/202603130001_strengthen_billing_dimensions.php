<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603130001_strengthen_billing_dimensions';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();

        if ($schemaManager->tablesExist(['plans'])) {
            $planColumns = $schemaManager->listTableColumns('plans');
            if (!isset($planColumns['billing_limits_json'])) {
                $db->executeStatement('ALTER TABLE plans ADD COLUMN billing_limits_json JSON NULL AFTER billing_cycle');
            }

            $defaults = [
                'starter' => json_encode([
                    'video_scan_limit' => 10,
                    'live_session_limit' => 10,
                    'live_session_minutes_limit' => 120,
                    'llm_request_limit' => 25,
                    'llm_token_limit' => 100000,
                    'max_video_retention_days' => 30,
                    'max_org_members' => 5,
                    'max_live_concurrent_sessions' => 1,
                ], JSON_UNESCAPED_UNICODE),
                'professional' => json_encode([
                    'video_scan_limit' => 500,
                    'live_session_limit' => 250,
                    'live_session_minutes_limit' => 3000,
                    'llm_request_limit' => 500,
                    'llm_token_limit' => 2000000,
                    'max_video_retention_days' => 180,
                    'max_org_members' => 50,
                    'max_live_concurrent_sessions' => 4,
                ], JSON_UNESCAPED_UNICODE),
                'enterprise' => json_encode([
                    'video_scan_limit' => null,
                    'live_session_limit' => null,
                    'live_session_minutes_limit' => null,
                    'llm_request_limit' => null,
                    'llm_token_limit' => null,
                    'max_video_retention_days' => 3650,
                    'max_org_members' => null,
                    'max_live_concurrent_sessions' => 12,
                ], JSON_UNESCAPED_UNICODE),
            ];

            foreach ($defaults as $name => $payload) {
                $db->executeStatement(
                    'UPDATE plans
                     SET billing_limits_json = :payload
                     WHERE name = :name
                       AND (billing_limits_json IS NULL OR JSON_TYPE(billing_limits_json) = "NULL")',
                    ['payload' => $payload, 'name' => $name]
                );
            }

            $db->executeStatement(
                'UPDATE plans
                 SET billing_limits_json = JSON_OBJECT(
                    "video_scan_limit", scan_limit,
                    "live_session_limit", NULL,
                    "live_session_minutes_limit", NULL,
                    "llm_request_limit", NULL,
                    "llm_token_limit", NULL,
                    "max_video_retention_days", 30,
                    "max_org_members", NULL,
                    "max_live_concurrent_sessions", NULL
                 )
                 WHERE billing_limits_json IS NULL OR JSON_TYPE(billing_limits_json) = "NULL"'
            );
        }

        if ($schemaManager->tablesExist(['copilot_audit_logs'])) {
            $auditColumns = $schemaManager->listTableColumns('copilot_audit_logs');

            if (!isset($auditColumns['llm_request_count'])) {
                $db->executeStatement('ALTER TABLE copilot_audit_logs ADD COLUMN llm_request_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER llm_status');
            }
            if (!isset($auditColumns['llm_prompt_tokens'])) {
                $db->executeStatement('ALTER TABLE copilot_audit_logs ADD COLUMN llm_prompt_tokens INT UNSIGNED NULL AFTER llm_request_count');
            }
            if (!isset($auditColumns['llm_completion_tokens'])) {
                $db->executeStatement('ALTER TABLE copilot_audit_logs ADD COLUMN llm_completion_tokens INT UNSIGNED NULL AFTER llm_prompt_tokens');
            }
            if (!isset($auditColumns['llm_total_tokens'])) {
                $db->executeStatement('ALTER TABLE copilot_audit_logs ADD COLUMN llm_total_tokens INT UNSIGNED NULL AFTER llm_completion_tokens');
            }
        }
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();

        if ($schemaManager->tablesExist(['copilot_audit_logs'])) {
            $auditColumns = $schemaManager->listTableColumns('copilot_audit_logs');
            if (isset($auditColumns['llm_total_tokens'])) {
                $db->executeStatement('ALTER TABLE copilot_audit_logs DROP COLUMN llm_total_tokens');
            }
            if (isset($auditColumns['llm_completion_tokens'])) {
                $db->executeStatement('ALTER TABLE copilot_audit_logs DROP COLUMN llm_completion_tokens');
            }
            if (isset($auditColumns['llm_prompt_tokens'])) {
                $db->executeStatement('ALTER TABLE copilot_audit_logs DROP COLUMN llm_prompt_tokens');
            }
            if (isset($auditColumns['llm_request_count'])) {
                $db->executeStatement('ALTER TABLE copilot_audit_logs DROP COLUMN llm_request_count');
            }
        }

        if ($schemaManager->tablesExist(['plans'])) {
            $planColumns = $schemaManager->listTableColumns('plans');
            if (isset($planColumns['billing_limits_json'])) {
                $db->executeStatement('ALTER TABLE plans DROP COLUMN billing_limits_json');
            }
        }
    }
};
