-- APONSHOP module controls + partner accounting v2
-- Additive only: no DROP, DELETE or TRUNCATE.

CREATE TABLE IF NOT EXISTS module_controls (
  module_key VARCHAR(40) NOT NULL PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  reason VARCHAR(255) NULL,
  updated_by INT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_v2_settings (
  id TINYINT NOT NULL PRIMARY KEY,
  cutoff_date DATE NOT NULL,
  initialized_by INT NULL,
  initialized_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  notes VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_v2_product_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  partner_one_id INT NOT NULL,
  partner_one_ratio DECIMAL(7,4) NOT NULL DEFAULT 50,
  partner_two_id INT NOT NULL,
  partner_two_ratio DECIMAL(7,4) NOT NULL DEFAULT 50,
  terms VARCHAR(500) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_pv2_rule_start (product_id,effective_from),
  INDEX idx_pv2_rule_effective (product_id,effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_v2_direct_costs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  product_id INT NOT NULL,
  cost_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  description VARCHAR(255) NULL,
  reference VARCHAR(90) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'approved',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pv2_direct_ref (reference),
  INDEX idx_pv2_direct_sale (sale_id,product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_v2_expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  voucher_no VARCHAR(80) NOT NULL,
  category VARCHAR(50) NOT NULL,
  description VARCHAR(500) NULL,
  amount DECIMAL(14,2) NOT NULL,
  payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
  payer_type VARCHAR(20) NOT NULL DEFAULT 'business',
  payer_partner_id INT NULL,
  account_id INT NULL,
  receipt_path VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'approved',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  voided_by INT NULL,
  voided_at DATETIME NULL,
  void_reason VARCHAR(255) NULL,
  UNIQUE KEY uniq_pv2_expense_voucher (voucher_no),
  INDEX idx_pv2_expense_date (expense_date),
  INDEX idx_pv2_expense_partner (payer_partner_id),
  INDEX idx_pv2_expense_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_v2_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  partner_id INT NOT NULL,
  movement_date DATE NOT NULL,
  movement_type VARCHAR(40) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
  account_id INT NULL,
  reference VARCHAR(90) NOT NULL,
  note VARCHAR(500) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'posted',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  reversed_by INT NULL,
  reversed_at DATETIME NULL,
  reversal_reason VARCHAR(255) NULL,
  UNIQUE KEY uniq_pv2_movement_ref (reference),
  INDEX idx_pv2_move_partner_date (partner_id,movement_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_v2_months (
  period_ym CHAR(7) NOT NULL PRIMARY KEY,
  period_from DATE NOT NULL,
  period_to DATE NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  snapshot_json LONGTEXT NULL,
  snapshot_hash CHAR(64) NULL,
  closed_by INT NULL,
  closed_at DATETIME NULL,
  reopened_by INT NULL,
  reopened_at DATETIME NULL,
  close_note VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_v2_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_id VARCHAR(70) NOT NULL,
  actor_id INT NULL,
  action VARCHAR(50) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id VARCHAR(80) NULL,
  period_ym CHAR(7) NULL,
  before_json LONGTEXT NULL,
  after_json LONGTEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pv2_audit_event (event_id),
  INDEX idx_pv2_audit_entity (entity_type,entity_id),
  INDEX idx_pv2_audit_period (period_ym)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
