-- Core application seed data

INSERT INTO plans (name, scan_limit, price, billing_limits_json)
SELECT 'starter', 10, 0.00, JSON_OBJECT(
    'video_scan_limit', 10,
    'live_session_limit', 10,
    'live_session_minutes_limit', 120,
    'llm_request_limit', 25,
    'llm_token_limit', 100000,
    'max_video_retention_days', 30,
    'max_org_members', 5,
    'max_live_concurrent_sessions', 1
) FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = 'starter');

INSERT INTO plans (name, scan_limit, price, billing_limits_json)
SELECT 'professional', 500, 299.00, JSON_OBJECT(
    'video_scan_limit', 500,
    'live_session_limit', 250,
    'live_session_minutes_limit', 3000,
    'llm_request_limit', 500,
    'llm_token_limit', 2000000,
    'max_video_retention_days', 180,
    'max_org_members', 50,
    'max_live_concurrent_sessions', 4
) FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = 'professional');

INSERT INTO plans (name, scan_limit, price, billing_limits_json)
SELECT 'enterprise', NULL, 999.00, JSON_OBJECT(
    'video_scan_limit', CAST(NULL AS SIGNED),
    'live_session_limit', CAST(NULL AS SIGNED),
    'live_session_minutes_limit', CAST(NULL AS SIGNED),
    'llm_request_limit', CAST(NULL AS SIGNED),
    'llm_token_limit', CAST(NULL AS SIGNED),
    'max_video_retention_days', 3650,
    'max_org_members', CAST(NULL AS SIGNED),
    'max_live_concurrent_sessions', 12
) FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = 'enterprise');
