-- APONSHOP 10.98.70 additive schema only — safe for live data.
-- No DROP / TRUNCATE / DELETE. Prefer Settings → Tools → Schema Sync.

CREATE TABLE IF NOT EXISTS `employee_advances` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  account_id INT NOT NULL DEFAULT 0,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  kind VARCHAR(20) NOT NULL DEFAULT 'give',
  advance_date DATE NOT NULL,
  note VARCHAR(255) NULL DEFAULT NULL,
  previous_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  current_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  affects_cash TINYINT(1) NOT NULL DEFAULT 1,
  idempotency_token VARCHAR(64) NULL DEFAULT NULL,
  created_by INT NULL DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY employee_id (employee_id),
  KEY advance_date (advance_date),
  UNIQUE KEY uniq_adv_idem (idempotency_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sms_outbox` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mobile VARCHAR(20) NOT NULL,
  message TEXT NOT NULL,
  purpose VARCHAR(100) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  last_error VARCHAR(255) DEFAULT NULL,
  log_id INT NULL,
  claimed_at DATETIME NULL,
  queued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  INDEX idx_sms_out_status (status, queued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sms_settings` (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  api_url VARCHAR(255) NOT NULL DEFAULT '',
  api_key VARCHAR(255) NOT NULL DEFAULT '',
  sender_id VARCHAR(80) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'inactive',
  channel VARCHAR(20) NOT NULL DEFAULT 'sim',
  gateway_phone VARCHAR(20) NOT NULL DEFAULT '',
  device_last_seen DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prefer Schema Sync for ADD COLUMN on existing installs:
-- ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0;
-- ALTER TABLE sms_settings ADD COLUMN gateway_phone VARCHAR(20) NOT NULL DEFAULT '';
-- ALTER TABLE sms_settings ADD COLUMN device_last_seen DATETIME NULL DEFAULT NULL;
-- ALTER TABLE employees ADD COLUMN advance_balance DECIMAL(12,2) NOT NULL DEFAULT 0;
