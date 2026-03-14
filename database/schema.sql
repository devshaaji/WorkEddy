-- WorkEddy Database Schema
-- Run via: php scripts/migrate.php

CREATE TABLE IF NOT EXISTS organizations (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255)    NOT NULL,
    slug          VARCHAR(255)    NULL,
    contact_email VARCHAR(255)    NULL,
    plan          VARCHAR(100)    NOT NULL DEFAULT 'starter',
    settings      JSON            NULL,
    status        ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    created_at    DATETIME        NOT NULL,
    updated_at    DATETIME        NULL
);

CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(255)    NOT NULL,
    email           VARCHAR(255)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('admin','supervisor','worker','observer') NOT NULL,
    status          ENUM('active','inactive','invited') NOT NULL DEFAULT 'active',
    email_verified  TINYINT(1)      NOT NULL DEFAULT 0,
    email_otp       VARCHAR(10)     NULL,
    email_otp_expires_at DATETIME   NULL,
    two_factor_enabled TINYINT(1)   NOT NULL DEFAULT 0,
    two_factor_secret  VARCHAR(255) NULL,
    created_at      DATETIME        NOT NULL,
    updated_at      DATETIME        NULL,
    CONSTRAINT fk_users_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS plans (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)   NOT NULL,
    scan_limit    INT            NULL,
    price         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    billing_cycle VARCHAR(50)    NOT NULL DEFAULT 'monthly',
    billing_limits_json JSON     NULL,
    status        ENUM('active','archived') NOT NULL DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    plan_id         BIGINT UNSIGNED NOT NULL,
    start_date      DATE            NOT NULL,
    end_date        DATE            NULL,
    status          VARCHAR(50)     NOT NULL,
    created_at      DATETIME        NOT NULL,
    CONSTRAINT fk_subscriptions_org  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id)         REFERENCES plans(id)         ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS tasks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(255)    NOT NULL,
    description     TEXT            NULL,
    workstation     VARCHAR(255)    NULL,
    department      VARCHAR(255)    NULL,
    created_at      DATETIME        NOT NULL,
    CONSTRAINT fk_tasks_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS scans (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id  BIGINT UNSIGNED NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    task_id          BIGINT UNSIGNED NOT NULL,
    scan_type        ENUM('manual','video') NOT NULL,
    model            ENUM('rula','reba','niosh') NOT NULL DEFAULT 'reba',
    raw_score        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    normalized_score DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    risk_category    ENUM('low','moderate','high') NOT NULL DEFAULT 'low',
    parent_scan_id   BIGINT UNSIGNED NULL,
    status           ENUM('created','processing','completed','invalid') NOT NULL DEFAULT 'completed',
    error_message    TEXT            NULL,
    video_path       VARCHAR(1024)   NULL,
    created_at       DATETIME        NOT NULL,
    CONSTRAINT fk_scans_org    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_scans_user   FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE,
    CONSTRAINT fk_scans_task   FOREIGN KEY (task_id)         REFERENCES tasks(id)         ON DELETE CASCADE,
    CONSTRAINT fk_scans_parent FOREIGN KEY (parent_scan_id)  REFERENCES scans(id)         ON DELETE SET NULL
);

-- Unified scan metrics table - stores posture / lifting variables for any model.
-- Columns are nullable because different models use different subsets.
CREATE TABLE IF NOT EXISTS scan_metrics (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scan_id         BIGINT UNSIGNED NOT NULL,
    -- Posture angles (RULA / REBA / video-derived)
    neck_angle      DECIMAL(10,2) NULL,
    trunk_angle     DECIMAL(10,2) NULL,
    upper_arm_angle DECIMAL(10,2) NULL,
    lower_arm_angle DECIMAL(10,2) NULL,
    wrist_angle     DECIMAL(10,2) NULL,
    leg_score       INT           NULL,
    -- NIOSH lifting fields
    load_weight     DECIMAL(10,2) NULL,
    horizontal_distance DECIMAL(10,2) NULL,
    vertical_start  DECIMAL(10,2) NULL,
    vertical_travel DECIMAL(10,2) NULL,
    twist_angle     DECIMAL(10,2) NULL,
    frequency       DECIMAL(10,2) NULL,
    coupling        VARCHAR(50)   NULL,
    -- Video-derived extras
    shoulder_elevation_duration DECIMAL(10,4) NULL,
    repetition_count INT          NULL,
    processing_confidence DECIMAL(5,4) NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_metrics_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE
);

