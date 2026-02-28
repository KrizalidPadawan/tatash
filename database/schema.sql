SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS financial_saas
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE financial_saas;

CREATE TABLE IF NOT EXISTS tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  plan VARCHAR(30) NOT NULL DEFAULT 'basic',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenants_slug (slug)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_tenant_email (tenant_id, email),
  UNIQUE KEY uq_users_id_tenant (id, tenant_id),
  KEY idx_users_tenant_role (tenant_id, role_id),
  CONSTRAINT fk_users_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_users_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  kind ENUM('income', 'expense') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_tenant_name_kind (tenant_id, name, kind),
  UNIQUE KEY uq_categories_id_tenant (id, tenant_id),
  KEY idx_categories_tenant_kind (tenant_id, kind),
  CONSTRAINT fk_categories_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  type ENUM('income', 'expense') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  description VARCHAR(255) NOT NULL,
  transaction_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_transactions_id_tenant (id, tenant_id),
  KEY idx_tx_tenant_date (tenant_id, transaction_date),
  KEY idx_tx_tenant_category (tenant_id, category_id),
  KEY idx_tx_tenant_created_by (tenant_id, created_by),
  CONSTRAINT chk_transactions_amount_positive
    CHECK (amount > 0),
  CONSTRAINT fk_transactions_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_transactions_category_tenant
    FOREIGN KEY (category_id, tenant_id) REFERENCES categories(id, tenant_id),
  CONSTRAINT fk_transactions_user_tenant
    FOREIGN KEY (created_by, tenant_id) REFERENCES users(id, tenant_id)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS receipts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  transaction_id BIGINT UNSIGNED NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_receipts_checksum (checksum_sha256),
  KEY idx_receipts_tenant_tx (tenant_id, transaction_id),
  CONSTRAINT chk_receipts_file_size_positive
    CHECK (file_size > 0),
  CONSTRAINT fk_receipts_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_receipts_tx_tenant
    FOREIGN KEY (transaction_id, tenant_id) REFERENCES transactions(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NOT NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_tenant_date (tenant_id, created_at),
  KEY idx_audit_tenant_action (tenant_id, action),
  KEY idx_audit_tenant_user (tenant_id, user_id),
  CONSTRAINT fk_audit_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_audit_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS refresh_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  tenant_id BIGINT UNSIGNED NOT NULL,
  jti CHAR(32) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_refresh_jti (jti),
  KEY idx_refresh_tenant_user (tenant_id, user_id),
  KEY idx_refresh_expires_at (expires_at),
  CONSTRAINT fk_refresh_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_refresh_user_tenant
    FOREIGN KEY (user_id, tenant_id) REFERENCES users(id, tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(180) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL,
  KEY idx_login_attempts_email_ip_time (email, ip_address, attempted_at)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC;

INSERT INTO roles (code, name)
VALUES
  ('ADMIN', 'Administrador'),
  ('INGRESADOR', 'Ingresador'),
  ('VISUALIZADOR', 'Visualizador')
ON DUPLICATE KEY UPDATE
  name = VALUES(name);

INSERT INTO permissions (code, name)
VALUES
  ('transactions.read', 'Leer transacciones'),
  ('transactions.write', 'Crear/editar transacciones'),
  ('reports.read', 'Leer reportes'),
  ('dashboard.read', 'Leer dashboard'),
  ('admin.full', 'Acceso total')
ON DUPLICATE KEY UPDATE
  name = VALUES(name);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
  ON p.code IN (
    'transactions.read',
    'transactions.write',
    'reports.read',
    'dashboard.read',
    'admin.full'
  )
WHERE r.code = 'ADMIN';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
  ON p.code IN (
    'transactions.read',
    'transactions.write',
    'reports.read',
    'dashboard.read'
  )
WHERE r.code = 'INGRESADOR';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
  ON p.code IN (
    'transactions.read',
    'reports.read',
    'dashboard.read'
  )
WHERE r.code = 'VISUALIZADOR';
