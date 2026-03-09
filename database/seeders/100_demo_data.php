<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;

return static function (Connection $db): void {
    // Organization
    $existing = $db->fetchAssociative("SELECT id FROM organizations WHERE name = 'Demo Warehouse' LIMIT 1");
    if ($existing) {
        $orgId = (int) $existing['id'];
    } else {
        $db->executeStatement(
            "INSERT INTO organizations (name, plan, created_at) VALUES ('Demo Warehouse', 'professional', NOW())"
        );
        $orgId = (int) $db->lastInsertId();
    }

    // Subscription
    $plan = $db->fetchAssociative("SELECT id FROM plans WHERE name = 'professional' LIMIT 1");
    if ($plan) {
        $subExists = $db->fetchAssociative(
            'SELECT id FROM subscriptions WHERE organization_id = :org_id LIMIT 1',
            ['org_id' => $orgId]
        );

        if (!$subExists) {
            $db->executeStatement(
                "INSERT INTO subscriptions (organization_id, plan_id, start_date, status, created_at) VALUES (:org_id, :plan_id, CURRENT_DATE(), 'active', NOW())",
                ['org_id' => $orgId, 'plan_id' => (int) $plan['id']]
            );
        }
    }

    // Admin user
    $adminEmail = 'admin@demo.workeddy.com';
    $existingUser = $db->fetchAssociative(
        'SELECT id FROM users WHERE email = :email LIMIT 1',
        ['email' => $adminEmail]
    );

    if (!$existingUser) {
        $db->executeStatement(
            "INSERT INTO users (organization_id, name, email, password_hash, role, created_at) VALUES (:org_id, :name, :email, :hash, 'admin', NOW())",
            [
                'org_id' => $orgId,
                'name' => 'Demo Admin',
                'email' => $adminEmail,
                'hash' => password_hash('Password1!', PASSWORD_BCRYPT),
            ]
        );
    }

    // Sample tasks
    $sampleTasks = [
        ['Pallet lifting - Zone A', 'Lifting pallets from floor to waist height', 'Receiving'],
        ['Order picking - Lane C', 'Picking individual items from shelving units', 'Fulfillment'],
        ['Outbound loading - Bay 2', 'Loading vehicles at the outbound loading bay', 'Shipping'],
    ];

    foreach ($sampleTasks as [$name, $description, $department]) {
        $exists = $db->fetchAssociative(
            'SELECT id FROM tasks WHERE organization_id = :org_id AND name = :name LIMIT 1',
            ['org_id' => $orgId, 'name' => $name]
        );

        if (!$exists) {
            $db->executeStatement(
                'INSERT INTO tasks (organization_id, name, description, department, created_at) VALUES (:org_id, :name, :description, :department, NOW())',
                [
                    'org_id' => $orgId,
                    'name' => $name,
                    'description' => $description,
                    'department' => $department,
                ]
            );
        }
    }
};
