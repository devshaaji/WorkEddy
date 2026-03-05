CREATE TABLE IF NOT EXISTS organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    plan VARCHAR(100) NOT NULL DEFAULT 'starter',
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','supervisor','worker','observer') NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_users_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    department VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tasks_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS scans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NOT NULL,
    scan_type ENUM('manual','video') NOT NULL,
    raw_score DECIMAL(10,2) NOT NULL,
    normalized_score DECIMAL(10,2) NOT NULL,
    risk_category ENUM('low','moderate','high') NOT NULL,
    parent_scan_id BIGINT UNSIGNED NULL,
    status ENUM('created','processing','completed','invalid') NOT NULL DEFAULT 'completed',
    video_path VARCHAR(1024) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_scans_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_scans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_scans_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_scans_parent FOREIGN KEY (parent_scan_id) REFERENCES scans(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS manual_inputs (
    scan_id BIGINT UNSIGNED PRIMARY KEY,
    weight DECIMAL(10,2) NOT NULL,
    frequency DECIMAL(10,2) NOT NULL,
    duration DECIMAL(10,2) NOT NULL,
    trunk_angle_estimate DECIMAL(10,2) NOT NULL,
    twisting TINYINT(1) NOT NULL DEFAULT 0,
    overhead TINYINT(1) NOT NULL DEFAULT 0,
    repetition DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_manual_inputs_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS usage_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    scan_id BIGINT UNSIGNED NOT NULL,
    usage_type ENUM('manual_scan','video_scan') NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_usage_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_usage_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS observer_ratings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scan_id BIGINT UNSIGNED NOT NULL,
    observer_id BIGINT UNSIGNED NOT NULL,
    observer_score DECIMAL(10,2) NOT NULL,
    observer_category VARCHAR(100) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_observer_scan FOREIGN KEY (scan_id) REFERENCES scans(id) ON DELETE CASCADE,
    CONSTRAINT fk_observer_user FOREIGN KEY (observer_id) REFERENCES users(id) ON DELETE CASCADE
);
