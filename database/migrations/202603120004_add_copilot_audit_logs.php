<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603120004_add_copilot_audit_logs';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['copilot_audit_logs'])) {
            return;
        }

        $db->executeStatement(
            'CREATE TABLE copilot_audit_logs (
                id CHAR(36) PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                persona VARCHAR(50) NOT NULL,
                request_payload_redacted JSON NULL,
                deterministic_bundle_redacted JSON NULL,
                llm_prompt_redacted JSON NULL,
                llm_response_redacted JSON NULL,
                response_payload_redacted JSON NULL,
                llm_status ENUM("success","fallback","disabled") NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_copilot_audit_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                CONSTRAINT fk_copilot_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_copilot_audit_org_created (organization_id, created_at),
                INDEX idx_copilot_audit_user_created (user_id, created_at),
                INDEX idx_copilot_audit_persona_created (persona, created_at)
            )'
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if ($schemaManager->tablesExist(['copilot_audit_logs'])) {
            $db->executeStatement('DROP TABLE copilot_audit_logs');
        }
    }
};

