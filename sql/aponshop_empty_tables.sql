/* APONSHOP clean schema v10.98.746
   phpMyAdmin: Import -> Choose file -> Go. Do not paste into the SQL tab.
   For a new empty shop only. No live customer or sales rows.
   Default login: admin / Apon7k#4821 (must change after first login).
   Existing tables/rows stay. Missing tables/columns are added.
   No DROP / TRUNCATE / DELETE. */
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

/* account_ledger */
CREATE TABLE IF NOT EXISTS `account_ledger` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  type VARCHAR(10) NOT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  balance_after DECIMAL(14,2) NOT NULL DEFAULT 0,
  ref_type VARCHAR(50) DEFAULT NULL,
  ref_id INT DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  is_reconciled TINYINT(1) NOT NULL DEFAULT 0,
  attachment VARCHAR(255) DEFAULT NULL,
  category_id INT DEFAULT NULL,
  merchant_id INT DEFAULT NULL,
  member_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_al_account (account_id),
  INDEX idx_al_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* accounts */
CREATE TABLE IF NOT EXISTS `accounts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_name VARCHAR(150) NOT NULL,
  account_type VARCHAR(50) NOT NULL DEFAULT 'cash',
  balance DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  context VARCHAR(20) NOT NULL DEFAULT 'business',
  low_balance_threshold DECIMAL(12,2) DEFAULT NULL,
  alert_phone VARCHAR(20) DEFAULT NULL,
  last_alert_sent_at DATETIME DEFAULT NULL,
  account_number VARCHAR(50) DEFAULT NULL,
  bank_branch VARCHAR(150) DEFAULT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  owner_member_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* activity_logs */
CREATE TABLE IF NOT EXISTS `activity_logs` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(80) NOT NULL,
  module VARCHAR(80) DEFAULT NULL,
  details TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_al_user (user_id),
  INDEX idx_al_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* app_settings */
CREATE TABLE IF NOT EXISTS `app_settings` (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* attendance */
CREATE TABLE IF NOT EXISTS `attendance` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT DEFAULT NULL,
  att_date DATE DEFAULT NULL,
  check_in TIME DEFAULT NULL,
  check_out TIME DEFAULT NULL,
  status ENUM('Present','Absent','Leave','Late') DEFAULT 'Present',
  KEY employee_id (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* bank_loans */
CREATE TABLE IF NOT EXISTS `bank_loans` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_name VARCHAR(150) NOT NULL,
  bank_name VARCHAR(150) NOT NULL,
  loan_type VARCHAR(50) NOT NULL DEFAULT 'ব্যবসায়িক লোন',
  principal_amount DECIMAL(14,2) NOT NULL,
  paid_principal DECIMAL(14,2) NOT NULL DEFAULT 0,
  interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  loan_date DATE NOT NULL,
  account_id INT NULL,
  tenure_months INT NOT NULL DEFAULT 0,
  installment_count INT NOT NULL DEFAULT 0,
  installment_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  first_installment_date DATE NULL,
  last_installment_date DATE NULL,
  processing_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_charges DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes VARCHAR(500) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  closed_date DATE NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_status (status),
  INDEX idx_bank (bank_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* bill_collections */
CREATE TABLE IF NOT EXISTS `bill_collections` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  partner_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  month_year VARCHAR(40) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_bc_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* bill_partners */
CREATE TABLE IF NOT EXISTS `bill_partners` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  monthly_share DECIMAL(12,2) NOT NULL DEFAULT 0,
  phone VARCHAR(30) DEFAULT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* brands */
CREATE TABLE IF NOT EXISTS `brands` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* cash_closing */
CREATE TABLE IF NOT EXISTS `cash_closing` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  closing_date DATE NOT NULL,
  system_balance DECIMAL(12,2) NOT NULL,
  counted_balance DECIMAL(12,2) NOT NULL,
  difference DECIMAL(12,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* categories */
CREATE TABLE IF NOT EXISTS `categories` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  is_partnership_shared TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* contact_groups */
CREATE TABLE IF NOT EXISTS `contact_groups` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_name VARCHAR(120) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* contacts */
CREATE TABLE IF NOT EXISTS `contacts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NULL,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  address TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ct_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* critical_action_otps */
CREATE TABLE IF NOT EXISTS `critical_action_otps` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action_key VARCHAR(40) NOT NULL,
  otp_hash VARCHAR(64) NOT NULL,
  channel VARCHAR(20) NOT NULL,
  destination VARCHAR(120) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_action (user_id, action_key, used, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* customer_areas */
CREATE TABLE IF NOT EXISTS `customer_areas` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* customer_call_log */
CREATE TABLE IF NOT EXISTS `customer_call_log` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  log_date DATE NOT NULL,
  outcome VARCHAR(40) NOT NULL DEFAULT 'called',
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_customer_date (customer_id, log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* customer_groups */
CREATE TABLE IF NOT EXISTS `customer_groups` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* customer_ledger */
CREATE TABLE IF NOT EXISTS `customer_ledger` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  txn_date DATE NOT NULL,
  module_type VARCHAR(30) NOT NULL,
  ref_no VARCHAR(50) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  debit DECIMAL(12,2) NOT NULL DEFAULT 0,
  credit DECIMAL(12,2) NOT NULL DEFAULT 0,
  running_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  balance_field VARCHAR(30) NOT NULL DEFAULT 'receivable_balance',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* customer_payments */
CREATE TABLE IF NOT EXISTS `customer_payments` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_date DATE NOT NULL,
  method VARCHAR(30) NOT NULL DEFAULT 'CASH',
  reference_no VARCHAR(50) DEFAULT NULL,
  note TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* customers */
CREATE TABLE IF NOT EXISTS `customers` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_code VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  alt_phone VARCHAR(20) DEFAULT NULL,
  nid VARCHAR(30) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  father_name VARCHAR(150) DEFAULT NULL,
  blood_group VARCHAR(10) DEFAULT NULL,
  is_customer TINYINT(1) NOT NULL DEFAULT 1,
  is_supplier TINYINT(1) NOT NULL DEFAULT 0,
  is_installment TINYINT(1) NOT NULL DEFAULT 0,
  is_rental TINYINT(1) NOT NULL DEFAULT 0,
  credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
  receivable_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  payable_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  rental_deposit_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  ai_credit_score INT NOT NULL DEFAULT 70,
  ai_risk_level VARCHAR(20) NOT NULL DEFAULT 'Normal',
  notes TEXT DEFAULT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  due_watch TINYINT(1) NOT NULL DEFAULT 0,
  due_watch_on DATE DEFAULT NULL,
  next_due_date DATE DEFAULT NULL,
  next_due_amount DECIMAL(12,2) NULL DEFAULT NULL,
  due_note VARCHAR(255) DEFAULT NULL,
  is_borrower TINYINT(1) NOT NULL DEFAULT 0,
  guarantor_name VARCHAR(150) DEFAULT NULL,
  guarantor_phone VARCHAR(20) DEFAULT NULL,
  group_id INT DEFAULT NULL,
  area_id INT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* dashboard_banners */
CREATE TABLE IF NOT EXISTS `dashboard_banners` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  image_name VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* departments */
CREATE TABLE IF NOT EXISTS `departments` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dept_name VARCHAR(100) NOT NULL,
  total_employees INT DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* display_widgets */
CREATE TABLE IF NOT EXISTS `display_widgets` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(20) NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  title VARCHAR(200) NOT NULL DEFAULT '',
  message TEXT,
  icon_key VARCHAR(40) NOT NULL DEFAULT 'megaphone',
  icon_color VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
  icon_bg VARCHAR(20) NOT NULL DEFAULT '#0B1E4D',
  text_color VARCHAR(20) NOT NULL DEFAULT '#1B5E20',
  bg_color VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
  accent_color VARCHAR(20) NOT NULL DEFAULT '#FFD600',
  digit_color VARCHAR(20) NOT NULL DEFAULT '#0B1E4D',
  digit_bg VARCHAR(20) NOT NULL DEFAULT '#FFFFFF',
  label_color VARCHAR(20) NOT NULL DEFAULT '#E2E8F0',
  digit_font_size INT NOT NULL DEFAULT 14,
  digit_font_weight VARCHAR(10) NOT NULL DEFAULT '800',
  digit_font_family VARCHAR(120) NOT NULL DEFAULT '',
  font_size INT NOT NULL DEFAULT 13,
  font_weight VARCHAR(10) NOT NULL DEFAULT '700',
  speed_seconds INT NOT NULL DEFAULT 22,
  direction VARCHAR(10) NOT NULL DEFAULT 'left',
  ends_at DATETIME NULL,
  ended_message VARCHAR(255) NOT NULL DEFAULT 'সময় শেষ হয়েছে',
  hide_when_ended TINYINT(1) NOT NULL DEFAULT 0,
  show_days TINYINT(1) NOT NULL DEFAULT 1,
  show_hours TINYINT(1) NOT NULL DEFAULT 1,
  show_minutes TINYINT(1) NOT NULL DEFAULT 1,
  show_seconds TINYINT(1) NOT NULL DEFAULT 1,
  box_style VARCHAR(20) NOT NULL DEFAULT 'boxed',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY kind_enabled (kind, is_enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* employees */
CREATE TABLE IF NOT EXISTS `employees` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  emp_id VARCHAR(30) NULL,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  department VARCHAR(100) DEFAULT NULL,
  designation VARCHAR(100) DEFAULT NULL,
  joining_date DATE NULL,
  photo VARCHAR(255) DEFAULT NULL,
  salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  department_id INT NULL DEFAULT NULL,
  salary_type VARCHAR(20) NOT NULL DEFAULT 'monthly',
  employee_code VARCHAR(30) NULL DEFAULT NULL,
  service_share_percent DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  worker_type VARCHAR(40) NOT NULL DEFAULT 'artisan',
  payment_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
  advance_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_emp_code (employee_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* engineers */
CREATE TABLE IF NOT EXISTS `engineers` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL DEFAULT NULL,
  employee_id INT NULL DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* expense_categories */
CREATE TABLE IF NOT EXISTS `expense_categories` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* expenses */
CREATE TABLE IF NOT EXISTS `expenses` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(120) NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  account_id INT NULL,
  expense_date DATE NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_exp_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* family_access */
CREATE TABLE IF NOT EXISTS `family_access` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  role_key VARCHAR(20) NOT NULL DEFAULT 'member',
  can_health TINYINT(1) NOT NULL DEFAULT 1,
  can_money TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_book_folds */
CREATE TABLE IF NOT EXISTS `family_book_folds` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  page_no INT NOT NULL,
  note VARCHAR(160) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY book_fold (book_id, page_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_book_pages */
CREATE TABLE IF NOT EXISTS `family_book_pages` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  page_no INT NOT NULL,
  image_file VARCHAR(255) NOT NULL DEFAULT '',
  ocr_text MEDIUMTEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY book_page (book_id, page_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_books */
CREATE TABLE IF NOT EXISTS `family_books` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  title VARCHAR(200) NOT NULL,
  author VARCHAR(160) DEFAULT NULL,
  publisher VARCHAR(160) DEFAULT NULL,
  subject VARCHAR(80) DEFAULT NULL,
  language VARCHAR(40) DEFAULT NULL,
  copies INT NOT NULL DEFAULT 1,
  buy_date DATE NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  source VARCHAR(160) DEFAULT NULL,
  location_note VARCHAR(160) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_contacts */
CREATE TABLE IF NOT EXISTS `family_contacts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NULL,
  name VARCHAR(120) NOT NULL,
  mobile VARCHAR(30) DEFAULT NULL,
  alt_mobile VARCHAR(30) DEFAULT NULL,
  address TEXT,
  email VARCHAR(120) DEFAULT NULL,
  profession VARCHAR(120) DEFAULT NULL,
  organization VARCHAR(120) DEFAULT NULL,
  relation_note VARCHAR(80) DEFAULT NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY group_id (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_doctors */
CREATE TABLE IF NOT EXISTS `family_doctors` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  name VARCHAR(120) NOT NULL,
  specialist VARCHAR(80) DEFAULT NULL,
  hospital VARCHAR(160) DEFAULT NULL,
  mobile VARCHAR(30) DEFAULT NULL,
  address TEXT,
  chamber_time VARCHAR(160) DEFAULT NULL,
  appointment_date DATE NULL,
  last_visit DATE NULL,
  visit_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  next_visit DATE NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY member_id (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_documents */
CREATE TABLE IF NOT EXISTS `family_documents` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  doctor_id INT NULL,
  title VARCHAR(200) NOT NULL,
  kind VARCHAR(80) DEFAULT 'অন্যান্য',
  file_name VARCHAR(255) DEFAULT NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_events */
CREATE TABLE IF NOT EXISTS `family_events` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  kind VARCHAR(40) NOT NULL DEFAULT 'অন্যান্য',
  event_date DATE NOT NULL,
  member_id INT NULL,
  ref_kind VARCHAR(40) DEFAULT NULL,
  ref_id INT NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_expenses */
CREATE TABLE IF NOT EXISTS `family_expenses` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  exp_date DATE NULL,
  category VARCHAR(80) DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  spent_by VARCHAR(80) DEFAULT NULL,
  purpose VARCHAR(200) DEFAULT NULL,
  pay_method VARCHAR(40) DEFAULT 'নগদ',
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY exp_date (exp_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_groups */
CREATE TABLE IF NOT EXISTS `family_groups` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_inventory */
CREATE TABLE IF NOT EXISTS `family_inventory` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(80) DEFAULT NULL,
  qty VARCHAR(40) DEFAULT '1',
  buy_date DATE NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  condition_note VARCHAR(80) DEFAULT 'ভালো',
  location_note VARCHAR(160) DEFAULT NULL,
  warranty_until DATE NULL,
  receipt_file VARCHAR(255) DEFAULT NULL,
  maintain_date DATE NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_med_buys */
CREATE TABLE IF NOT EXISTS `family_med_buys` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  medicine_id INT NULL,
  member_id INT NULL,
  buy_date DATE NULL,
  name VARCHAR(160) NOT NULL,
  qty DECIMAL(10,2) NOT NULL DEFAULT 0,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  pharmacy VARCHAR(160) DEFAULT NULL,
  invoice_file VARCHAR(255) DEFAULT NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_medicines */
CREATE TABLE IF NOT EXISTS `family_medicines` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  doctor_id INT NULL,
  name VARCHAR(160) NOT NULL,
  generic_name VARCHAR(160) DEFAULT NULL,
  company VARCHAR(120) DEFAULT NULL,
  strength VARCHAR(40) DEFAULT NULL,
  bought_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock DECIMAL(10,2) NOT NULL DEFAULT 0,
  when_to_take VARCHAR(160) DEFAULT NULL,
  times_per_day INT NOT NULL DEFAULT 1,
  days_for INT NOT NULL DEFAULT 0,
  rx_date DATE NULL,
  refill_date DATE NULL,
  expiry_date DATE NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY member_id (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_members */
CREATE TABLE IF NOT EXISTS `family_members` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  relation_name VARCHAR(80) DEFAULT NULL,
  birth_date DATE NULL,
  mobile VARCHAR(30) DEFAULT NULL,
  address TEXT,
  notes TEXT,
  emergency_name VARCHAR(120) DEFAULT NULL,
  emergency_phone VARCHAR(30) DEFAULT NULL,
  important_info TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_notes */
CREATE TABLE IF NOT EXISTS `family_notes` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  title VARCHAR(160) DEFAULT NULL,
  body TEXT,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* family_shopping */
CREATE TABLE IF NOT EXISTS `family_shopping` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(80) DEFAULT NULL,
  qty VARCHAR(40) DEFAULT NULL,
  est_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  priority VARCHAR(20) DEFAULT 'মাঝারি',
  buyer VARCHAR(80) DEFAULT NULL,
  shop_name VARCHAR(160) DEFAULT NULL,
  is_bought TINYINT(1) NOT NULL DEFAULT 0,
  buy_date DATE NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* idempotency_keys */
CREATE TABLE IF NOT EXISTS `idempotency_keys` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scope VARCHAR(40) NOT NULL,
  token VARCHAR(80) NOT NULL,
  ref_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_scope_token (scope, token),
  INDEX idx_scope_created (scope, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* installment_contracts */
CREATE TABLE IF NOT EXISTS `installment_contracts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  contract_no VARCHAR(30) NOT NULL,
  customer_id INT NOT NULL,
  product_details VARCHAR(255) NOT NULL,
  product_condition VARCHAR(30) NOT NULL DEFAULT 'NEW',
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  down_payment DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_installments INT NOT NULL DEFAULT 1,
  installment_type VARCHAR(20) NOT NULL DEFAULT 'MONTHLY',
  remaining_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* invoice_layout_templates */
CREATE TABLE IF NOT EXISTS `invoice_layout_templates` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  layout_json LONGTEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* invoice_settings */
CREATE TABLE IF NOT EXISTS `invoice_settings` (
  id INT PRIMARY KEY DEFAULT 1,
  footer_note VARCHAR(255) DEFAULT 'ধন্যবাদ,
  আবার আসবেন!',
  show_discount TINYINT(1) NOT NULL DEFAULT 1,
  show_payment_method TINYINT(1) NOT NULL DEFAULT 1,
  show_address TINYINT(1) NOT NULL DEFAULT 1,
  paper_width VARCHAR(10) NOT NULL DEFAULT '80mm',
  accent_color VARCHAR(20) NOT NULL DEFAULT 'green'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* leave_requests */
CREATE TABLE IF NOT EXISTS `leave_requests` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  leave_type VARCHAR(50) NOT NULL DEFAULT 'সাধারণ ছুটি',
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  total_days INT NOT NULL DEFAULT 1,
  reason VARCHAR(255) NULL,
  status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  decided_by INT NULL,
  decided_at DATETIME NULL,
  KEY employee_id (employee_id),
  KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ledger_provenance */
CREATE TABLE IF NOT EXISTS `ledger_provenance` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(20) NOT NULL,
  ref_id INT NOT NULL,
  stamp VARCHAR(24) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  prev_hash CHAR(64) NOT NULL DEFAULT '',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  label VARCHAR(80) NOT NULL DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_stamp (stamp),
  UNIQUE KEY uniq_kind_ref (kind, ref_id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* ledger_provenance_meta */
CREATE TABLE IF NOT EXISTS `ledger_provenance_meta` (
  id INT PRIMARY KEY,
  pepper VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_applications */
CREATE TABLE IF NOT EXISTS `loan_applications` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  product_id INT NOT NULL,
  requested_amount DECIMAL(12,2) NOT NULL,
  requested_term_months INT NOT NULL,
  purpose VARCHAR(255) DEFAULT NULL,
  guarantor_name VARCHAR(150) DEFAULT NULL,
  guarantor_phone VARCHAR(20) DEFAULT NULL,
  status ENUM('pending','approved','rejected','disbursed','cancelled') NOT NULL DEFAULT 'pending',
  applied_by INT DEFAULT NULL,
  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  decided_by INT DEFAULT NULL,
  decided_at DATETIME DEFAULT NULL,
  decision_note VARCHAR(255) DEFAULT NULL,
  resulting_loan_id INT DEFAULT NULL,
  INDEX(customer_id),
  INDEX(product_id),
  INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_fees */
CREATE TABLE IF NOT EXISTS `loan_fees` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  fee_type ENUM('processing','penalty','other') NOT NULL DEFAULT 'other',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL,
  charged_by INT DEFAULT NULL,
  charged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_installments */
CREATE TABLE IF NOT EXISTS `loan_installments` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  installment_no INT NOT NULL,
  due_date DATE NOT NULL,
  installment_amount DECIMAL(14,2) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  paid_date DATE NULL,
  INDEX idx_loan (loan_id),
  INDEX idx_due (due_date),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_products */
CREATE TABLE IF NOT EXISTS `loan_products` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  interest_type ENUM('flat','reducing') NOT NULL DEFAULT 'flat',
  default_term_months INT NOT NULL DEFAULT 12,
  min_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  max_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  processing_fee_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  penalty_rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_recovery_actions */
CREATE TABLE IF NOT EXISTS `loan_recovery_actions` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  action_type ENUM('call','visit','notice','legal','other') NOT NULL DEFAULT 'call',
  action_date DATE NOT NULL,
  notes TEXT DEFAULT NULL,
  done_by INT DEFAULT NULL,
  next_follow_up_date DATE DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_repayment_schedule */
CREATE TABLE IF NOT EXISTS `loan_repayment_schedule` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  installment_no INT NOT NULL,
  due_date DATE NOT NULL,
  due_principal DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_interest DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('pending','partial','paid','overdue') NOT NULL DEFAULT 'pending',
  INDEX(loan_id),
  INDEX(due_date),
  INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_restructure_log */
CREATE TABLE IF NOT EXISTS `loan_restructure_log` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  old_amount DECIMAL(12,2) NOT NULL,
  old_term_months INT NOT NULL,
  old_interest_rate DECIMAL(5,2) NOT NULL,
  new_amount DECIMAL(12,2) NOT NULL,
  new_term_months INT NOT NULL,
  new_interest_rate DECIMAL(5,2) NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  approved_by INT DEFAULT NULL,
  restructured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_settings */
CREATE TABLE IF NOT EXISTS `loan_settings` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grace_period_days INT NOT NULL DEFAULT 3,
  default_penalty_rate_percent DECIMAL(5,2) NOT NULL DEFAULT 2,
  require_guarantor TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_settlements */
CREATE TABLE IF NOT EXISTS `loan_settlements` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  settlement_type ENUM('full_payment','early_settlement','written_off') NOT NULL DEFAULT 'full_payment',
  waived_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  note VARCHAR(255) DEFAULT NULL,
  settled_by INT DEFAULT NULL,
  settled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loan_transactions */
CREATE TABLE IF NOT EXISTS `loan_transactions` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  loan_id INT NOT NULL,
  txn_type VARCHAR(20) NOT NULL,
  txn_date DATE NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL,
  principal_part DECIMAL(14,2) NOT NULL DEFAULT 0,
  interest_part DECIMAL(14,2) NOT NULL DEFAULT 0,
  charge_part DECIMAL(14,2) NOT NULL DEFAULT 0,
  account_id INT NULL,
  reference_no VARCHAR(100) NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_loan (loan_id),
  INDEX idx_type (txn_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* loans */
CREATE TABLE IF NOT EXISTS `loans` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  person_name VARCHAR(150) DEFAULT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'given',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  customer_id INT DEFAULT NULL,
  product_id INT DEFAULT NULL,
  application_id INT DEFAULT NULL,
  interest_rate DECIMAL(5,2) DEFAULT NULL,
  interest_type VARCHAR(20) DEFAULT NULL,
  term_months INT DEFAULT NULL,
  disbursed_at DATETIME DEFAULT NULL,
  disbursed_by INT DEFAULT NULL,
  disbursement_account_id INT DEFAULT NULL,
  next_due_date DATE DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_loan_status (status),
  INDEX idx_loan_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* login_attempts */
CREATE TABLE IF NOT EXISTS `login_attempts` (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(190) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_time (username, attempted_at),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* merchants */
CREATE TABLE IF NOT EXISTS `merchants` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* partner_acct_audit */
CREATE TABLE IF NOT EXISTS `partner_acct_audit` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NULL,
  action VARCHAR(40) NOT NULL,
  entity VARCHAR(40) NOT NULL,
  entity_id INT NULL,
  period_ym CHAR(7) NULL,
  details TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* partner_acct_expenses */
CREATE TABLE IF NOT EXISTS `partner_acct_expenses` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'other',
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  period_ym CHAR(7) NOT NULL,
  reference VARCHAR(80) NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_period (period_ym),
  INDEX idx_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* partner_acct_months */
CREATE TABLE IF NOT EXISTS `partner_acct_months` (
  period_ym CHAR(7) PRIMARY KEY,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  closed_by INT NULL,
  closed_at DATETIME NULL,
  note VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* partner_acct_payments */
CREATE TABLE IF NOT EXISTS `partner_acct_payments` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  partner_id INT NOT NULL,
  payment_date DATE NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
  period_ym CHAR(7) NOT NULL,
  reference VARCHAR(80) NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_partner (partner_id),
  INDEX idx_period (period_ym)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* partner_capital_transactions */
CREATE TABLE IF NOT EXISTS `partner_capital_transactions` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  partner_id INT NOT NULL,
  type VARCHAR(25) NOT NULL DEFAULT 'investment',
  amount DECIMAL(14,2) NOT NULL,
  transaction_date DATE NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_partner (partner_id),
  INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* partner_withdrawals */
CREATE TABLE IF NOT EXISTS `partner_withdrawals` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  partner_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  withdrawal_date DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* partners */
CREATE TABLE IF NOT EXISTS `partners` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  share_percentage DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  partner_type VARCHAR(30) NOT NULL DEFAULT 'equity',
  phone VARCHAR(20) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  initial_capital DECIMAL(14,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* partnership_adjustments */
CREATE TABLE IF NOT EXISTS `partnership_adjustments` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  amount DECIMAL(12,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  adjustment_date DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* partnership_openings */
CREATE TABLE IF NOT EXISTS `partnership_openings` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  partner_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  as_of_date DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* password_reset_otps */
CREATE TABLE IF NOT EXISTS `password_reset_otps` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  otp_hash VARCHAR(64) NOT NULL,
  channel VARCHAR(20) NOT NULL,
  destination VARCHAR(120) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_otp (user_id, used, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* password_resets */
CREATE TABLE IF NOT EXISTS `password_resets` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_token (user_id, used, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* personal_categories */
CREATE TABLE IF NOT EXISTS `personal_categories` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'expense',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* pf_categories */
CREATE TABLE IF NOT EXISTS `pf_categories` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  parent_group VARCHAR(50) NOT NULL DEFAULT 'অন্যান্য',
  type VARCHAR(10) NOT NULL DEFAULT 'expense',
  icon VARCHAR(10) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* pf_family_members */
CREATE TABLE IF NOT EXISTS `pf_family_members` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'other',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* pf_merchants */
CREATE TABLE IF NOT EXISTS `pf_merchants` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* pos_title_line */
CREATE TABLE IF NOT EXISTS `pos_title_line` (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  line_text TEXT NOT NULL,
  text_color VARCHAR(20) NOT NULL DEFAULT '#0D47A1',
  font_size INT NOT NULL DEFAULT 13,
  font_weight VARCHAR(10) NOT NULL DEFAULT '800',
  font_family VARCHAR(120) NOT NULL DEFAULT '',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* positions */
CREATE TABLE IF NOT EXISTS `positions` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  position_name VARCHAR(100) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  UNIQUE KEY uniq_position_name (position_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* product_serials */
CREATE TABLE IF NOT EXISTS `product_serials` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NULL,
  serial_no VARCHAR(80) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'in_stock',
  customer_id INT NULL,
  sale_id INT NULL,
  job_id INT NULL,
  warranty_months INT NOT NULL DEFAULT 0,
  warranty_expiry DATE NULL,
  notes VARCHAR(255) NULL,
  store_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_serial_no (serial_no),
  INDEX idx_serial_product (product_id),
  INDEX idx_serial_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* product_units */
CREATE TABLE IF NOT EXISTS `product_units` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  unit_name VARCHAR(40) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* products */
CREATE TABLE IF NOT EXISTS `products` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(64) DEFAULT NULL,
  name VARCHAR(200) NOT NULL,
  category_id INT NULL,
  brand_id INT NULL,
  unit_id INT NULL,
  supplier_id INT NULL,
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  dealer_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock DECIMAL(12,3) NOT NULL DEFAULT 0,
  min_stock DECIMAL(12,3) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  barcode VARCHAR(64) DEFAULT NULL,
  manufacturer_name VARCHAR(150) DEFAULT NULL,
  sub_category VARCHAR(100) DEFAULT NULL,
  product_type VARCHAR(32) NOT NULL DEFAULT 'part',
  short_description TEXT NULL,
  description TEXT NULL,
  highlights TEXT NULL,
  tags VARCHAR(255) DEFAULT NULL,
  internal_notes TEXT NULL,
  reference_no VARCHAR(80) DEFAULT NULL,
  vat_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
  discount_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
  moq DECIMAL(10,2) NOT NULL DEFAULT 1,
  warranty_months INT DEFAULT NULL,
  warranty_type VARCHAR(80) DEFAULT NULL,
  return_policy TEXT NULL,
  model_name VARCHAR(120) DEFAULT NULL,
  size_label VARCHAR(80) DEFAULT NULL,
  color_label VARCHAR(80) DEFAULT NULL,
  weight_kg DECIMAL(10,3) DEFAULT NULL,
  country_origin VARCHAR(80) DEFAULT NULL,
  hs_code VARCHAR(32) DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  seo_title VARCHAR(180) DEFAULT NULL,
  seo_keywords VARCHAR(255) DEFAULT NULL,
  is_old_battery TINYINT(1) NOT NULL DEFAULT 0,
  shelf_location VARCHAR(100) DEFAULT NULL,
  oem_code VARCHAR(80) DEFAULT NULL,
  supplier_code VARCHAR(80) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_sku (sku),
  INDEX idx_prod_name (name),
  INDEX idx_prod_cat (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* profit_allocation_details */
CREATE TABLE IF NOT EXISTS `profit_allocation_details` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  allocation_id INT NOT NULL,
  partner_id INT NOT NULL,
  share_percentage_used DECIMAL(5,2) NOT NULL DEFAULT 0,
  allocated_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  INDEX idx_pad_alloc (allocation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* profit_allocations */
CREATE TABLE IF NOT EXISTS `profit_allocations` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_label VARCHAR(80) NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  gross_profit DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_expenses DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_adjustments DECIMAL(14,2) NOT NULL DEFAULT 0,
  net_profit DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'proposed',
  created_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pa_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* purchase_drafts */
CREATE TABLE IF NOT EXISTS `purchase_drafts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  draft_number VARCHAR(40) NOT NULL DEFAULT '',
  supplier_id INT NULL,
  memo_no VARCHAR(50) NULL,
  purchase_date DATE NULL,
  notes VARCHAR(500) NULL,
  items_json MEDIUMTEXT NOT NULL,
  expenses_json MEDIUMTEXT NULL,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  account_id INT NULL,
  payment_type VARCHAR(10) NOT NULL DEFAULT 'cash',
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_by INT NULL,
  store_id INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pd_status (status),
  INDEX idx_pd_user (created_by),
  INDEX idx_pd_store (store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* purchase_expenses */
CREATE TABLE IF NOT EXISTS `purchase_expenses` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  memo_no VARCHAR(50) NULL,
  supplier_id INT NULL,
  expense_type VARCHAR(50) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_memo (memo_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* purchase_return_items */
CREATE TABLE IF NOT EXISTS `purchase_return_items` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  product_id INT NOT NULL,
  purchased_qty DECIMAL(10,2) NULL,
  returned_qty DECIMAL(10,2) NOT NULL,
  free_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  INDEX idx_return (return_id),
  INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* purchase_return_logs */
CREATE TABLE IF NOT EXISTS `purchase_return_logs` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  action VARCHAR(30) NOT NULL,
  old_status VARCHAR(20) NULL,
  new_status VARCHAR(20) NULL,
  comment VARCHAR(255) NULL,
  user_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_return (return_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* purchase_returns */
CREATE TABLE IF NOT EXISTS `purchase_returns` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_no VARCHAR(30) NOT NULL UNIQUE,
  credit_note_no VARCHAR(30) NULL,
  supplier_id INT NULL,
  supplier_name VARCHAR(100) NULL,
  memo_no VARCHAR(50) NULL,
  return_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  refund_type VARCHAR(20) NOT NULL DEFAULT 'supplier_credit',
  account_id INT NULL,
  inspection_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  inspection_note VARCHAR(255) NULL,
  supplier_response VARCHAR(20) NOT NULL DEFAULT 'pending',
  supplier_response_note VARCHAR(255) NULL,
  dispatch_date DATE NULL,
  dispatch_note VARCHAR(255) NULL,
  reason VARCHAR(255) NULL,
  notes VARCHAR(500) NULL,
  reason_category VARCHAR(30) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_by INT NULL,
  reviewed_by INT NULL,
  approved_by INT NULL,
  reviewed_at DATETIME NULL,
  approved_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_status (status),
  INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* purchases */
CREATE TABLE IF NOT EXISTS `purchases` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(10,2) NOT NULL,
  purchase_price DECIMAL(10,2) NOT NULL,
  wholesale_price DECIMAL(10,2) NOT NULL,
  sale_price DECIMAL(10,2) NOT NULL,
  total_cost DECIMAL(10,2) NOT NULL,
  account_id INT NOT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  memo_no VARCHAR(50) NULL,
  dealer_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_type VARCHAR(10) NOT NULL DEFAULT 'cash',
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes VARCHAR(500) NULL,
  store_id INT NULL,
  INDEX(product_id),
  INDEX(supplier_id),
  INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* rental_audit */
CREATE TABLE IF NOT EXISTS `rental_audit` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  table_name VARCHAR(40) NOT NULL,
  row_id INT NOT NULL,
  action VARCHAR(20) NOT NULL,
  old_json TEXT DEFAULT NULL,
  new_json TEXT DEFAULT NULL,
  user_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_raud_row (table_name, row_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* rental_contracts */
CREATE TABLE IF NOT EXISTS `rental_contracts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rental_no VARCHAR(30) NOT NULL,
  customer_id INT NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  security_deposit DECIMAL(12,2) NOT NULL DEFAULT 0,
  rent_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
  rate_type VARCHAR(20) NOT NULL DEFAULT 'DAILY',
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  last_charged_date DATE DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* rental_owner_payments */
CREATE TABLE IF NOT EXISTS `rental_owner_payments` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method VARCHAR(30) NOT NULL DEFAULT 'CASH',
  paid_at DATETIME NOT NULL,
  ref VARCHAR(80) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  user_id INT DEFAULT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'posted',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rop_owner (owner_id),
  INDEX idx_rop_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* rental_owners */
CREATE TABLE IF NOT EXISTS `rental_owners` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  pay_cycle VARCHAR(16) NOT NULL DEFAULT 'monthly',
  pay_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  custom_days INT NOT NULL DEFAULT 7,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  vehicle_no VARCHAR(40) DEFAULT NULL,
  vehicle_name VARCHAR(120) DEFAULT NULL,
  entry_date DATE DEFAULT NULL,
  INDEX idx_ro_status (status),
  INDEX idx_ro_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* rental_vehicles */
CREATE TABLE IF NOT EXISTS `rental_vehicles` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  vehicle_no VARCHAR(40) NOT NULL,
  vehicle_name VARCHAR(120) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rv_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* role_permissions */
CREATE TABLE IF NOT EXISTS `role_permissions` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_key VARCHAR(50) NOT NULL,
  module_key VARCHAR(50) NOT NULL,
  UNIQUE KEY unique_role_module (role_key, module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* salaries */
CREATE TABLE IF NOT EXISTS `salaries` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  month_year VARCHAR(20) NOT NULL,
  account_id INT NULL,
  payment_date DATE NULL,
  base_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  bonus_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  deduction_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  snacks_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  pay_mode_snapshot VARCHAR(20) NULL DEFAULT NULL,
  pay_rule_id INT NULL DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sal_emp (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* employee_advances — সকালের অগ্রিম, কর্মীর নামে সাবেক */
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* sale_exchange_items */
CREATE TABLE IF NOT EXISTS `sale_exchange_items` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  product_id INT NOT NULL,
  item_name VARCHAR(255) NOT NULL DEFAULT '',
  qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  exchange_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (sale_id),
  INDEX (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* sale_items */
CREATE TABLE IF NOT EXISTS `sale_items` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  product_id INT NULL DEFAULT NULL,
  item_name VARCHAR(255) NULL DEFAULT NULL,
  qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  legacy_key VARCHAR(96) NULL DEFAULT NULL,
  UNIQUE KEY uniq_sale_item_legacy_key (legacy_key),
  INDEX idx_si_sale (sale_id),
  INDEX idx_si_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* sale_payments */
CREATE TABLE IF NOT EXISTS `sale_payments` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  account_id INT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  reference_number VARCHAR(100) NULL,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'posted',
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (sale_id),
  INDEX (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* sale_return_items */
CREATE TABLE IF NOT EXISTS `sale_return_items` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_id INT NOT NULL,
  product_id INT NOT NULL,
  original_qty DECIMAL(10,2) NULL,
  returned_qty DECIMAL(10,2) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  line_reason VARCHAR(100) NULL,
  line_total DECIMAL(14,2) NOT NULL,
  is_replacement TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_return (return_id),
  INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* sale_returns */
CREATE TABLE IF NOT EXISTS `sale_returns` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NULL,
  product_id INT DEFAULT NULL,
  qty DECIMAL(10,2) DEFAULT NULL,
  return_type VARCHAR(30) NOT NULL DEFAULT 'with_invoice',
  customer_name VARCHAR(100) DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  total_return_amount DECIMAL(12,2) NULL,
  account_id INT NULL,
  exchange_product_id INT DEFAULT NULL,
  exchange_qty DECIMAL(10,2) DEFAULT NULL,
  price_difference DECIMAL(12,2) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sr_sale (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* sale_returns_v2 */
CREATE TABLE IF NOT EXISTS `sale_returns_v2` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  return_no VARCHAR(30) NOT NULL UNIQUE,
  sale_id INT NULL,
  customer_id INT NULL,
  customer_name VARCHAR(100) NULL,
  sales_type VARCHAR(20) NOT NULL DEFAULT 'retail',
  return_type VARCHAR(30) NOT NULL DEFAULT 'invoice_based',
  return_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  exchange_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  customer_adjustment DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  account_id INT NULL,
  reason VARCHAR(255) NULL,
  refund_method VARCHAR(20) NOT NULL DEFAULT 'customer_balance',
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes VARCHAR(500) NULL,
  return_date DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_by INT NULL,
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  INDEX idx_status (status),
  INDEX idx_customer (customer_id),
  INDEX idx_sale (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* sale_services */
CREATE TABLE IF NOT EXISTS `sale_services` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  engineer_id INT NULL,
  service_type VARCHAR(255) NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  INDEX (sale_id),
  INDEX (engineer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* sale_warranties */
CREATE TABLE IF NOT EXISTS `sale_warranties` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NULL,
  product_id INT NULL,
  serial_no VARCHAR(80) NOT NULL,
  expiry_date DATE NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sw_serial (serial_no),
  INDEX idx_sw_sale (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* sales */
CREATE TABLE IF NOT EXISTS `sales` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(50) NULL,
  customer_id INT NULL,
  customer_type VARCHAR(20) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  total_bill DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_payable DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  account_id INT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  sale_date DATETIME NULL,
  vat_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  service_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  rounding_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  offline_client_ref VARCHAR(80) NULL,
  guest_customer_name VARCHAR(150) NULL,
  guest_customer_phone VARCHAR(30) NULL,
  store_id INT NULL,
  exchange_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  cash_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  INDEX(status),
  INDEX(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* service_commissions */
CREATE TABLE IF NOT EXISTS `service_commissions` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  sale_id INT NULL,
  service_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  commission_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY employee_id (employee_id),
  KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* settings */
CREATE TABLE IF NOT EXISTS `settings` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shop_name VARCHAR(150) NOT NULL DEFAULT 'আমার ব্যবসা প্রতিষ্ঠান',
  shop_phone VARCHAR(30) DEFAULT NULL,
  shop_address TEXT DEFAULT NULL,
  shop_banner VARCHAR(255) DEFAULT NULL,
  shop_logo VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  shop_tagline VARCHAR(180) NULL DEFAULT NULL,
  default_pay_account_id INT NULL DEFAULT NULL,
  brand_type_json TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* sms_log */
CREATE TABLE IF NOT EXISTS `sms_log` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(20) NOT NULL,
  message TEXT,
  purpose VARCHAR(100) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'sent',
  response_note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recipient (recipient),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* sms_settings */
CREATE TABLE IF NOT EXISTS `sms_settings` (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  api_url VARCHAR(255) NOT NULL DEFAULT '',
  api_key VARCHAR(255) NOT NULL DEFAULT '',
  sender_id VARCHAR(80) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'inactive',
  channel VARCHAR(20) NOT NULL DEFAULT 'sim'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* sms_outbox */
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

/* stock_count_items */
CREATE TABLE IF NOT EXISTS `stock_count_items` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  count_id INT NOT NULL,
  product_id INT NOT NULL,
  system_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  counted_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  variance DECIMAL(12,3) NOT NULL DEFAULT 0,
  INDEX idx_count_item (count_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* stock_counts */
CREATE TABLE IF NOT EXISTS `stock_counts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  count_no VARCHAR(24) NOT NULL,
  store_id INT NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_count_no (count_no),
  INDEX idx_count_store (store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* stock_movements */
CREATE TABLE IF NOT EXISTS `stock_movements` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  store_id INT NULL,
  qty_in DECIMAL(12,3) NOT NULL DEFAULT 0,
  qty_out DECIMAL(12,3) NOT NULL DEFAULT 0,
  unit_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  movement_type VARCHAR(32) NOT NULL,
  ref_type VARCHAR(32) NOT NULL,
  ref_id INT NOT NULL,
  customer_id INT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id),
  INDEX (ref_type, ref_id),
  INDEX (movement_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* store_stock */
CREATE TABLE IF NOT EXISTS `store_stock` (
  store_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (store_id, product_id),
  INDEX (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* store_transfer_items */
CREATE TABLE IF NOT EXISTS `store_transfer_items` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_id INT NOT NULL,
  product_id INT NOT NULL,
  qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  INDEX idx_tr_item (transfer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* store_transfers */
CREATE TABLE IF NOT EXISTS `store_transfers` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_no VARCHAR(24) NOT NULL,
  from_store_id INT NOT NULL,
  to_store_id INT NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_transfer_no (transfer_no),
  INDEX idx_tr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* stores */
CREATE TABLE IF NOT EXISTS `stores` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_store_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* suppliers */
CREATE TABLE IF NOT EXISTS `suppliers` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  total_due DECIMAL(12,2) NOT NULL DEFAULT 0,
  credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* system_flags */
CREATE TABLE IF NOT EXISTS `system_flags` (
  flag_name VARCHAR(50) NOT NULL PRIMARY KEY,
  flag_value VARCHAR(50) NOT NULL DEFAULT '1',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* system_routes */
CREATE TABLE IF NOT EXISTS `system_routes` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_slug VARCHAR(120) NOT NULL,
  target_file VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_slug (request_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* theme_settings */
CREATE TABLE IF NOT EXISTS `theme_settings` (
  id INT PRIMARY KEY,
  header_color VARCHAR(20) DEFAULT '#0B1E4D',
  primary_color VARCHAR(20) DEFAULT '#0B1E4D',
  secondary_color VARCHAR(20) DEFAULT '#0E8F52',
  accent_color VARCHAR(20) DEFAULT '#1D9BF0',
  gold_color VARCHAR(20) DEFAULT '#C9A227',
  bg_color VARCHAR(20) DEFAULT '#EAF4FF',
  surface_color VARCHAR(20) DEFAULT '#FFFFFF',
  text_color VARCHAR(20) DEFAULT '#0B1E4D',
  muted_color VARCHAR(20) DEFAULT '#64748B',
  sidebar_color VARCHAR(20) DEFAULT '#FFFFFF',
  border_color VARCHAR(20) DEFAULT '#CFE3F7',
  danger_color VARCHAR(20) DEFAULT '#D32F2F',
  clock_color VARCHAR(20) DEFAULT '#2DD4BF'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* user_permissions */
CREATE TABLE IF NOT EXISTS `user_permissions` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  module_key VARCHAR(50) NOT NULL,
  UNIQUE KEY unique_user_module (user_id, module_key),
  KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* user_roles */
CREATE TABLE IF NOT EXISTS `user_roles` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role_key VARCHAR(50) NOT NULL UNIQUE,
  role_label VARCHAR(100) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* user_stores */
CREATE TABLE IF NOT EXISTS `user_stores` (
  user_id INT NOT NULL,
  store_id INT NOT NULL,
  PRIMARY KEY (user_id, store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* users */
CREATE TABLE IF NOT EXISTS `users` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'cashier',
  email VARCHAR(120) DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  whatsapp VARCHAR(20) DEFAULT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  address VARCHAR(255) DEFAULT NULL,
  document VARCHAR(255) DEFAULT NULL,
  status TINYINT(1) NOT NULL DEFAULT 1,
  auth_version INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL DEFAULT NULL,
  employee_id INT NULL DEFAULT NULL,
  use_custom_perms TINYINT(1) NOT NULL DEFAULT 0,
  removed_at DATETIME DEFAULT NULL,
  removed_by INT DEFAULT NULL,
  removed_username VARCHAR(120) DEFAULT NULL,
  purged_at DATETIME DEFAULT NULL,
  is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* whatsapp_settings */
CREATE TABLE IF NOT EXISTS `whatsapp_settings` (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  api_url VARCHAR(255) NOT NULL DEFAULT 'https://graph.facebook.com/v21.0',
  access_token VARCHAR(500) NOT NULL DEFAULT '',
  phone_number_id VARCHAR(40) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'inactive',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* worker_audit_log */
CREATE TABLE IF NOT EXISTS `worker_audit_log` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id INT NULL,
  employee_id INT NULL,
  old_value TEXT NULL,
  new_value TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY created_at (created_at),
  KEY employee_id (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* worker_earnings */
CREATE TABLE IF NOT EXISTS `worker_earnings` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  engineer_id INT NULL,
  source_type VARCHAR(30) NOT NULL DEFAULT 'sale_service',
  source_id INT NULL,
  sale_id INT NULL,
  order_ref VARCHAR(80) NULL,
  customer_name VARCHAR(120) NULL,
  job_label VARCHAR(255) NULL,
  work_date DATE NOT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  work_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
  rule_label VARCHAR(80) NOT NULL DEFAULT '',
  percent_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
  fixed_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  earning_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  company_share DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  pay_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  locked TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY employee_id (employee_id),
  KEY work_date (work_date),
  KEY sale_id (sale_id),
  KEY pay_status (pay_status),
  UNIQUE KEY uniq_source (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* worker_earnings_settings */
CREATE TABLE IF NOT EXISTS `worker_earnings_settings` (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* worker_payment_allocations */
CREATE TABLE IF NOT EXISTS `worker_payment_allocations` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payment_id INT NOT NULL,
  earning_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  reversed TINYINT(1) NOT NULL DEFAULT 0,
  KEY payment_id (payment_id),
  KEY earning_id (earning_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* worker_payment_rules */
CREATE TABLE IF NOT EXISTS `worker_payment_rules` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  worker_type VARCHAR(40) NOT NULL DEFAULT 'artisan',
  payment_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
  percent_rate DECIMAL(6,2) NOT NULL DEFAULT 50.00,
  fixed_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  fixed_scope VARCHAR(20) NOT NULL DEFAULT 'per_job',
  daily_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
  monthly_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  custom_note VARCHAR(255) NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY employee_id (employee_id),
  KEY effective_from (effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* worker_payments */
CREATE TABLE IF NOT EXISTS `worker_payments` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  method VARCHAR(20) NOT NULL DEFAULT 'cash',
  payment_date DATE NOT NULL,
  account_id INT NULL,
  reference VARCHAR(120) NULL,
  note VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'posted',
  approval_status VARCHAR(20) NOT NULL DEFAULT 'none',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  reverses_id INT NULL,
  reversed_at DATETIME NULL,
  reverse_reason VARCHAR(255) NULL,
  reversed_by INT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY employee_id (employee_id),
  KEY payment_date (payment_date),
  KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* worker_payouts */
CREATE TABLE IF NOT EXISTS `worker_payouts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  account_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payout_date DATE NOT NULL,
  note VARCHAR(255) NULL DEFAULT NULL,
  created_by INT NULL DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY employee_id (employee_id),
  KEY payout_date (payout_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* workshop_job_parts */
CREATE TABLE IF NOT EXISTS `workshop_job_parts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  product_id INT NULL,
  product_name VARCHAR(200) NOT NULL DEFAULT '',
  qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  `reserved` TINYINT(1) NOT NULL DEFAULT 1,
  issued TINYINT(1) NOT NULL DEFAULT 0,
  line_kind VARCHAR(20) NOT NULL DEFAULT 'repair',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_part_job (job_id),
  INDEX idx_part_product (product_id),
  INDEX idx_part_reserve (product_id, `reserved`, issued)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* workshop_jobs */
CREATE TABLE IF NOT EXISTS `workshop_jobs` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_no VARCHAR(24) NOT NULL,
  customer_id INT NULL,
  customer_name VARCHAR(150) NOT NULL DEFAULT '',
  phone VARCHAR(30) NULL,
  item_name VARCHAR(200) NOT NULL DEFAULT '',
  serial_no VARCHAR(80) NULL,
  complaint TEXT NULL,
  diagnosis TEXT NULL,
  engineer_id INT NULL,
  store_id INT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'received',
  labor_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
  parts_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  quote_id INT NULL,
  notes TEXT NULL,
  received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  delivered_at DATETIME NULL,
  created_by INT NULL,
  machine_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_no (job_no),
  INDEX idx_job_status (status),
  INDEX idx_job_customer (customer_id),
  INDEX idx_job_serial (serial_no),
  INDEX idx_job_store (store_id),
  INDEX idx_job_machine (machine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* workshop_machine_parts */
CREATE TABLE IF NOT EXISTS `workshop_machine_parts` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  machine_id INT NOT NULL,
  product_id INT NOT NULL,
  oem_code VARCHAR(80) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wsm_part (machine_id, product_id),
  INDEX idx_wsmp_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* workshop_machines */
CREATE TABLE IF NOT EXISTS `workshop_machines` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  machine_type VARCHAR(40) NOT NULL DEFAULT 'other',
  brand VARCHAR(120) NOT NULL DEFAULT '',
  model VARCHAR(160) NOT NULL DEFAULT '',
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_wsm_type (machine_type),
  INDEX idx_wsm_model (brand, model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* workshop_quote_items */
CREATE TABLE IF NOT EXISTS `workshop_quote_items` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_id INT NOT NULL,
  product_id INT NULL,
  product_name VARCHAR(200) NOT NULL DEFAULT '',
  qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  INDEX idx_qitem_quote (quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* workshop_quotes */
CREATE TABLE IF NOT EXISTS `workshop_quotes` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_no VARCHAR(24) NOT NULL,
  customer_id INT NULL,
  customer_name VARCHAR(150) NOT NULL DEFAULT '',
  phone VARCHAR(30) NULL,
  item_name VARCHAR(200) NOT NULL DEFAULT '',
  machine_id INT NULL,
  notes TEXT NULL,
  labor_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
  parts_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  status VARCHAR(32) NOT NULL DEFAULT 'open',
  job_id INT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_quote_no (quote_no),
  INDEX idx_quote_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* Extra columns for older live tables */
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `shop_logo` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `shop_tagline` VARCHAR(180) NULL DEFAULT NULL;
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `default_pay_account_id` INT NULL DEFAULT NULL;
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `brand_type_json` TEXT NULL;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `due_watch` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `due_watch_on` DATE DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `next_due_date` DATE DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `next_due_amount` DECIMAL(12,2) NULL DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `due_note` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `is_borrower` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `guarantor_name` VARCHAR(150) DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `guarantor_phone` VARCHAR(20) DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `group_id` INT DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `area_id` INT DEFAULT NULL;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `sale_date` DATETIME NULL;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `vat_percent` DECIMAL(5,2) NOT NULL DEFAULT 0;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `vat_amount` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `service_amount` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `rounding_amount` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `offline_client_ref` VARCHAR(80) NULL;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `guest_customer_name` VARCHAR(150) NULL;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `guest_customer_phone` VARCHAR(30) NULL;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `store_id` INT NULL;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `exchange_amount` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `sales` ADD COLUMN IF NOT EXISTS `cash_paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `sale_items` ADD COLUMN IF NOT EXISTS `item_name` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `memo_no` VARCHAR(50) NULL;
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `dealer_price` DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `payment_type` VARCHAR(10) NOT NULL DEFAULT 'cash';
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `due_amount` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `notes` VARCHAR(500) NULL;
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `store_id` INT NULL;
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `customer_id` INT NULL;
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `previous_customer_balance` DECIMAL(12,2) NULL DEFAULT NULL;
ALTER TABLE `purchases` ADD COLUMN IF NOT EXISTS `customer_balance_after` DECIMAL(12,2) NULL DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email` VARCHAR(120) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `photo` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `employee_id` INT NULL DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `auth_version` INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `context` VARCHAR(20) NOT NULL DEFAULT 'business';
ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `is_default` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `rental_owners` ADD COLUMN IF NOT EXISTS `vehicle_no` VARCHAR(40) DEFAULT NULL;
ALTER TABLE `rental_owners` ADD COLUMN IF NOT EXISTS `vehicle_name` VARCHAR(120) DEFAULT NULL;
ALTER TABLE `rental_owners` ADD COLUMN IF NOT EXISTS `entry_date` DATE DEFAULT NULL;
ALTER TABLE `partners` ADD COLUMN IF NOT EXISTS `partner_type` VARCHAR(30) NOT NULL DEFAULT 'equity';
ALTER TABLE `partners` ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) NULL;
ALTER TABLE `partners` ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'active';
ALTER TABLE `partners` ADD COLUMN IF NOT EXISTS `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `partners` ADD COLUMN IF NOT EXISTS `initial_capital` DECIMAL(14,2) NOT NULL DEFAULT 0;
ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `clock_color` VARCHAR(20) DEFAULT '#2DD4BF';
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `barcode` VARCHAR(64) DEFAULT NULL;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `is_old_battery` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `oem_code` VARCHAR(80) DEFAULT NULL;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `shelf_location` VARCHAR(100) DEFAULT NULL;
ALTER TABLE `sms_settings` ADD COLUMN IF NOT EXISTS `channel` VARCHAR(20) NOT NULL DEFAULT 'sim';


/* 10.98.418 clean install — empty extra tables, no shop rows */
CREATE TABLE IF NOT EXISTS `inventory_damage` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  store_id INT NULL,
  qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  loss_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  reason VARCHAR(40) NOT NULL DEFAULT 'other',
  note VARCHAR(255) NULL,
  damage_date DATE NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'posted',
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_damage_date (damage_date, id),
  INDEX idx_damage_product (product_id),
  INDEX idx_damage_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ame_purchase_revisions` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id INT NOT NULL,
  action VARCHAR(40) NOT NULL,
  summary TEXT NULL,
  snapshot LONGTEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY invoice_id (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `party_balance_adjustments` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  party_type VARCHAR(16) NOT NULL,
  party_id INT NOT NULL,
  memo_no VARCHAR(50) NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  kind VARCHAR(24) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_party (party_type, party_id),
  INDEX idx_memo (memo_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `purchase_memo_audit` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  memo_no VARCHAR(50) NOT NULL,
  action VARCHAR(40) NOT NULL,
  user_id INT NULL,
  reason VARCHAR(500) NULL,
  old_json LONGTEXT NULL,
  new_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_memo_audit (memo_no),
  INDEX idx_audit_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `purchase_payment_allocations` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  source_memo VARCHAR(50) NOT NULL,
  target_memo VARCHAR(50) NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_alloc_source (source_memo),
  INDEX idx_alloc_target (target_memo),
  INDEX idx_alloc_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`shop_name`)
SELECT 'আমার ব্যবসা প্রতিষ্ঠান' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `settings`);

INSERT INTO `accounts` (`account_name`, `account_type`, `balance`, `status`, `context`, `is_default`)
SELECT 'ক্যাশ', 'cash', 0, 'active', 'business', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `accounts`);

INSERT INTO `stores` (`code`, `name`, `is_active`, `is_default`)
SELECT 'MAIN', 'মূল দোকান', 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `stores`);

INSERT INTO `theme_settings` (`id`)
SELECT 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `theme_settings`);

INSERT INTO `user_roles` (`role_key`, `role_label`)
SELECT 'admin', 'Admin (সম্পূর্ণ এক্সেস)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `user_roles` WHERE `role_key` = 'admin');
INSERT INTO `user_roles` (`role_key`, `role_label`)
SELECT 'manager', 'Manager' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `user_roles` WHERE `role_key` = 'manager');
INSERT INTO `user_roles` (`role_key`, `role_label`)
SELECT 'cashier', 'Cashier' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `user_roles` WHERE `role_key` = 'cashier');
INSERT INTO `user_roles` (`role_key`, `role_label`)
SELECT 'employee', 'Employee' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `user_roles` WHERE `role_key` = 'employee');
INSERT INTO `user_roles` (`role_key`, `role_label`)
SELECT 'family', 'Family Member' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `user_roles` WHERE `role_key` = 'family');
INSERT INTO `user_roles` (`role_key`, `role_label`)
SELECT 'viewer', 'View Only' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `user_roles` WHERE `role_key` = 'viewer');

INSERT INTO `users` (`username`, `password`, `role`, `status`, `must_change_password`, `is_super_admin`)
SELECT 'admin', '$2y$10$pBGl3nYeTSfv8WNEBEwm2epsXIR0Dcxutg2JyT8jbdxJBC82B3.fG', 'admin', 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin');

SET FOREIGN_KEY_CHECKS=1;