-- Separate results table - stores computed score per assessment
CREATE TABLE IF NOT EXISTS scan_results (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scan_id         BIGINT UNSIGNED NOT NULL,
    model           ENUM('rula','reba','niosh') NOT NULL,
    score           DECIMAL(10,2) NOT NULL,
    risk_level      VARCHAR(191)  NOT NULL,
    recommendation  TEXT          NULL,
    algorithm_version VARCHAR(64) NOT NULL DEFAULT 'legacy_v1',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_results_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS scan_control_recommendations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scan_id BIGINT UNSIGNED NOT NULL,
    rank_order TINYINT UNSIGNED NOT NULL,
    hierarchy_level ENUM('elimination','substitution','engineering','administrative','ppe') NOT NULL,
    control_code VARCHAR(120) NOT NULL,
    title VARCHAR(255) NOT NULL,
    expected_risk_reduction_pct DECIMAL(5,2) NOT NULL,
    implementation_cost ENUM('low','medium','high') NOT NULL,
    time_to_deploy_days INT UNSIGNED NOT NULL,
    throughput_impact ENUM('low','medium','high') NOT NULL,
    control_type ENUM('permanent','interim') NOT NULL DEFAULT 'permanent',
    feasibility_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    feasibility_status ENUM('feasible','conditional','not_feasible') NOT NULL DEFAULT 'conditional',
    interim_for_control_code VARCHAR(120) NULL,
    rationale TEXT NOT NULL,
    evidence_json JSON NULL,
    recommendation_engine_version VARCHAR(64) NOT NULL DEFAULT 'ctrl_rec_v1',
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_ctrl_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_ctrl_scan_rank (scan_id, rank_order),
    UNIQUE KEY uniq_ctrl_scan_code (scan_id, control_code),
    INDEX idx_ctrl_scan (scan_id),
    INDEX idx_ctrl_hierarchy (hierarchy_level),
    INDEX idx_ctrl_feasibility (feasibility_status)
);

CREATE TABLE IF NOT EXISTS control_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    source_scan_id BIGINT UNSIGNED NOT NULL,
    source_control_id BIGINT UNSIGNED NULL,
    control_code VARCHAR(120) NOT NULL,
    control_title VARCHAR(255) NOT NULL,
    hierarchy_level ENUM('elimination','substitution','engineering','administrative','ppe') NOT NULL,
    control_type ENUM('permanent','interim') NOT NULL DEFAULT 'permanent',
    assigned_to_user_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('planned','in_progress','implemented','verified','cancelled') NOT NULL DEFAULT 'planned',
    priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    target_due_date DATE NULL,
    implementation_notes TEXT NULL,
    worker_feedback_json JSON NULL,
    verification_scan_id BIGINT UNSIGNED NULL,
    verification_summary_json JSON NULL,
    implemented_at DATETIME NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    CONSTRAINT fk_action_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_action_scan FOREIGN KEY (source_scan_id) REFERENCES scans(id) ON DELETE CASCADE,
    CONSTRAINT fk_action_control FOREIGN KEY (source_control_id) REFERENCES scan_control_recommendations(id) ON DELETE SET NULL,
    CONSTRAINT fk_action_assignee FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_action_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_action_verification_scan FOREIGN KEY (verification_scan_id) REFERENCES scans(id) ON DELETE SET NULL,
    INDEX idx_action_org_status (organization_id, status, created_at),
    INDEX idx_action_org_scan (organization_id, source_scan_id),
    INDEX idx_action_assignee (assigned_to_user_id, status)
);

CREATE TABLE IF NOT EXISTS copilot_audit_logs (
    id CHAR(36) PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    persona VARCHAR(50) NOT NULL,
    request_payload_redacted JSON NULL,
    deterministic_bundle_redacted JSON NULL,
    llm_prompt_redacted JSON NULL,
    llm_response_redacted JSON NULL,
    response_payload_redacted JSON NULL,
    llm_status ENUM('success','fallback','disabled') NOT NULL,
    llm_request_count INT UNSIGNED NOT NULL DEFAULT 0,
    llm_prompt_tokens INT UNSIGNED NULL,
    llm_completion_tokens INT UNSIGNED NULL,
    llm_total_tokens INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_copilot_audit_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_copilot_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_copilot_audit_org_created (organization_id, created_at),
    INDEX idx_copilot_audit_user_created (user_id, created_at),
    INDEX idx_copilot_audit_persona_created (persona, created_at)
);

