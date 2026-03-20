<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603070002_add_organization_settings';
    }

    public function up(Connection $db): void
    {
        $this->syncSettingsColumn($db, true);
    }

    public function down(Connection $db): void
    {
        $this->syncSettingsColumn($db, false);
    }

    private function syncSettingsColumn(Connection $db, bool $shouldExist): void
    {
        $schemaManager = $db->createSchemaManager();
        if (!$schemaManager->tablesExist(['organizations'])) {
            throw new \RuntimeException('Table organizations does not exist. Run the initial migration first.');
        }

        $currentTable = $schemaManager->introspectTable('organizations');
        $hasColumn = $currentTable->hasColumn('settings');

        if ($hasColumn === $shouldExist) {
            return;
        }

        $targetTable = clone $currentTable;
        if ($shouldExist) {
            $targetTable->addColumn('settings', 'json', ['notnull' => false]);
        } else {
            $targetTable->dropColumn('settings');
        }

        $comparator = $schemaManager->createComparator();
        $tableDiff = $comparator->compareTables($currentTable, $targetTable);
        foreach ($db->getDatabasePlatform()->getAlterTableSQL($tableDiff) as $statement) {
            $db->executeStatement($statement);
        }
    }
};

