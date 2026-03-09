<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603080006_add_super_admin_role';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['users'])) {
            return;
        }

        $superAdminCount = (int) $db->fetchOne(
            "SELECT COUNT(*) FROM users WHERE role = 'super_admin'"
        );

        if ($superAdminCount > 0) {
            return;
        }

        $firstAdminId = $db->fetchOne(
            "SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1"
        );

        if ($firstAdminId === false || $firstAdminId === null) {
            return;
        }

        $db->executeStatement(
            "UPDATE users SET role = 'super_admin' WHERE id = :id",
            ['id' => (int) $firstAdminId]
        );
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['users'])) {
            return;
        }

        $db->executeStatement(
            "UPDATE users SET role = 'admin' WHERE role = 'super_admin'"
        );
    }
};