-- Legacy tables kept for backwards compatibility with existing data.
-- New scans use scan_metrics + scan_results instead.

CREATE TABLE IF NOT EXISTS manual_inputs (
    scan_id              BIGINT UNSIGNED PRIMARY KEY,
    weight               DECIMAL(10,2) NOT NULL,
    frequency            DECIMAL(10,2) NOT NULL,
    duration             DECIMAL(10,2) NOT NULL,
    trunk_angle_estimate DECIMAL(10,2) NOT NULL,
    twisting             TINYINT(1)    NOT NULL DEFAULT 0,
    overhead             TINYINT(1)    NOT NULL DEFAULT 0,
    repetition           DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_manual_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS video_metrics (
    scan_id                    BIGINT UNSIGNED PRIMARY KEY,
    max_trunk_angle            DECIMAL(10,2) NOT NULL,
    avg_trunk_angle            DECIMAL(10,2) NOT NULL,
    shoulder_elevation_duration DECIMAL(10,4) NOT NULL,
    repetition_count           INT           NOT NULL,
    processing_confidence      DECIMAL(5,4)  NOT NULL,
    CONSTRAINT fk_video_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS observer_ratings (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scan_id           BIGINT UNSIGNED NOT NULL,
    observer_id       BIGINT UNSIGNED NOT NULL,
    observer_score    DECIMAL(10,2)   NOT NULL,
    observer_category VARCHAR(100)    NOT NULL,
    notes             TEXT            NULL,
    created_at        DATETIME        NOT NULL,
    CONSTRAINT fk_observer_scan FOREIGN KEY (scan_id)     REFERENCES scans(id) ON DELETE CASCADE,
    CONSTRAINT fk_observer_user FOREIGN KEY (observer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS usage_records (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    scan_id         BIGINT UNSIGNED NOT NULL,
    usage_type      ENUM('manual_scan','video_scan') NOT NULL,
    created_at      DATETIME        NOT NULL,
    CONSTRAINT fk_usage_org  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_usage_scan FOREIGN KEY (scan_id)         REFERENCES scans(id)         ON DELETE CASCADE,
    UNIQUE KEY uniq_usage_org_scan_type (organization_id, scan_id, usage_type),
    INDEX idx_usage_org_created (organization_id, created_at),
    INDEX idx_usage_created (created_at)
);

CREATE TABLE IF NOT EXISTS usage_reservations (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    scan_id         BIGINT UNSIGNED NOT NULL,
    usage_type      ENUM('video_scan') NOT NULL,
    created_at      DATETIME        NOT NULL,
    CONSTRAINT fk_usage_res_org  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_usage_res_scan FOREIGN KEY (scan_id)         REFERENCES scans(id)         ON DELETE CASCADE,
    UNIQUE KEY uniq_usage_res_org_scan_type (organization_id, scan_id, usage_type),
    INDEX idx_usage_res_org_created (organization_id, created_at),
    INDEX idx_usage_res_created (created_at)
);

CREATE TABLE IF NOT EXISTS worker_leading_indicators (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NULL,
    checkin_type ENUM('pre_shift','mid_shift','post_shift') NOT NULL DEFAULT 'post_shift',
    shift_date DATE NOT NULL,
    discomfort_level TINYINT UNSIGNED NOT NULL,
    fatigue_level TINYINT UNSIGNED NOT NULL,
    micro_breaks_taken SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    recovery_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    overtime_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    task_rotation_quality ENUM('poor','fair','good') NOT NULL DEFAULT 'fair',
    psychosocial_load ENUM('low','moderate','high') NOT NULL DEFAULT 'moderate',
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_wli_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_wli_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_wli_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    INDEX idx_wli_org_shift (organization_id, shift_date),
    INDEX idx_wli_org_created (organization_id, created_at)
);

CREATE TABLE IF NOT EXISTS queue_jobs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_name  VARCHAR(100)    NOT NULL,
    payload     JSON            NOT NULL,
    created_at  DATETIME        NOT NULL,
    INDEX idx_queue_jobs_queue_id (queue_name, id)
);

CREATE TABLE IF NOT EXISTS live_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NOT NULL,
    model ENUM('rula','reba') NOT NULL DEFAULT 'reba',
    pose_engine ENUM('mediapipe','yolo26') NOT NULL DEFAULT 'yolo26',
    status ENUM('active','paused','completed','failed') NOT NULL DEFAULT 'active',
    target_fps DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    batch_window_ms INT UNSIGNED NOT NULL DEFAULT 500,
    max_e2e_latency_ms INT UNSIGNED NOT NULL DEFAULT 2000,
    frame_count INT UNSIGNED NOT NULL DEFAULT 0,
    analysed_frame_count INT UNSIGNED NOT NULL DEFAULT 0,
    avg_latency_ms DECIMAL(10,2) NULL,
    summary_metrics_json JSON NULL,
    telemetry_json JSON NULL,
    error_message TEXT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    CONSTRAINT fk_live_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_live_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_live_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_live_org_status (organization_id, status),
    INDEX idx_live_org_created (organization_id, created_at)
);

CREATE TABLE IF NOT EXISTS live_session_frames (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    frame_number INT UNSIGNED NOT NULL,
    metrics_json JSON NOT NULL,
    trunk_angle DECIMAL(10,2) NULL,
    neck_angle DECIMAL(10,2) NULL,
    upper_arm_angle DECIMAL(10,2) NULL,
    lower_arm_angle DECIMAL(10,2) NULL,
    wrist_angle DECIMAL(10,2) NULL,
    confidence DECIMAL(5,4) NULL,
    latency_ms DECIMAL(10,2) NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    CONSTRAINT fk_lsf_session FOREIGN KEY (session_id) REFERENCES live_sessions(id) ON DELETE CASCADE,
    INDEX idx_lsf_session_frame (session_id, frame_number)
);

CREATE TABLE IF NOT EXISTS notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL,
    type            VARCHAR(50)     NOT NULL,
    title           VARCHAR(255)    NOT NULL,
    body            TEXT            NULL,
    link            VARCHAR(512)    NULL,
    is_read         TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_org  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE SET NULL,
    INDEX idx_notifications_org_read (organization_id, is_read, created_at DESC)
);

CREATE TABLE IF NOT EXISTS invoices (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id   BIGINT UNSIGNED NOT NULL,
    subscription_id   BIGINT UNSIGNED NOT NULL,
    plan_id           BIGINT UNSIGNED NOT NULL,
    billing_cycle     VARCHAR(50)     NOT NULL,
    period_start      DATETIME        NOT NULL,
    period_end        DATETIME        NOT NULL,
    amount            DECIMAL(10,2)   NOT NULL,
    currency          VARCHAR(10)     NOT NULL DEFAULT 'USD',
    status            ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    gateway           VARCHAR(50)     NULL,
    provider_reference VARCHAR(191)   NULL,
    metadata          JSON            NULL,
    created_at        DATETIME        NOT NULL,
    paid_at           DATETIME        NULL,
    CONSTRAINT fk_invoices_org  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_sub  FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT,
    UNIQUE KEY uniq_invoice_org_plan_period (organization_id, plan_id, period_start, period_end),
    INDEX idx_invoices_org_created (organization_id, created_at),
    INDEX idx_invoices_status (status)
);

CREATE TABLE IF NOT EXISTS payment_transactions (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id        BIGINT UNSIGNED NOT NULL,
    organization_id   BIGINT UNSIGNED NOT NULL,
    gateway           VARCHAR(50)     NOT NULL,
    status            ENUM('pending','paid','failed') NOT NULL,
    amount            DECIMAL(10,2)   NOT NULL,
    currency          VARCHAR(10)     NOT NULL DEFAULT 'USD',
    provider_reference VARCHAR(191)   NULL,
    response_payload  JSON            NULL,
    created_at        DATETIME        NOT NULL,
    CONSTRAINT fk_pay_txn_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_txn_org     FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_pay_txn_invoice (invoice_id, created_at),
    INDEX idx_pay_txn_org (organization_id, created_at)
);

-- Global platform settings (key-value, used by super admin)
CREATE TABLE IF NOT EXISTS system_settings (
    key_name   VARCHAR(255) NOT NULL PRIMARY KEY,
    value_data JSON         NOT NULL
);

-- Seed data moved to database/seeders. Run: php scripts/seed.php run
