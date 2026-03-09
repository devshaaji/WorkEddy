<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603080004_billing_usage_hardening';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();

        if (!$schemaManager->tablesExist(['usage_reservations'])) {
            $db->executeStatement(
                'CREATE TABLE usage_reservations (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    organization_id BIGINT UNSIGNED NOT NULL,
                    scan_id BIGINT UNSIGNED NOT NULL,
                    usage_type VARCHAR(20) NOT NULL,
                    created_at DATETIME NOT NULL,
                    CONSTRAINT fk_usage_res_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                    CONSTRAINT fk_usage_res_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE,
                    UNIQUE KEY uniq_usage_res_org_scan_type (organization_id, scan_id, usage_type),
                    INDEX idx_usage_res_org_created (organization_id, created_at),
                    INDEX idx_usage_res_created (created_at)
                )'
            );
        }

        if (!$schemaManager->tablesExist(['usage_records'])) {
            return;
        }

        // Remove duplicates before adding a uniqueness constraint.
        $db->executeStatement(
            'DELETE ur1
             FROM usage_records ur1
             INNER JOIN usage_records ur2
                ON ur1.organization_id = ur2.organization_id
               AND ur1.scan_id = ur2.scan_id
               AND ur1.usage_type = ur2.usage_type
               AND ur1.id > ur2.id'
        );

        $usageIndexes = $db->createSchemaManager()->listTableIndexes('usage_records');
        if (!isset($usageIndexes['uniq_usage_org_scan_type'])) {
            $db->executeStatement(
                'ALTER TABLE usage_records
                 ADD UNIQUE INDEX uniq_usage_org_scan_type (organization_id, scan_id, usage_type)'
            );
        }

        $usageIndexes = $db->createSchemaManager()->listTableIndexes('usage_records');
        if (!isset($usageIndexes['idx_usage_org_created'])) {
            $db->executeStatement(
                'ALTER TABLE usage_records
                 ADD INDEX idx_usage_org_created (organization_id, created_at)'
            );
        }

        $usageIndexes = $db->createSchemaManager()->listTableIndexes('usage_records');
        if (!isset($usageIndexes['idx_usage_created'])) {
            $db->executeStatement(
                'ALTER TABLE usage_records
                 ADD INDEX idx_usage_created (created_at)'
            );
        }

        // Historical backfill: completed scans with no usage record.
        $db->executeStatement(
            'INSERT INTO usage_records (organization_id, scan_id, usage_type, created_at)
             SELECT
                s.organization_id,
                s.id,
                CASE
                    WHEN s.scan_type = "manual" THEN "manual_scan"
                    ELSE "video_scan"
                END AS usage_type,
                COALESCE(sr.first_result_at, s.created_at, NOW())
             FROM scans s
             LEFT JOIN (
                SELECT scan_id, MIN(created_at) AS first_result_at
                FROM scan_results
                GROUP BY scan_id
             ) sr ON sr.scan_id = s.id
             LEFT JOIN usage_records ur
                ON ur.organization_id = s.organization_id
               AND ur.scan_id = s.id
               AND ur.usage_type = CASE
                    WHEN s.scan_type = "manual" THEN "manual_scan"
                    ELSE "video_scan"
               END
             WHERE s.status = "completed"
               AND s.scan_type IN ("manual", "video")
               AND ur.id IS NULL'
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();

        if ($schemaManager->tablesExist(['usage_reservations'])) {
            $db->executeStatement('DROP TABLE usage_reservations');
        }

        if (!$schemaManager->tablesExist(['usage_records'])) {
            return;
        }

        $indexes = $db->createSchemaManager()->listTableIndexes('usage_records');
        if (isset($indexes['idx_usage_created'])) {
            $db->executeStatement('ALTER TABLE usage_records DROP INDEX idx_usage_created');
        }

        $indexes = $db->createSchemaManager()->listTableIndexes('usage_records');
        if (isset($indexes['idx_usage_org_created'])) {
            $db->executeStatement('ALTER TABLE usage_records DROP INDEX idx_usage_org_created');
        }

        $indexes = $db->createSchemaManager()->listTableIndexes('usage_records');
        if (isset($indexes['uniq_usage_org_scan_type'])) {
            $db->executeStatement('ALTER TABLE usage_records DROP INDEX uniq_usage_org_scan_type');
        }
    }
};
