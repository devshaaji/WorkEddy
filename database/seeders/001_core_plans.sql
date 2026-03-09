-- Core application seed data

INSERT INTO plans (name, scan_limit, price)
SELECT 'starter',      10,   0.00  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = 'starter');

INSERT INTO plans (name, scan_limit, price)
SELECT 'professional', 500,  299.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = 'professional');

INSERT INTO plans (name, scan_limit, price)
SELECT 'enterprise',   NULL, 999.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = 'enterprise');
