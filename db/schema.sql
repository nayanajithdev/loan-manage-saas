CREATE DATABASE IF NOT EXISTS loan_manage_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE loan_manage_saas;

CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    owner_name VARCHAR(150) NULL,
    owner_email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    status ENUM('pending', 'approved', 'rejected', 'suspended', 'deleted') NOT NULL DEFAULT 'pending',
    approved_at DATETIME NULL,
    suspended_at DATETIME NULL,
    rejected_at DATETIME NULL,
    deleted_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenants_status (status),
    INDEX idx_tenants_owner_email (owner_email)
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(190) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'owner', 'manager', 'collector') NOT NULL DEFAULT 'manager',
    owner_tenant_unique_key INT GENERATED ALWAYS AS (CASE WHEN role = 'owner' THEN tenant_id ELSE NULL END) STORED,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    force_logout_at DATETIME NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    theme_preference ENUM('dark', 'light') NOT NULL DEFAULT 'dark',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_tenant_id (tenant_id),
    INDEX idx_users_role (role),
    INDEX idx_users_status (status),
    UNIQUE KEY uq_users_one_owner_per_tenant (owner_tenant_unique_key),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uq_users_email (email)
);

CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INT NOT NULL,
    permission_key VARCHAR(80) NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    requested_ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_reset_tokens_token_hash (token_hash),
    INDEX idx_password_reset_tokens_user_id (user_id),
    INDEX idx_password_reset_tokens_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS remember_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    user_agent VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_remember_tokens_token_hash (token_hash),
    INDEX idx_remember_tokens_user_id (user_id),
    INDEX idx_remember_tokens_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    customer_code VARCHAR(30) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    nic VARCHAR(60) DEFAULT NULL,
    address TEXT,
    note TEXT,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customers_tenant_code (tenant_id, customer_code),
    INDEX idx_customers_tenant_status (tenant_id, status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_routes_tenant_name (tenant_id, name),
    INDEX idx_routes_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    loan_number VARCHAR(40) NOT NULL,
    customer_id INT NOT NULL,
    route_id INT DEFAULT NULL,
    assigned_user_id INT DEFAULT NULL,
    issued_date DATE DEFAULT NULL,
    principal_amount DECIMAL(12,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    interest_rate_type ENUM('amount_based','monthly') NOT NULL DEFAULT 'amount_based',
    interest_rate_months INT NOT NULL DEFAULT 1,
    total_amount DECIMAL(12,2) NOT NULL,
    installment_frequency ENUM('daily', 'weekly', 'monthly') NOT NULL,
    installment_count INT NOT NULL,
    installment_amount DECIMAL(12,2) NOT NULL,
    start_date DATE NOT NULL,
    first_due_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_loans_tenant_number (tenant_id, loan_number),
    INDEX idx_loans_tenant_status (tenant_id, status),
    INDEX idx_loans_tenant_route (tenant_id, route_id),
    INDEX idx_loans_route (route_id),
    INDEX idx_loans_assigned_user (assigned_user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (route_id) REFERENCES routes(id),
    FOREIGN KEY (assigned_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS loan_installments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    loan_id INT NOT NULL,
    installment_no INT NOT NULL,
    due_date DATE NOT NULL,
    due_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    paid_on DATE DEFAULT NULL,
    status ENUM('pending', 'partial', 'paid', 'overdue') NOT NULL DEFAULT 'pending',
    is_flexible_adjustment TINYINT(1) NOT NULL DEFAULT 0,
    source_payment_ref VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_loan_installment (loan_id, installment_no),
    INDEX idx_loan_installments_tenant_status (tenant_id, status, due_date),
    INDEX idx_loan_installments_flexible (loan_id, is_flexible_adjustment, installment_no),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id)
);

CREATE TABLE IF NOT EXISTS collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    loan_id INT NOT NULL,
    installment_id INT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    collected_on DATE NOT NULL,
    method VARCHAR(40) NOT NULL DEFAULT 'cash',
    note VARCHAR(255) DEFAULT NULL,
    collected_by_user_id INT DEFAULT NULL,
    payment_ref VARCHAR(50) DEFAULT NULL,
    meta_json LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_collections_tenant_date (tenant_id, collected_on),
    INDEX idx_collections_collected_by_user (collected_by_user_id),
    INDEX idx_collections_payment_ref (payment_ref),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id),
    FOREIGN KEY (installment_id) REFERENCES loan_installments(id),
    FOREIGN KEY (collected_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS customer_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    customer_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_documents_tenant_id (tenant_id),
    INDEX idx_customer_documents_customer_id (customer_id),
    INDEX idx_customer_documents_uploaded_by (uploaded_by_user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS system_settings (
    tenant_id INT NOT NULL DEFAULT 0,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by_user_id INT NULL,
    PRIMARY KEY (tenant_id, setting_key),
    INDEX idx_system_settings_updated_by (updated_by_user_id),
    FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    holiday_date DATE NOT NULL,
    note VARCHAR(255) NULL,
    created_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_holidays_tenant_date (tenant_id, holiday_date),
    INDEX idx_holidays_created_by (created_by_user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    actor_user_id INT NULL,
    actor_role VARCHAR(20) NULL,
    action_key VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    meta_json LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_logs_tenant_created (tenant_id, created_at),
    INDEX idx_activity_logs_created_at (created_at),
    INDEX idx_activity_logs_actor (actor_user_id),
    INDEX idx_activity_logs_action_key (action_key),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
);
