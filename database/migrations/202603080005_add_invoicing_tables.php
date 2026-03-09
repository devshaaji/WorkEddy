<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Core\Migrations\MigrationInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603080005_add_invoicing_tables';
    }

    public function up(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();

        if (!$schemaManager->tablesExist(['invoices'])) {
            $db->executeStatement(
                'CREATE TABLE invoices (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    organization_id BIGINT UNSIGNED NOT NULL,
                    subscription_id BIGINT UNSIGNED NOT NULL,
                    plan_id BIGINT UNSIGNED NOT NULL,
                    billing_cycle VARCHAR(50) NOT NULL,
                    period_start DATETIME NOT NULL,
                    period_end DATETIME NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(10) NOT NULL DEFAULT "USD",
                    status VARCHAR(20) NOT NULL DEFAULT "pending",
                    gateway VARCHAR(50) NULL,
                    provider_reference VARCHAR(191) NULL,
                    metadata JSON NULL,
                    created_at DATETIME NOT NULL,
                    paid_at DATETIME NULL,
                    CONSTRAINT fk_invoices_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                    CONSTRAINT fk_invoices_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
                    CONSTRAINT fk_invoices_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
                    UNIQUE KEY uniq_invoice_org_plan_period (organization_id, plan_id, period_start, period_end),
                    INDEX idx_invoices_org_created (organization_id, created_at),
                    INDEX idx_invoices_status (status)
                )'
            );
        }

        if (!$schemaManager->tablesExist(['payment_transactions'])) {
            $db->executeStatement(
                'CREATE TABLE payment_transactions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    invoice_id BIGINT UNSIGNED NOT NULL,
                    organization_id BIGINT UNSIGNED NOT NULL,
                    gateway VARCHAR(50) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(10) NOT NULL DEFAULT "USD",
                    provider_reference VARCHAR(191) NULL,
                    response_payload JSON NULL,
                    created_at DATETIME NOT NULL,
                    CONSTRAINT fk_pay_txn_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                    CONSTRAINT fk_pay_txn_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
                    INDEX idx_pay_txn_invoice (invoice_id, created_at),
                    INDEX idx_pay_txn_org (organization_id, created_at)
                )'
            );
        }
    }

    public function down(Connection $db): void
    {
        $schemaManager = $db->createSchemaManager();

        if ($schemaManager->tablesExist(['payment_transactions'])) {
            $db->executeStatement('DROP TABLE payment_transactions');
        }

        if ($schemaManager->tablesExist(['invoices'])) {
            $db->executeStatement('DROP TABLE invoices');
        }
    }
};
