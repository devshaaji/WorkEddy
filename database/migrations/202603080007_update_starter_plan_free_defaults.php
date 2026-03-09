<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603080007_update_starter_plan_free_defaults';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['plans'])) {
            return;
        }

        $starterId = $db->fetchOne("SELECT id FROM plans WHERE name = 'starter' LIMIT 1");

        if ($starterId === false || $starterId === null) {
            $db->executeStatement(
                "INSERT INTO plans (name, scan_limit, price, billing_cycle, status, created_at)
                 VALUES ('starter', 10, 0.00, 'monthly', 'active', NOW())"
            );
            return;
        }

        $db->executeStatement(
            "UPDATE plans
             SET scan_limit = 10,
                 price = 0.00,
                 billing_cycle = 'monthly',
                 status = 'active'
             WHERE id = :id",
            ['id' => (int) $starterId]
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['plans'])) {
            return;
        }

        $db->executeStatement(
            "UPDATE plans
             SET scan_limit = 100,
                 price = 99.00,
                 billing_cycle = 'monthly',
                 status = 'active'
             WHERE name = 'starter'"
        );
    }
};