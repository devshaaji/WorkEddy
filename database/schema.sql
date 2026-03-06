-- WorkEddy Database Schema
-- Run via: php scripts/migrate.php

CREATE TABLE IF NOT EXISTS organizations (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255)    NOT NULL,
    slug          VARCHAR(255)    NULL,
    contact_email VARCHAR(255)    NULL,
    plan          VARCHAR(100)    NOT NULL DEFAULT 'starter',
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
    video_path       VARCHAR(1024)   NULL,
    created_at       DATETIME        NOT NULL,
    CONSTRAINT fk_scans_org    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_scans_user   FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE,
    CONSTRAINT fk_scans_task   FOREIGN KEY (task_id)         REFERENCES tasks(id)         ON DELETE CASCADE,
    CONSTRAINT fk_scans_parent FOREIGN KEY (parent_scan_id)  REFERENCES scans(id)         ON DELETE SET NULL
);

-- Unified scan metrics table – stores posture / lifting variables for any model.
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

-- Separate results table – stores computed score per assessment
CREATE TABLE IF NOT EXISTS scan_results (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scan_id         BIGINT UNSIGNED NOT NULL,
    model           ENUM('rula','reba','niosh') NOT NULL,
    score           DECIMAL(10,2) NOT NULL,
    risk_level      VARCHAR(50)   NOT NULL,
    recommendation  TEXT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_results_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE
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
    CONSTRAINT fk_usage_scan FOREIGN KEY (scan_id)         REFERENCES scans(id)         ON DELETE CASCADE
);

-- Seed default plans (idempotent)
INSERT INTO plans (name, scan_limit, price)
SELECT 'starter',      100,  99.00  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = 'starter');

INSERT INTO plans (name, scan_limit, price)
SELECT 'professional', 500,  299.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = 'professional');

INSERT INTO plans (name, scan_limit, price)
SELECT 'enterprise',   NULL, 999.00 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE name = 'enterprise');
