<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603070001_initial_schema';
    }

    public function up(Connection $db): void
    {
        $schema = $this->buildSchema();
        $platform = $db->getDatabasePlatform();

        $existingNames = array_map(
            static fn(string $name): string => strtolower($name),
            $db->createSchemaManager()->listTableNames()
        );
        $existingLookup = array_flip($existingNames);

        foreach ($this->tableOrder() as $tableName) {
            if (isset($existingLookup[strtolower($tableName)])) {
                continue;
            }

            $table = $schema->getTable($tableName);
            foreach ($platform->getCreateTableSQL($table) as $statement) {
                $db->executeStatement($statement);
            }
        }
    }

    public function down(Connection $db): void
    {
        $platform = $db->getDatabasePlatform();

        $existingNames = array_map(
            static fn(string $name): string => strtolower($name),
            $db->createSchemaManager()->listTableNames()
        );
        $existingLookup = array_flip($existingNames);

        foreach (array_reverse($this->tableOrder()) as $tableName) {
            if (!isset($existingLookup[strtolower($tableName)])) {
                continue;
            }

            $db->executeStatement($platform->getDropTableSQL($tableName));
        }
    }

    private function buildSchema(): Schema
    {
        $schema = new Schema();

        $organizations = $schema->createTable('organizations');
        $this->addId($organizations);
        $organizations->addColumn('name', 'string', ['length' => 255]);
        $organizations->addColumn('slug', 'string', ['length' => 255, 'notnull' => false]);
        $organizations->addColumn('contact_email', 'string', ['length' => 255, 'notnull' => false]);
        $organizations->addColumn('plan', 'string', ['length' => 100, 'default' => 'starter']);
        $organizations->addColumn('settings', 'json', ['notnull' => false]);
        $organizations->addColumn('status', 'string', ['length' => 20, 'default' => 'active']);
        $organizations->addColumn('created_at', 'datetime');
        $organizations->addColumn('updated_at', 'datetime', ['notnull' => false]);

        $users = $schema->createTable('users');
        $this->addId($users);
        $users->addColumn('organization_id', 'bigint', ['unsigned' => true]);
        $users->addColumn('name', 'string', ['length' => 255]);
        $users->addColumn('email', 'string', ['length' => 255]);
        $users->addColumn('password_hash', 'string', ['length' => 255]);
        $users->addColumn('role', 'string', ['length' => 30]);
        $users->addColumn('status', 'string', ['length' => 20, 'default' => 'active']);
        $users->addColumn('email_verified', 'boolean', ['default' => false]);
        $users->addColumn('email_otp', 'string', ['length' => 10, 'notnull' => false]);
        $users->addColumn('email_otp_expires_at', 'datetime', ['notnull' => false]);
        $users->addColumn('two_factor_enabled', 'boolean', ['default' => false]);
        $users->addColumn('two_factor_secret', 'string', ['length' => 255, 'notnull' => false]);
        $users->addColumn('created_at', 'datetime');
        $users->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $users->addUniqueIndex(['email'], 'uniq_users_email');
        $users->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_users_org');

        $plans = $schema->createTable('plans');
        $this->addId($plans);
        $plans->addColumn('name', 'string', ['length' => 100]);
        $plans->addColumn('scan_limit', 'integer', ['notnull' => false]);
        $plans->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $plans->addColumn('billing_cycle', 'string', ['length' => 50, 'default' => 'monthly']);
        $plans->addColumn('status', 'string', ['length' => 20, 'default' => 'active']);

        $subscriptions = $schema->createTable('subscriptions');
        $this->addId($subscriptions);
        $subscriptions->addColumn('organization_id', 'bigint', ['unsigned' => true]);
        $subscriptions->addColumn('plan_id', 'bigint', ['unsigned' => true]);
        $subscriptions->addColumn('start_date', 'date');
        $subscriptions->addColumn('end_date', 'date', ['notnull' => false]);
        $subscriptions->addColumn('status', 'string', ['length' => 50]);
        $subscriptions->addColumn('created_at', 'datetime');
        $subscriptions->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_subscriptions_org');
        $subscriptions->addForeignKeyConstraint('plans', ['plan_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_subscriptions_plan');

        $tasks = $schema->createTable('tasks');
        $this->addId($tasks);
        $tasks->addColumn('organization_id', 'bigint', ['unsigned' => true]);
        $tasks->addColumn('name', 'string', ['length' => 255]);
        $tasks->addColumn('description', 'text', ['notnull' => false]);
        $tasks->addColumn('workstation', 'string', ['length' => 255, 'notnull' => false]);
        $tasks->addColumn('department', 'string', ['length' => 255, 'notnull' => false]);
        $tasks->addColumn('created_at', 'datetime');
        $tasks->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_tasks_org');

        $scans = $schema->createTable('scans');
        $this->addId($scans);
        $scans->addColumn('organization_id', 'bigint', ['unsigned' => true]);
        $scans->addColumn('user_id', 'bigint', ['unsigned' => true]);
        $scans->addColumn('task_id', 'bigint', ['unsigned' => true]);
        $scans->addColumn('scan_type', 'string', ['length' => 20]);
        $scans->addColumn('model', 'string', ['length' => 20, 'default' => 'reba']);
        $scans->addColumn('raw_score', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $scans->addColumn('normalized_score', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $scans->addColumn('risk_category', 'string', ['length' => 20, 'default' => 'low']);
        $scans->addColumn('parent_scan_id', 'bigint', ['unsigned' => true, 'notnull' => false]);
        $scans->addColumn('status', 'string', ['length' => 20, 'default' => 'completed']);
        $scans->addColumn('error_message', 'text', ['notnull' => false]);
        $scans->addColumn('video_path', 'string', ['length' => 1024, 'notnull' => false]);
        $scans->addColumn('created_at', 'datetime');
        $scans->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_scans_org');
        $scans->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_scans_user');
        $scans->addForeignKeyConstraint('tasks', ['task_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_scans_task');
        $scans->addForeignKeyConstraint('scans', ['parent_scan_id'], ['id'], ['onDelete' => 'SET NULL'], 'fk_scans_parent');

        $scanMetrics = $schema->createTable('scan_metrics');
        $this->addId($scanMetrics);
        $scanMetrics->addColumn('scan_id', 'bigint', ['unsigned' => true]);
        $scanMetrics->addColumn('neck_angle', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('trunk_angle', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('upper_arm_angle', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('lower_arm_angle', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('wrist_angle', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('leg_score', 'integer', ['notnull' => false]);
        $scanMetrics->addColumn('load_weight', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('horizontal_distance', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('vertical_start', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('vertical_travel', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('twist_angle', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('frequency', 'decimal', ['precision' => 10, 'scale' => 2, 'notnull' => false]);
        $scanMetrics->addColumn('coupling', 'string', ['length' => 50, 'notnull' => false]);
        $scanMetrics->addColumn('shoulder_elevation_duration', 'decimal', ['precision' => 10, 'scale' => 4, 'notnull' => false]);
        $scanMetrics->addColumn('repetition_count', 'integer', ['notnull' => false]);
        $scanMetrics->addColumn('processing_confidence', 'decimal', ['precision' => 5, 'scale' => 4, 'notnull' => false]);
        $scanMetrics->addColumn('created_at', 'datetime', [
            'columnDefinition' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ]);
        $scanMetrics->addForeignKeyConstraint('scans', ['scan_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_metrics_scan');

        $scanResults = $schema->createTable('scan_results');
        $this->addId($scanResults);
        $scanResults->addColumn('scan_id', 'bigint', ['unsigned' => true]);
        $scanResults->addColumn('model', 'string', ['length' => 20]);
        $scanResults->addColumn('score', 'decimal', ['precision' => 10, 'scale' => 2]);
        $scanResults->addColumn('risk_level', 'string', ['length' => 191]);
        $scanResults->addColumn('recommendation', 'text', ['notnull' => false]);
        $scanResults->addColumn('algorithm_version', 'string', ['length' => 64, 'default' => 'legacy_v1']);
        $scanResults->addColumn('created_at', 'datetime', [
            'columnDefinition' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ]);
        $scanResults->addForeignKeyConstraint('scans', ['scan_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_results_scan');

        $manualInputs = $schema->createTable('manual_inputs');
        $manualInputs->addColumn('scan_id', 'bigint', ['unsigned' => true]);
        $manualInputs->addColumn('weight', 'decimal', ['precision' => 10, 'scale' => 2]);
        $manualInputs->addColumn('frequency', 'decimal', ['precision' => 10, 'scale' => 2]);
        $manualInputs->addColumn('duration', 'decimal', ['precision' => 10, 'scale' => 2]);
        $manualInputs->addColumn('trunk_angle_estimate', 'decimal', ['precision' => 10, 'scale' => 2]);
        $manualInputs->addColumn('twisting', 'boolean', ['default' => false]);
        $manualInputs->addColumn('overhead', 'boolean', ['default' => false]);
        $manualInputs->addColumn('repetition', 'decimal', ['precision' => 10, 'scale' => 2]);
        $manualInputs->setPrimaryKey(['scan_id']);
        $manualInputs->addForeignKeyConstraint('scans', ['scan_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_manual_scan');

        $videoMetrics = $schema->createTable('video_metrics');
        $videoMetrics->addColumn('scan_id', 'bigint', ['unsigned' => true]);
        $videoMetrics->addColumn('max_trunk_angle', 'decimal', ['precision' => 10, 'scale' => 2]);
        $videoMetrics->addColumn('avg_trunk_angle', 'decimal', ['precision' => 10, 'scale' => 2]);
        $videoMetrics->addColumn('shoulder_elevation_duration', 'decimal', ['precision' => 10, 'scale' => 4]);
        $videoMetrics->addColumn('repetition_count', 'integer');
        $videoMetrics->addColumn('processing_confidence', 'decimal', ['precision' => 5, 'scale' => 4]);
        $videoMetrics->setPrimaryKey(['scan_id']);
        $videoMetrics->addForeignKeyConstraint('scans', ['scan_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_video_scan');

        $observerRatings = $schema->createTable('observer_ratings');
        $this->addId($observerRatings);
        $observerRatings->addColumn('scan_id', 'bigint', ['unsigned' => true]);
        $observerRatings->addColumn('observer_id', 'bigint', ['unsigned' => true]);
        $observerRatings->addColumn('observer_score', 'decimal', ['precision' => 10, 'scale' => 2]);
        $observerRatings->addColumn('observer_category', 'string', ['length' => 100]);
        $observerRatings->addColumn('notes', 'text', ['notnull' => false]);
        $observerRatings->addColumn('created_at', 'datetime');
        $observerRatings->addForeignKeyConstraint('scans', ['scan_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_observer_scan');
        $observerRatings->addForeignKeyConstraint('users', ['observer_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_observer_user');

        $usageRecords = $schema->createTable('usage_records');
        $this->addId($usageRecords);
        $usageRecords->addColumn('organization_id', 'bigint', ['unsigned' => true]);
        $usageRecords->addColumn('scan_id', 'bigint', ['unsigned' => true]);
        $usageRecords->addColumn('usage_type', 'string', ['length' => 20]);
        $usageRecords->addColumn('created_at', 'datetime');
        $usageRecords->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_usage_org');
        $usageRecords->addForeignKeyConstraint('scans', ['scan_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_usage_scan');

        $notifications = $schema->createTable('notifications');
        $this->addId($notifications);
        $notifications->addColumn('organization_id', 'bigint', ['unsigned' => true]);
        $notifications->addColumn('user_id', 'bigint', ['unsigned' => true, 'notnull' => false]);
        $notifications->addColumn('type', 'string', ['length' => 50]);
        $notifications->addColumn('title', 'string', ['length' => 255]);
        $notifications->addColumn('body', 'text', ['notnull' => false]);
        $notifications->addColumn('link', 'string', ['length' => 512, 'notnull' => false]);
        $notifications->addColumn('is_read', 'boolean', ['default' => false]);
        $notifications->addColumn('created_at', 'datetime', [
            'columnDefinition' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ]);
        $notifications->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_notifications_org');
        $notifications->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'SET NULL'], 'fk_notifications_user');
        $notifications->addIndex(['organization_id', 'is_read', 'created_at'], 'idx_notifications_org_read');

        $systemSettings = $schema->createTable('system_settings');
        $systemSettings->addColumn('key_name', 'string', ['length' => 255]);
        $systemSettings->addColumn('value_data', 'json');
        $systemSettings->setPrimaryKey(['key_name']);

        return $schema;
    }

    /**
     * @return list<string>
     */
    private function tableOrder(): array
    {
        return [
            'organizations',
            'users',
            'plans',
            'subscriptions',
            'tasks',
            'scans',
            'scan_metrics',
            'scan_results',
            'manual_inputs',
            'video_metrics',
            'observer_ratings',
            'usage_records',
            'notifications',
            'system_settings',
        ];
    }

    private function addId(Table $table): void
    {
        $table->addColumn('id', 'bigint', ['unsigned' => true, 'autoincrement' => true]);
        $table->setPrimaryKey(['id']);
    }
};




