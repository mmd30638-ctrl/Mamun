-- APONSHOP database schema reference
-- Generated from the 2026-10-02 production export.
-- Structure only: no INSERT statements or customer/business data.
-- Use migrations for live updates; do not import this over production.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_type` enum('Cash','Bank','Mobile') DEFAULT 'Cash',
  `balance` decimal(15,2) DEFAULT 0.00,
  `is_default` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `context` enum('business','personal') DEFAULT 'business',
  `low_balance_threshold` decimal(12,2) DEFAULT NULL,
  `alert_phone` varchar(20) DEFAULT NULL,
  `last_alert_sent_at` datetime DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `bank_branch` varchar(150) DEFAULT NULL,
  `owner_member_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `account_ledger` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `ref_type` varchar(50) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_reconciled` tinyint(1) NOT NULL DEFAULT 0,
  `attachment` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `merchant_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `account_transfers` (
  `id` int(11) NOT NULL,
  `from_account` int(11) DEFAULT NULL,
  `to_account` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `transfer_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `username` varchar(120) DEFAULT NULL,
  `action_type` varchar(40) DEFAULT NULL,
  `page_url` varchar(255) DEFAULT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` varchar(40) DEFAULT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(180) DEFAULT NULL,
  `party_type` varchar(20) DEFAULT NULL,
  `party_id` int(11) DEFAULT NULL,
  `party_name` varchar(180) DEFAULT NULL,
  `amount_total` decimal(14,2) DEFAULT NULL,
  `amount_paid` decimal(14,2) DEFAULT NULL,
  `amount_due` decimal(14,2) DEFAULT NULL,
  `amount_discount` decimal(14,2) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `old_json` text DEFAULT NULL,
  `new_json` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `device_info` varchar(80) DEFAULT NULL,
  `session_key` varchar(64) DEFAULT NULL,
  `prev_hash` char(64) DEFAULT NULL,
  `row_hash` char(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `app_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `att_date` date DEFAULT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('Present','Absent','Leave','Late') DEFAULT 'Present'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `banks` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(150) NOT NULL,
  `branch_name` varchar(150) DEFAULT NULL,
  `branch_address` text DEFAULT NULL,
  `account_info` varchar(255) DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bank_loans` (
  `id` int(11) NOT NULL,
  `loan_name` varchar(150) NOT NULL,
  `bank_name` varchar(150) NOT NULL,
  `loan_type` varchar(50) NOT NULL DEFAULT 'ব্যবসায়িক লোন',
  `principal_amount` decimal(14,2) NOT NULL,
  `paid_principal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `loan_date` date NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `tenure_months` int(11) NOT NULL DEFAULT 0,
  `installment_count` int(11) NOT NULL DEFAULT 0,
  `installment_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `first_installment_date` date DEFAULT NULL,
  `last_installment_date` date DEFAULT NULL,
  `processing_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `closed_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `bill_collections` (
  `id` int(11) NOT NULL,
  `partner_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `month_year` varchar(20) DEFAULT NULL,
  `collected_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bill_partners` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `monthly_share` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bloan_accounts` (
  `id` int(11) NOT NULL,
  `loan_no` varchar(30) NOT NULL,
  `application_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `approved_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `disbursed_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(8,3) NOT NULL DEFAULT 0.000,
  `interest_type` varchar(20) NOT NULL DEFAULT 'flat',
  `monthly_payment` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tenure_months` int(11) NOT NULL DEFAULT 1,
  `installment_count` int(11) NOT NULL DEFAULT 1,
  `frequency` varchar(20) NOT NULL DEFAULT 'monthly',
  `installment_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `principal_outstanding` decimal(15,2) NOT NULL DEFAULT 0.00,
  `principal_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `charges_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `disbursed_on` date DEFAULT NULL,
  `account_id` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `bloan_applications` (
  `id` int(11) NOT NULL,
  `app_no` varchar(30) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `apply_date` date NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tenure_months` int(11) NOT NULL DEFAULT 1,
  `interest_rate` decimal(8,3) NOT NULL DEFAULT 0.000,
  `interest_type` varchar(20) NOT NULL DEFAULT 'flat',
  `monthly_payment` decimal(15,2) NOT NULL DEFAULT 0.00,
  `frequency` varchar(20) NOT NULL DEFAULT 'monthly',
  `processing_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `other_charge` decimal(15,2) NOT NULL DEFAULT 0.00,
  `guarantee_note` varchar(500) DEFAULT NULL,
  `documents_note` varchar(500) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `bloan_documents` (
  `id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `file_name` varchar(180) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `bloan_installments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_no` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `principal_part` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_part` decimal(15,2) NOT NULL DEFAULT 0.00,
  `charge_part` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_due` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_on` date DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'upcoming'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `bloan_providers` (
  `id` int(11) NOT NULL,
  `provider_type` varchar(30) NOT NULL DEFAULT 'bank',
  `name` varchar(160) NOT NULL,
  `branch` varchar(150) DEFAULT NULL,
  `officer_name` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `account_ref` varchar(80) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `bloan_txns` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_id` int(11) DEFAULT NULL,
  `txn_date` date NOT NULL,
  `kind` varchar(30) NOT NULL,
  `principal_part` decimal(15,2) NOT NULL DEFAULT 0.00,
  `interest_part` decimal(15,2) NOT NULL DEFAULT 0.00,
  `charge_part` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `balance_after` decimal(15,2) NOT NULL DEFAULT 0.00,
  `method` varchar(30) NOT NULL DEFAULT 'bank',
  `account_id` int(11) NOT NULL DEFAULT 0,
  `reference_no` varchar(80) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `cash_closing` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `closing_date` date NOT NULL,
  `system_balance` decimal(12,2) NOT NULL,
  `counted_balance` decimal(12,2) NOT NULL,
  `difference` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_partnership_shared` tinyint(1) NOT NULL DEFAULT 0,
  `is_exchange` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `contact_groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `critical_action_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_key` varchar(40) NOT NULL,
  `otp_hash` varchar(64) NOT NULL,
  `channel` varchar(20) NOT NULL,
  `destination` varchar(120) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `cus_id_code` varchar(50) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `alt_phone` varchar(20) DEFAULT NULL,
  `nid_number` varchar(30) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `guarantor_name` varchar(100) DEFAULT NULL,
  `guarantor_phone` varchar(20) DEFAULT NULL,
  `guarantor_nid` varchar(30) DEFAULT NULL,
  `guarantor_photo` varchar(255) DEFAULT NULL,
  `total_due` decimal(15,2) DEFAULT 0.00,
  `credit_limit` decimal(15,2) DEFAULT 50000.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `is_installment_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `loyalty_points` int(11) DEFAULT 0,
  `custom_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_data`)),
  `next_payment_date` date DEFAULT NULL,
  `customer_code` varchar(30) NOT NULL,
  `nid` varchar(30) DEFAULT NULL,
  `is_customer` tinyint(1) NOT NULL DEFAULT 1,
  `is_supplier` tinyint(1) NOT NULL DEFAULT 0,
  `is_installment` tinyint(1) NOT NULL DEFAULT 0,
  `is_rental` tinyint(1) NOT NULL DEFAULT 0,
  `receivable_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payable_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rental_deposit_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ai_credit_score` int(11) NOT NULL DEFAULT 70,
  `ai_risk_level` varchar(20) NOT NULL DEFAULT 'Normal',
  `notes` text DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `custom_requirement` text DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `next_due_amount` decimal(12,2) DEFAULT NULL,
  `due_note` varchar(255) DEFAULT NULL,
  `is_borrower` tinyint(1) NOT NULL DEFAULT 0,
  `due_watch` tinyint(1) NOT NULL DEFAULT 0,
  `due_watch_on` date DEFAULT NULL,
  `next_pay_date` date DEFAULT NULL,
  `next_pay_amount` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `customer_areas` (
  `id` int(11) NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `customer_call_log` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `outcome` varchar(20) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `customer_groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `customer_ledger` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `module_type` varchar(50) DEFAULT 'GENERAL',
  `ref_no` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `debit` decimal(12,2) DEFAULT 0.00,
  `credit` decimal(12,2) DEFAULT 0.00,
  `running_balance` decimal(12,2) DEFAULT 0.00,
  `txn_date` date DEFAULT curdate(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `balance_field` varchar(30) NOT NULL DEFAULT 'receivable_balance'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `customer_ledgers` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `txn_type` varchar(50) NOT NULL,
  `narration` text DEFAULT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  `balance_type` varchar(50) DEFAULT 'receivable_balance',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_payments` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_date` date NOT NULL,
  `method` varchar(30) NOT NULL DEFAULT 'CASH',
  `account_id` int(11) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `idempotency_token` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `customer_rentals` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `rent_amount` decimal(15,2) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('active','returned') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `customer_to_shop_buys` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `product_details` text DEFAULT NULL,
  `buying_price` decimal(15,2) DEFAULT NULL,
  `payment_status` enum('paid','due') DEFAULT 'paid',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `customer_withdrawals` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdraw_date` date NOT NULL,
  `method` varchar(30) NOT NULL DEFAULT 'CASH',
  `account_id` int(11) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `idempotency_token` varchar(80) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `damages` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `qty` decimal(15,2) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `dashboard_banners` (
  `id` int(11) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `dept_name` varchar(100) DEFAULT NULL,
  `total_employees` int(11) DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `display_widgets` (
  `id` int(11) NOT NULL,
  `kind` varchar(20) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `title` varchar(200) NOT NULL DEFAULT '',
  `message` text DEFAULT NULL,
  `icon_key` varchar(40) NOT NULL DEFAULT 'megaphone',
  `icon_color` varchar(20) NOT NULL DEFAULT '#FFFFFF',
  `icon_bg` varchar(20) NOT NULL DEFAULT '#0B1E4D',
  `text_color` varchar(20) NOT NULL DEFAULT '#1B5E20',
  `bg_color` varchar(20) NOT NULL DEFAULT '#FFFFFF',
  `accent_color` varchar(20) NOT NULL DEFAULT '#FFD600',
  `digit_color` varchar(20) NOT NULL DEFAULT '#0B1E4D',
  `digit_bg` varchar(20) NOT NULL DEFAULT '#FFFFFF',
  `label_color` varchar(20) NOT NULL DEFAULT '#E2E8F0',
  `font_size` int(11) NOT NULL DEFAULT 13,
  `font_weight` varchar(10) NOT NULL DEFAULT '700',
  `speed_seconds` int(11) NOT NULL DEFAULT 22,
  `direction` varchar(10) NOT NULL DEFAULT 'left',
  `ends_at` datetime DEFAULT NULL,
  `ended_message` varchar(255) NOT NULL DEFAULT 'সময় শেষ হয়েছে',
  `hide_when_ended` tinyint(1) NOT NULL DEFAULT 0,
  `show_days` tinyint(1) NOT NULL DEFAULT 1,
  `show_hours` tinyint(1) NOT NULL DEFAULT 1,
  `show_minutes` tinyint(1) NOT NULL DEFAULT 1,
  `show_seconds` tinyint(1) NOT NULL DEFAULT 1,
  `box_style` varchar(20) NOT NULL DEFAULT 'boxed',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `digit_font_size` int(11) NOT NULL DEFAULT 14,
  `digit_font_weight` varchar(10) NOT NULL DEFAULT '800',
  `digit_font_family` varchar(120) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `salary` decimal(15,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `department_id` int(11) DEFAULT NULL,
  `salary_type` enum('monthly','daily') NOT NULL DEFAULT 'monthly',
  `photo` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `employee_code` varchar(30) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `service_share_percent` decimal(5,2) NOT NULL DEFAULT 50.00,
  `worker_type` varchar(40) NOT NULL DEFAULT 'artisan',
  `payment_type` varchar(20) NOT NULL DEFAULT 'percentage',
  `advance_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `removed_at` datetime DEFAULT NULL,
  `removed_by` int(11) DEFAULT NULL,
  `shop_deposit_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `archived_at` datetime DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL,
  `archive_note` varchar(255) DEFAULT NULL,
  `purged_at` datetime DEFAULT NULL,
  `purged_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `employee_advances` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `kind` varchar(20) NOT NULL DEFAULT 'give',
  `advance_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `previous_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `affects_cash` tinyint(1) NOT NULL DEFAULT 1,
  `idempotency_token` varchar(64) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_shop_deposits` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `kind` varchar(20) NOT NULL DEFAULT 'deposit',
  `deposit_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `previous_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `idempotency_token` varchar(64) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_work_descriptions` (
  `id` int(11) NOT NULL,
  `engineer_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `engineers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `employee_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_partnership_shared` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_access` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_key` varchar(20) NOT NULL DEFAULT 'member',
  `can_health` tinyint(1) NOT NULL DEFAULT 1,
  `can_money` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_books` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(160) DEFAULT NULL,
  `publisher` varchar(160) DEFAULT NULL,
  `subject` varchar(80) DEFAULT NULL,
  `language` varchar(40) DEFAULT NULL,
  `copies` int(11) NOT NULL DEFAULT 1,
  `buy_date` date DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `source` varchar(160) DEFAULT NULL,
  `location_note` varchar(160) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `pdf_file` varchar(255) DEFAULT NULL,
  `page_count` int(11) NOT NULL DEFAULT 0,
  `last_page` int(11) NOT NULL DEFAULT 1,
  `subtitle` varchar(255) DEFAULT NULL,
  `translator` varchar(160) DEFAULT NULL,
  `edition` varchar(80) DEFAULT NULL,
  `pub_year` varchar(12) DEFAULT NULL,
  `subcategory` varchar(80) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cover_file` varchar(255) DEFAULT NULL,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `file_type` varchar(20) DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `favorite` tinyint(1) NOT NULL DEFAULT 0,
  `read_status` varchar(20) NOT NULL DEFAULT 'not_started',
  `visibility` varchar(20) NOT NULL DEFAULT 'family',
  `updated_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_book_folds` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `page_no` int(11) NOT NULL,
  `note` varchar(160) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `title` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_book_highlights` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_no` int(11) NOT NULL,
  `quote` varchar(500) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_book_history` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_no` int(11) NOT NULL DEFAULT 1,
  `progress_pct` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'reading',
  `read_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_book_notes` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_no` int(11) NOT NULL,
  `note_text` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_book_pages` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `page_no` int(11) NOT NULL,
  `image_file` varchar(255) NOT NULL DEFAULT '',
  `ocr_text` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_book_progress` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `page_no` int(11) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'reading',
  `updated_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_book_toc` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `title` varchar(180) NOT NULL,
  `page_no` int(11) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_contacts` (
  `id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `alt_mobile` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `profession` varchar(120) DEFAULT NULL,
  `organization` varchar(120) DEFAULT NULL,
  `relation_note` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_doctors` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `specialist` varchar(80) DEFAULT NULL,
  `hospital` varchar(160) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `chamber_time` varchar(160) DEFAULT NULL,
  `appointment_date` date DEFAULT NULL,
  `last_visit` date DEFAULT NULL,
  `next_visit` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `visit_fee` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_documents` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `kind` varchar(80) DEFAULT 'অন্যান্য',
  `file_name` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `rx_date` date DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_events` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `kind` varchar(40) NOT NULL DEFAULT 'অন্যান্য',
  `event_date` date NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `ref_kind` varchar(40) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_expenses` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `spent_by` varchar(80) DEFAULT NULL,
  `purpose` varchar(200) DEFAULT NULL,
  `pay_method` varchar(40) DEFAULT 'নগদ',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(160) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `qty` varchar(40) DEFAULT '1',
  `buy_date` date DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `condition_note` varchar(80) DEFAULT 'ভালো',
  `location_note` varchar(160) DEFAULT NULL,
  `warranty_until` date DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `maintain_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_medicines` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `generic_name` varchar(160) DEFAULT NULL,
  `company` varchar(120) DEFAULT NULL,
  `strength` varchar(40) DEFAULT NULL,
  `bought_qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `when_to_take` varchar(160) DEFAULT NULL,
  `times_per_day` int(11) NOT NULL DEFAULT 1,
  `days_for` int(11) NOT NULL DEFAULT 0,
  `rx_date` date DEFAULT NULL,
  `refill_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `document_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_med_buys` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `buy_date` date DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pharmacy` varchar(160) DEFAULT NULL,
  `invoice_file` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_members` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'other',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `relation_name` varchar(80) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `emergency_name` varchar(120) DEFAULT NULL,
  `emergency_phone` varchar(30) DEFAULT NULL,
  `important_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_notes` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `title` varchar(160) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_rx_lines` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `medicine_id` int(11) DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `strength` varchar(40) DEFAULT NULL,
  `when_to_take` varchar(160) DEFAULT NULL,
  `times_per_day` int(11) NOT NULL DEFAULT 1,
  `days_for` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `family_shopping` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `qty` varchar(40) DEFAULT NULL,
  `est_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `priority` varchar(20) DEFAULT 'মাঝারি',
  `buyer` varchar(80) DEFAULT NULL,
  `shop_name` varchar(160) DEFAULT NULL,
  `is_bought` tinyint(1) NOT NULL DEFAULT 0,
  `buy_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `idempotency_keys` (
  `id` int(11) NOT NULL,
  `scope` varchar(40) NOT NULL,
  `token` varchar(80) NOT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `installments` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_installment` int(11) DEFAULT NULL,
  `paid_installment` int(11) DEFAULT 0,
  `per_installment_amount` decimal(15,2) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `status` enum('running','completed') DEFAULT 'running'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `installment_contracts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `contract_no` varchar(100) NOT NULL,
  `product_condition` varchar(50) DEFAULT 'NEW',
  `product_details` text NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `down_payment` decimal(12,2) DEFAULT 0.00,
  `remaining_balance` decimal(12,2) NOT NULL,
  `total_installments` int(11) DEFAULT 12,
  `installment_type` varchar(50) DEFAULT 'MONTHLY',
  `status` varchar(50) DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `invoice_layout_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `layout_json` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `invoice_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `footer_note` varchar(255) DEFAULT 'ধন্যবাদ, আবার আসবেন!',
  `show_discount` tinyint(1) NOT NULL DEFAULT 1,
  `show_payment_method` tinyint(1) NOT NULL DEFAULT 1,
  `show_address` tinyint(1) NOT NULL DEFAULT 1,
  `paper_width` varchar(10) NOT NULL DEFAULT '80mm',
  `accent_color` varchar(20) NOT NULL DEFAULT 'green',
  `pos_discount_percent` decimal(8,2) NOT NULL DEFAULT 0.00,
  `pos_discount_auto` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL DEFAULT 'সাধারণ ছুটি',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL DEFAULT 1,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `applied_at` datetime DEFAULT current_timestamp(),
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `ledger_provenance` (
  `id` int(11) NOT NULL,
  `kind` varchar(20) NOT NULL,
  `ref_id` int(11) NOT NULL,
  `stamp` varchar(24) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `prev_hash` char(64) NOT NULL DEFAULT '',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `label` varchar(80) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `ledger_provenance_meta` (
  `id` int(11) NOT NULL,
  `pepper` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loans` (
  `id` int(11) NOT NULL,
  `person_name` varchar(100) NOT NULL,
  `type` enum('taken','given') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `customer_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `interest_rate` decimal(5,2) DEFAULT NULL,
  `interest_type` varchar(20) DEFAULT NULL,
  `term_months` int(11) DEFAULT NULL,
  `disbursed_at` datetime DEFAULT NULL,
  `disbursed_by` int(11) DEFAULT NULL,
  `disbursement_account_id` int(11) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_applications` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `requested_amount` decimal(12,2) NOT NULL,
  `requested_term_months` int(11) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `guarantor_name` varchar(150) DEFAULT NULL,
  `guarantor_phone` varchar(20) DEFAULT NULL,
  `status` enum('pending','approved','rejected','disbursed','cancelled') NOT NULL DEFAULT 'pending',
  `applied_by` int(11) DEFAULT NULL,
  `applied_at` datetime DEFAULT current_timestamp(),
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_note` varchar(255) DEFAULT NULL,
  `resulting_loan_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_fees` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `fee_type` enum('processing','penalty','other') NOT NULL DEFAULT 'other',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `charged_by` int(11) DEFAULT NULL,
  `charged_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_installments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_no` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `installment_amount` decimal(14,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `paid_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `interest_type` enum('flat','reducing') NOT NULL DEFAULT 'flat',
  `default_term_months` int(11) NOT NULL DEFAULT 12,
  `min_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `processing_fee_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `penalty_rate_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_recovery_actions` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `action_type` enum('call','visit','notice','legal','other') NOT NULL DEFAULT 'call',
  `action_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `done_by` int(11) DEFAULT NULL,
  `next_follow_up_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_repayment_schedule` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_no` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `due_principal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_interest` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','partial','paid','overdue') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_restructure_log` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `old_amount` decimal(12,2) NOT NULL,
  `old_term_months` int(11) NOT NULL,
  `old_interest_rate` decimal(5,2) NOT NULL,
  `new_amount` decimal(12,2) NOT NULL,
  `new_term_months` int(11) NOT NULL,
  `new_interest_rate` decimal(5,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `restructured_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_settings` (
  `id` int(11) NOT NULL,
  `grace_period_days` int(11) NOT NULL DEFAULT 3,
  `default_penalty_rate_percent` decimal(5,2) NOT NULL DEFAULT 2.00,
  `require_guarantor` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_settlements` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `settlement_type` enum('full_payment','early_settlement','written_off') NOT NULL DEFAULT 'full_payment',
  `waived_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `settled_by` int(11) DEFAULT NULL,
  `settled_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `loan_transactions` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `txn_type` varchar(20) NOT NULL,
  `txn_date` date NOT NULL,
  `total_amount` decimal(14,2) NOT NULL,
  `principal_part` decimal(14,2) NOT NULL DEFAULT 0.00,
  `interest_part` decimal(14,2) NOT NULL DEFAULT 0.00,
  `charge_part` decimal(14,2) NOT NULL DEFAULT 0.00,
  `account_id` int(11) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(190) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `merchants` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` varchar(500) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `owner_documents` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `doc_category` varchar(30) NOT NULL DEFAULT 'owner',
  `ref_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `owner_loans` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `bank_id` int(11) DEFAULT NULL,
  `loan_type` varchar(80) DEFAULT NULL,
  `loan_purpose` varchar(255) DEFAULT NULL,
  `approved_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `loan_term_months` int(11) NOT NULL DEFAULT 0,
  `installment_count` int(11) NOT NULL DEFAULT 0,
  `installment_frequency` varchar(20) NOT NULL DEFAULT 'monthly',
  `installment_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `approval_date` date DEFAULT NULL,
  `disbursement_date` date DEFAULT NULL,
  `total_payable` decimal(14,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `outstanding_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `owner_loan_disbursements` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `bank_id` int(11) DEFAULT NULL,
  `disbursement_date` date NOT NULL,
  `approved_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `disbursed_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `processing_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_amount_received` decimal(14,2) NOT NULL DEFAULT 0.00,
  `bank_ref_no` varchar(100) DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `owner_loan_installments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `installment_no` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `principal_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `interest_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `other_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_installment` decimal(14,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'upcoming'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `owner_loan_payments` (
  `id` int(11) NOT NULL,
  `loan_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `installment_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `principal_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `interest_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `penalty_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_payment` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(30) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `collected_by` int(11) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `owner_savings_accounts` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `account_opening_date` date NOT NULL,
  `opening_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `owner_savings_transactions` (
  `id` int(11) NOT NULL,
  `savings_account_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `txn_date` date NOT NULL,
  `txn_type` varchar(20) NOT NULL DEFAULT 'deposit',
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `previous_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `new_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(30) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `collected_by` int(11) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `owner_savings_withdrawals` (
  `id` int(11) NOT NULL,
  `savings_account_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `withdrawal_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `previous_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `owner_transaction_ledger` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `loan_id` int(11) DEFAULT NULL,
  `savings_account_id` int(11) DEFAULT NULL,
  `txn_date` date NOT NULL,
  `txn_type` varchar(30) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `debit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reference` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `page_color_settings` (
  `id` int(11) NOT NULL,
  `page_key` varchar(255) NOT NULL,
  `header_color` varchar(20) NOT NULL,
  `primary_color` varchar(20) NOT NULL,
  `secondary_color` varchar(20) NOT NULL,
  `accent_color` varchar(20) NOT NULL,
  `gold_color` varchar(20) NOT NULL,
  `bg_color` varchar(20) NOT NULL,
  `surface_color` varchar(20) NOT NULL,
  `text_color` varchar(20) NOT NULL,
  `muted_color` varchar(20) NOT NULL,
  `sidebar_color` varchar(20) NOT NULL,
  `border_color` varchar(20) NOT NULL,
  `danger_color` varchar(20) NOT NULL,
  `clock_color` varchar(20) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `partners` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `share_percentage` decimal(5,2) NOT NULL DEFAULT 50.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `partner_type` varchar(30) NOT NULL DEFAULT 'equity',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `initial_capital` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `partnership_adjustments` (
  `id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `adjustment_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `partnership_openings` (
  `id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `as_of_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `partner_acct_audit` (
  `id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `entity` varchar(40) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `period_ym` char(7) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `partner_acct_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(40) NOT NULL DEFAULT 'other',
  `description` varchar(255) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `period_ym` char(7) NOT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `partner_acct_months` (
  `period_ym` char(7) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `partner_acct_payments` (
  `id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` varchar(40) NOT NULL DEFAULT 'cash',
  `period_ym` char(7) NOT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `partner_capital_transactions` (
  `id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `type` varchar(25) NOT NULL DEFAULT 'investment',
  `amount` decimal(14,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `partner_withdrawals` (
  `id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `withdrawal_date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `password_reset_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_hash` varchar(64) NOT NULL,
  `channel` varchar(20) NOT NULL,
  `destination` varchar(120) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `salary_month` varchar(20) DEFAULT NULL,
  `salary_year` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('Paid','Pending') DEFAULT 'Paid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `personal_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `pf_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_group` varchar(50) NOT NULL DEFAULT 'অন্যান্য',
  `type` varchar(10) NOT NULL DEFAULT 'expense',
  `icon` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `pf_family_members` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'other',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `pf_merchants` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `pf_transactions` (
  `id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `txn_date` date NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `to_account_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `merchant_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `source_label` varchar(150) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `related_txn_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `pos_title_line` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `line_text` text NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `text_color` varchar(20) NOT NULL DEFAULT '#0D47A1',
  `font_size` int(11) NOT NULL DEFAULT 13,
  `font_weight` varchar(10) NOT NULL DEFAULT '800',
  `font_family` varchar(120) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `dealer_price` decimal(15,2) DEFAULT 0.00,
  `sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `stock` decimal(15,2) DEFAULT 0.00,
  `min_stock` decimal(15,2) DEFAULT 5.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `brand_id` int(11) DEFAULT 0,
  `unit_id` int(11) DEFAULT 1,
  `qr_code` varchar(255) DEFAULT NULL,
  `shelf_location` varchar(100) DEFAULT NULL,
  `oem_code` varchar(80) DEFAULT NULL,
  `supplier_code` varchar(80) DEFAULT NULL,
  `is_old_battery` tinyint(1) NOT NULL DEFAULT 0,
  `barcode` varchar(64) DEFAULT NULL,
  `manufacturer_name` varchar(150) DEFAULT NULL,
  `sub_category` varchar(100) DEFAULT NULL,
  `product_type` varchar(32) NOT NULL DEFAULT 'part',
  `short_description` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `highlights` text DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `reference_no` varchar(80) DEFAULT NULL,
  `vat_percent` decimal(8,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(8,2) NOT NULL DEFAULT 0.00,
  `moq` decimal(10,2) NOT NULL DEFAULT 1.00,
  `warranty_months` int(11) DEFAULT NULL,
  `warranty_type` varchar(80) DEFAULT NULL,
  `return_policy` text DEFAULT NULL,
  `model_name` varchar(120) DEFAULT NULL,
  `size_label` varchar(80) DEFAULT NULL,
  `color_label` varchar(80) DEFAULT NULL,
  `weight_kg` decimal(10,3) DEFAULT NULL,
  `country_origin` varchar(80) DEFAULT NULL,
  `hs_code` varchar(32) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `seo_title` varchar(180) DEFAULT NULL,
  `seo_keywords` varchar(255) DEFAULT NULL,
  `rental_qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `damaged_qty` decimal(12,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `product_serials` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `serial_no` varchar(80) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'in_stock',
  `customer_id` int(11) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `job_id` int(11) DEFAULT NULL,
  `warranty_months` int(11) NOT NULL DEFAULT 0,
  `warranty_expiry` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `product_units` (
  `id` int(11) NOT NULL,
  `unit_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_unit_factors` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `from_unit_id` int(11) NOT NULL,
  `to_unit_id` int(11) NOT NULL,
  `factor` decimal(18,6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `product_unit_map` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `can_purchase` tinyint(1) NOT NULL DEFAULT 1,
  `can_sell` tinyint(1) NOT NULL DEFAULT 1,
  `is_base` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `product_unit_prices` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `dealer_price` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `profit_allocations` (
  `id` int(11) NOT NULL,
  `period_label` varchar(100) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `gross_profit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_adjustments` decimal(14,2) NOT NULL DEFAULT 0.00,
  `net_profit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'proposed',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `profit_allocation_details` (
  `id` int(11) NOT NULL,
  `allocation_id` int(11) NOT NULL,
  `partner_id` int(11) NOT NULL,
  `share_percentage_used` decimal(5,2) NOT NULL,
  `allocated_amount` decimal(14,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `memo_no` varchar(50) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `qty` decimal(10,2) NOT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `wholesale_price` decimal(10,2) NOT NULL,
  `dealer_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `payment_type` varchar(10) NOT NULL DEFAULT 'cash',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `previous_supplier_due` decimal(12,2) DEFAULT NULL,
  `previous_customer_balance` decimal(12,2) DEFAULT NULL,
  `supplier_due_after` decimal(12,2) DEFAULT NULL,
  `customer_balance_after` decimal(12,2) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `base_qty` decimal(18,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_drafts` (
  `id` int(11) NOT NULL,
  `draft_number` varchar(40) NOT NULL DEFAULT '',
  `supplier_id` int(11) DEFAULT NULL,
  `memo_no` varchar(50) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `items_json` mediumtext NOT NULL,
  `expenses_json` mediumtext DEFAULT NULL,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `account_id` int(11) DEFAULT NULL,
  `payment_type` varchar(10) NOT NULL DEFAULT 'cash',
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `purchase_expenses` (
  `id` int(11) NOT NULL,
  `memo_no` varchar(50) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `expense_type` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `purchase_returns` (
  `id` int(11) NOT NULL,
  `return_no` varchar(30) NOT NULL,
  `credit_note_no` varchar(30) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(100) DEFAULT NULL,
  `memo_no` varchar(50) DEFAULT NULL,
  `return_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `refund_type` varchar(20) NOT NULL DEFAULT 'supplier_credit',
  `account_id` int(11) DEFAULT NULL,
  `inspection_status` varchar(20) NOT NULL DEFAULT 'pending',
  `inspection_note` varchar(255) DEFAULT NULL,
  `supplier_response` varchar(20) NOT NULL DEFAULT 'pending',
  `supplier_response_note` varchar(255) DEFAULT NULL,
  `dispatch_date` date DEFAULT NULL,
  `dispatch_note` varchar(255) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reason_category` varchar(30) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `purchase_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `purchased_qty` decimal(10,2) DEFAULT NULL,
  `returned_qty` decimal(10,2) NOT NULL,
  `free_qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_price` decimal(12,2) NOT NULL,
  `line_total` decimal(14,2) NOT NULL,
  `unit_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `purchase_return_logs` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `action` varchar(30) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_accruals` (
  `id` int(11) NOT NULL,
  `accrual_no` varchar(50) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `period_key` varchar(24) NOT NULL,
  `period_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate_used` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate_type` varchar(20) NOT NULL DEFAULT 'DAILY',
  `ledger_ref` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_agreements` (
  `id` int(11) NOT NULL,
  `agreement_no` varchar(40) NOT NULL,
  `version_no` int(11) NOT NULL DEFAULT 1,
  `contract_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'DRAFT',
  `customer_code` varchar(40) DEFAULT NULL,
  `customer_name` varchar(180) NOT NULL DEFAULT '',
  `father_or_spouse` varchar(180) DEFAULT NULL,
  `nid` varchar(40) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `phone_alt` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `occupation` varchar(180) DEFAULT NULL,
  `customer_extra` text DEFAULT NULL,
  `item_name` varchar(255) NOT NULL DEFAULT '',
  `product_sku` varchar(80) DEFAULT NULL,
  `serial_no` varchar(80) DEFAULT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT 1.000,
  `item_condition` varchar(180) DEFAULT NULL,
  `security_deposit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rent_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `rate_type` varchar(20) NOT NULL DEFAULT 'DAILY',
  `rent_pay_on` varchar(80) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `agreement_date` date DEFAULT NULL,
  `other_charges` text DEFAULT NULL,
  `guarantor_code` varchar(40) DEFAULT NULL,
  `guarantor_name` varchar(180) DEFAULT NULL,
  `guarantor_father` varchar(180) DEFAULT NULL,
  `guarantor_nid` varchar(40) DEFAULT NULL,
  `guarantor_phone` varchar(30) DEFAULT NULL,
  `guarantor_phone_alt` varchar(30) DEFAULT NULL,
  `guarantor_address` text DEFAULT NULL,
  `guarantor_occupation` varchar(180) DEFAULT NULL,
  `guarantor_relation` varchar(80) DEFAULT NULL,
  `guarantor_extra` text DEFAULT NULL,
  `special_terms` text DEFAULT NULL,
  `terms_snapshot` mediumtext DEFAULT NULL,
  `verify_ref` varchar(24) DEFAULT NULL,
  `customer_signed_at` datetime DEFAULT NULL,
  `guarantor_signed_at` datetime DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_agreement_signs` (
  `id` int(11) NOT NULL,
  `agreement_id` int(11) NOT NULL,
  `version_no` int(11) NOT NULL DEFAULT 1,
  `role` varchar(20) NOT NULL,
  `signer_name` varchar(180) DEFAULT NULL,
  `signed_at` datetime NOT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `sha256` char(64) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_audit` (
  `id` int(11) NOT NULL,
  `table_name` varchar(40) NOT NULL,
  `row_id` int(11) NOT NULL,
  `action` varchar(20) NOT NULL,
  `old_json` text DEFAULT NULL,
  `new_json` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_contracts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rental_no` varchar(100) NOT NULL,
  `item_name` text NOT NULL,
  `security_deposit` decimal(12,2) NOT NULL,
  `rent_rate` decimal(12,2) NOT NULL,
  `rate_type` varchar(50) DEFAULT 'DAILY',
  `status` varchar(50) DEFAULT 'ACTIVE',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_charged_date` date DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `owner_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `owner_cycle` varchar(16) NOT NULL DEFAULT 'monthly',
  `owner_custom_days` int(11) NOT NULL DEFAULT 7,
  `owner_start_date` date DEFAULT NULL,
  `vehicle_no` varchar(40) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_sku` varchar(80) DEFAULT NULL,
  `serial_no` varchar(80) DEFAULT NULL,
  `barcode` varchar(80) DEFAULT NULL,
  `product_category` varchar(120) DEFAULT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT 1.000,
  `start_date` date DEFAULT NULL,
  `expected_return` date DEFAULT NULL,
  `billing_start` date DEFAULT NULL,
  `billing_day` tinyint(4) NOT NULL DEFAULT 1,
  `billing_method` varchar(24) NOT NULL DEFAULT 'calendar_month',
  `advance_usage` tinyint(1) NOT NULL DEFAULT 0,
  `account_id` int(11) DEFAULT NULL,
  `payment_method` varchar(30) NOT NULL DEFAULT 'CASH',
  `notes` text DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `pause_start` date DEFAULT NULL,
  `pause_end` date DEFAULT NULL,
  `late_fee_type` varchar(20) NOT NULL DEFAULT 'none',
  `late_fee_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `returned_at` datetime DEFAULT NULL,
  `return_condition` varchar(40) DEFAULT NULL,
  `meter_reading` varchar(40) DEFAULT NULL,
  `damage_notes` text DEFAULT NULL,
  `damage_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `received_by` int(11) DEFAULT NULL,
  `parent_contract_id` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `term_months` int(11) NOT NULL DEFAULT 0,
  `term_days` int(11) NOT NULL DEFAULT 0,
  `follow_date` date DEFAULT NULL,
  `follow_amount` decimal(12,2) DEFAULT NULL,
  `follow_note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_deposit_moves` (
  `id` int(11) NOT NULL,
  `move_no` varchar(40) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `kind` varchar(16) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `account_id` int(11) DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `ledger_ref` varchar(50) DEFAULT NULL,
  `reversed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_hire_payments` (
  `id` int(11) NOT NULL,
  `pay_no` varchar(40) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `account_id` int(11) DEFAULT NULL,
  `payment_method` varchar(30) NOT NULL DEFAULT 'CASH',
  `pay_date` date NOT NULL,
  `reference_no` varchar(80) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `ledger_ref` varchar(50) DEFAULT NULL,
  `reversed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_history` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `field_name` varchar(60) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_owners` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `pay_cycle` varchar(16) NOT NULL DEFAULT 'monthly',
  `pay_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `custom_days` int(11) NOT NULL DEFAULT 7,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vehicle_no` varchar(40) DEFAULT NULL,
  `vehicle_name` varchar(120) DEFAULT NULL,
  `entry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_owner_payments` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(30) NOT NULL DEFAULT 'CASH',
  `paid_at` datetime NOT NULL,
  `ref` varchar(80) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'posted',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` varchar(255) NOT NULL DEFAULT '',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_stock_moves` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `kind` varchar(20) NOT NULL,
  `serial_no` varchar(80) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `rental_vehicles` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `vehicle_no` varchar(40) NOT NULL,
  `vehicle_name` varchar(120) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_key` varchar(50) NOT NULL,
  `module_key` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `salaries` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `month_year` varchar(20) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `base_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `bonus_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `snacks_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `house_rent_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `medical_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `transport_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `other_allowance_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `fine_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `late_deduction_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_cut_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gross_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(50) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'paid',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` int(11) DEFAULT NULL,
  `reverse_note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `reference_no` varchar(80) DEFAULT NULL,
  `advance_cut` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_type` enum('Retail','Registered') DEFAULT 'Retail',
  `guest_customer_name` varchar(150) DEFAULT NULL,
  `guest_customer_phone` varchar(30) DEFAULT NULL,
  `guest_customer_note` varchar(255) DEFAULT NULL,
  `status` enum('draft','completed','cancelled') NOT NULL DEFAULT 'completed',
  `total_bill` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT 0.00,
  `vat_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `service_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `rounding_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_payable` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) NOT NULL,
  `due_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `previous_customer_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `due_adjustment_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `customer_balance_after` decimal(15,2) NOT NULL DEFAULT 0.00,
  `change_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `draft_note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `sale_date` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `offline_client_ref` varchar(80) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `exchange_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vehicle_no` varchar(40) DEFAULT NULL,
  `next_pay_date` date DEFAULT NULL,
  `next_pay_amount` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sale_due_schedules` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `note` varchar(255) DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sale_exchange_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL DEFAULT '',
  `qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `exchange_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `qty` decimal(15,2) DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `sale_price` decimal(15,2) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `legacy_key` varchar(96) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `base_qty` decimal(18,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sale_payments` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_status` enum('planned','posted','reversed') NOT NULL DEFAULT 'posted',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sale_returns` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `total_return_amount` decimal(12,2) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `return_date` timestamp NULL DEFAULT current_timestamp(),
  `product_id` int(11) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT NULL,
  `return_type` enum('with_invoice','without_invoice','exchange') NOT NULL DEFAULT 'with_invoice',
  `customer_name` varchar(100) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `exchange_product_id` int(11) DEFAULT NULL,
  `exchange_qty` decimal(10,2) DEFAULT NULL,
  `price_difference` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sale_returns_v2` (
  `id` int(11) NOT NULL,
  `return_no` varchar(30) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `sales_type` varchar(20) NOT NULL DEFAULT 'retail',
  `return_type` varchar(30) NOT NULL DEFAULT 'invoice_based',
  `return_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `exchange_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `customer_adjustment` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `account_id` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `refund_method` varchar(20) NOT NULL DEFAULT 'customer_balance',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(500) DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sale_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `original_qty` decimal(10,2) DEFAULT NULL,
  `returned_qty` decimal(10,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `discount_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `line_reason` varchar(100) DEFAULT NULL,
  `line_total` decimal(14,2) NOT NULL,
  `is_replacement` tinyint(1) NOT NULL DEFAULT 0,
  `unit_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sale_services` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `engineer_id` int(11) DEFAULT NULL,
  `service_type` varchar(255) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sale_warranties` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `serial_no` varchar(255) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `service_commissions` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `service_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 50.00,
  `commission_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `shop_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `engineer_id` int(11) DEFAULT NULL,
  `service_type` varchar(255) DEFAULT NULL,
  `payout_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `shop_name` varchar(180) NOT NULL DEFAULT 'আমার ব্যবসা প্রতিষ্ঠান',
  `shop_phone` varchar(20) DEFAULT NULL,
  `shop_address` text DEFAULT NULL,
  `currency` varchar(10) DEFAULT '৳',
  `shop_logo` varchar(255) DEFAULT NULL,
  `shop_favicon` varchar(255) DEFAULT NULL,
  `invoice_footer` text DEFAULT NULL,
  `shop_banner` varchar(255) DEFAULT NULL,
  `shop_tagline` varchar(180) DEFAULT NULL,
  `brand_type_json` text DEFAULT NULL,
  `default_pay_account_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `shop_owners` (
  `id` int(11) NOT NULL,
  `owner_name` varchar(150) NOT NULL,
  `parent_name` varchar(150) DEFAULT NULL,
  `nid_no` varchar(60) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `shop_name` varchar(150) DEFAULT NULL,
  `shop_type` varchar(80) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `trade_license_no` varchar(80) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sms_log` (
  `id` int(11) NOT NULL,
  `recipient` varchar(20) NOT NULL,
  `message` text DEFAULT NULL,
  `purpose` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'sent',
  `response_note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sms_outbox` (
  `id` int(11) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `purpose` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_error` varchar(255) DEFAULT NULL,
  `log_id` int(11) DEFAULT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `queued_at` datetime DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `sim_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sms_settings` (
  `id` int(11) NOT NULL,
  `api_url` varchar(255) DEFAULT NULL,
  `api_key` varchar(100) DEFAULT NULL,
  `sender_id` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'inactive',
  `channel` varchar(20) NOT NULL DEFAULT 'sim',
  `gateway_phone` varchar(20) NOT NULL DEFAULT '',
  `device_last_seen` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `sms_sims` (
  `id` int(11) NOT NULL,
  `label` varchar(80) NOT NULL,
  `phone` varchar(20) NOT NULL DEFAULT '',
  `slot` tinyint(4) NOT NULL DEFAULT 1,
  `token` varchar(64) NOT NULL,
  `is_default` tinyint(4) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `last_seen` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `stock_counts` (
  `id` int(11) NOT NULL,
  `count_no` varchar(24) NOT NULL,
  `store_id` int(11) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `stock_count_items` (
  `id` int(11) NOT NULL,
  `count_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `system_qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `counted_qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `variance` decimal(12,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `qty_in` decimal(12,3) NOT NULL DEFAULT 0.000,
  `qty_out` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `movement_type` varchar(32) NOT NULL,
  `ref_type` varchar(32) NOT NULL,
  `ref_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `store_stock` (
  `store_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `store_transfers` (
  `id` int(11) NOT NULL,
  `transfer_no` varchar(24) NOT NULL,
  `from_store_id` int(11) NOT NULL,
  `to_store_id` int(11) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `store_transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `total_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `credit_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `email` varchar(150) DEFAULT NULL,
  `alt_phone` varchar(30) DEFAULT NULL,
  `business_name` varchar(150) DEFAULT NULL,
  `business_type` varchar(80) DEFAULT NULL,
  `tax_number` varchar(60) DEFAULT NULL,
  `trade_license_no` varchar(60) DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `payment_terms` varchar(80) DEFAULT NULL,
  `preferred_payment_method` varchar(40) DEFAULT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `bank_account_no` varchar(60) DEFAULT NULL,
  `bank_branch` varchar(120) DEFAULT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `supply_categories` varchar(255) DEFAULT NULL,
  `lead_time_days` int(11) DEFAULT NULL,
  `minimum_order_note` varchar(120) DEFAULT NULL,
  `supply_notes` text DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `performance_note` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `party_code` varchar(40) DEFAULT NULL,
  `credit_limit` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `supplier_documents` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `supplier_settlements` (
  `id` int(11) NOT NULL,
  `payment_no` varchar(40) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'BDT',
  `fx_rate` decimal(14,6) NOT NULL DEFAULT 1.000000,
  `amount_bdt` decimal(14,2) NOT NULL DEFAULT 0.00,
  `method` varchar(30) NOT NULL DEFAULT 'cash',
  `account_id` int(11) NOT NULL DEFAULT 0,
  `reference_no` varchar(80) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `allocation_mode` varchar(20) NOT NULL DEFAULT 'oldest',
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `previous_due` decimal(14,2) NOT NULL DEFAULT 0.00,
  `remaining_due` decimal(14,2) NOT NULL DEFAULT 0.00,
  `ledger_id` int(11) DEFAULT NULL,
  `idempotency_token` varchar(80) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `reversed_by` int(11) DEFAULT NULL,
  `reversed_at` datetime DEFAULT NULL,
  `reversal_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `cheque_no` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `supplier_settlement_audit` (
  `id` int(11) NOT NULL,
  `settlement_id` int(11) NOT NULL,
  `action` varchar(30) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `supplier_settlement_lines` (
  `id` int(11) NOT NULL,
  `settlement_id` int(11) NOT NULL,
  `memo_no` varchar(50) NOT NULL DEFAULT '',
  `allocated` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_flags` (
  `flag_name` varchar(50) NOT NULL,
  `flag_value` varchar(50) NOT NULL DEFAULT '1',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `system_routes` (
  `id` int(11) NOT NULL,
  `request_slug` varchar(120) NOT NULL,
  `target_file` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `theme_settings` (
  `id` int(11) NOT NULL,
  `primary_color` varchar(20) DEFAULT '#1A237E',
  `secondary_color` varchar(20) DEFAULT '#1B5E20',
  `bg_color` varchar(20) DEFAULT '#FFFFFF',
  `text_color` varchar(20) DEFAULT '#333333',
  `header_color` varchar(20) DEFAULT '#0D47A1',
  `accent_color` varchar(20) DEFAULT '#1D9BF0',
  `gold_color` varchar(20) DEFAULT '#C9A227',
  `surface_color` varchar(20) DEFAULT '#FFFFFF',
  `muted_color` varchar(20) DEFAULT '#64748B',
  `sidebar_color` varchar(20) DEFAULT '#FFFFFF',
  `border_color` varchar(20) DEFAULT '#CFE3F7',
  `danger_color` varchar(20) DEFAULT '#D32F2F',
  `clock_color` varchar(20) DEFAULT '#2DD4BF',
  `module_key` varchar(40) NOT NULL DEFAULT '',
  `page_key` varchar(190) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `unit_stock_ledger` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `txn_date` date NOT NULL,
  `kind` varchar(30) NOT NULL,
  `entered_qty` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `unit_id` int(11) NOT NULL DEFAULT 0,
  `base_qty` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `balance_base` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `ref_type` varchar(40) DEFAULT NULL,
  `ref_id` int(11) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'admin',
  `photo` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `document` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `employee_id` int(11) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `auth_version` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `whatsapp` varchar(20) DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `use_custom_perms` tinyint(1) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `removed_by` int(11) DEFAULT NULL,
  `removed_username` varchar(120) DEFAULT NULL,
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_key` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `role_key` varchar(50) NOT NULL,
  `role_label` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_stores` (
  `user_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `vd_styles` (
  `id` int(11) NOT NULL,
  `scope` varchar(12) NOT NULL,
  `page_key` varchar(80) NOT NULL DEFAULT '',
  `element_key` varchar(40) NOT NULL,
  `device` varchar(12) NOT NULL DEFAULT 'all',
  `property` varchar(40) NOT NULL,
  `value` varchar(180) NOT NULL,
  `updated_by` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `whatsapp_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `api_url` varchar(255) NOT NULL DEFAULT 'https://graph.facebook.com/v21.0',
  `access_token` varchar(500) NOT NULL DEFAULT '',
  `phone_number_id` varchar(40) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'inactive',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `worker_audit_log` (
  `id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `worker_earnings` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `engineer_id` int(11) DEFAULT NULL,
  `source_type` varchar(30) NOT NULL DEFAULT 'sale_service',
  `source_id` int(11) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `order_ref` varchar(80) DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `job_label` varchar(255) DEFAULT NULL,
  `work_date` date NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `work_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_type` varchar(20) NOT NULL DEFAULT 'percentage',
  `rule_label` varchar(80) NOT NULL DEFAULT '',
  `percent_rate` decimal(6,2) NOT NULL DEFAULT 0.00,
  `fixed_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `earning_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `company_share` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pay_status` varchar(20) NOT NULL DEFAULT 'unpaid',
  `locked` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `worker_earnings_settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `worker_payments` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `method` varchar(20) NOT NULL DEFAULT 'cash',
  `payment_date` date NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'posted',
  `approval_status` varchar(20) NOT NULL DEFAULT 'none',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `reverses_id` int(11) DEFAULT NULL,
  `reversed_at` datetime DEFAULT NULL,
  `reverse_reason` varchar(255) DEFAULT NULL,
  `reversed_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `worker_payment_allocations` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `earning_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reversed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `worker_payment_rules` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `worker_type` varchar(40) NOT NULL DEFAULT 'artisan',
  `payment_type` varchar(20) NOT NULL DEFAULT 'percentage',
  `percent_rate` decimal(6,2) NOT NULL DEFAULT 50.00,
  `fixed_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fixed_scope` varchar(20) NOT NULL DEFAULT 'per_job',
  `daily_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monthly_salary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `custom_note` varchar(255) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `worker_payouts` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payout_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `workshop_jobs` (
  `id` int(11) NOT NULL,
  `job_no` varchar(24) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL DEFAULT '',
  `phone` varchar(30) DEFAULT NULL,
  `item_name` varchar(200) NOT NULL DEFAULT '',
  `serial_no` varchar(80) DEFAULT NULL,
  `complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `engineer_id` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'received',
  `labor_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `parts_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quote_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_at` datetime DEFAULT current_timestamp(),
  `delivered_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `machine_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `warranty_months` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `workshop_job_parts` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL DEFAULT '',
  `qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reserved` tinyint(1) NOT NULL DEFAULT 1,
  `issued` tinyint(1) NOT NULL DEFAULT 0,
  `line_kind` varchar(20) NOT NULL DEFAULT 'repair',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `workshop_machines` (
  `id` int(11) NOT NULL,
  `machine_type` varchar(40) NOT NULL DEFAULT 'other',
  `brand` varchar(120) NOT NULL DEFAULT '',
  `model` varchar(160) NOT NULL DEFAULT '',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `workshop_machine_parts` (
  `id` int(11) NOT NULL,
  `machine_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `oem_code` varchar(80) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `workshop_quotes` (
  `id` int(11) NOT NULL,
  `quote_no` varchar(24) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL DEFAULT '',
  `phone` varchar(30) DEFAULT NULL,
  `item_name` varchar(200) NOT NULL DEFAULT '',
  `machine_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `labor_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `parts_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `job_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

CREATE TABLE `workshop_quote_items` (
  `id` int(11) NOT NULL,
  `quote_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL DEFAULT '',
  `qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `account_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `ref_type` (`ref_type`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `idx_al_ref` (`ref_type`,`ref_id`);

ALTER TABLE `account_transfers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_al_action_type` (`action_type`,`created_at`),
  ADD KEY `idx_al_module` (`module`),
  ADD KEY `idx_al_reference` (`reference`);

ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_emp_date` (`employee_id`,`att_date`);

ALTER TABLE `banks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bank_status` (`status`);

ALTER TABLE `bank_loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_bank` (`bank_name`);

ALTER TABLE `bill_collections`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `bill_partners`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `bloan_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_loan_no` (`loan_no`),
  ADD UNIQUE KEY `uniq_application` (`application_id`);

ALTER TABLE `bloan_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_app_no` (`app_no`),
  ADD KEY `idx_provider` (`provider_id`);

ALTER TABLE `bloan_documents`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `bloan_installments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan` (`loan_id`),
  ADD KEY `idx_due` (`due_date`);

ALTER TABLE `bloan_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_provider` (`provider_type`,`name`,`branch`);

ALTER TABLE `bloan_txns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan` (`loan_id`);

ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `cash_closing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_account` (`account_id`);

ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

ALTER TABLE `contact_groups`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `critical_action_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action_key`,`used`,`expires_at`);

ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_customer_code` (`customer_code`),
  ADD UNIQUE KEY `cus_id_code` (`cus_id_code`),
  ADD UNIQUE KEY `nid_number` (`nid_number`),
  ADD KEY `idx_cust_phone` (`phone`),
  ADD KEY `idx_cust_name` (`name`);

ALTER TABLE `customer_areas`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `customer_call_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_customer_date` (`customer_id`,`log_date`);

ALTER TABLE `customer_groups`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `customer_ledger`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `customer_ledgers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `customer_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_payment_token` (`idempotency_token`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_pay_date` (`payment_date`,`id`);

ALTER TABLE `customer_rentals`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `customer_to_shop_buys`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `customer_withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cw_customer` (`customer_id`),
  ADD KEY `idx_cw_date` (`withdraw_date`);

ALTER TABLE `damages`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `dashboard_banners`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dept_name` (`dept_name`);

ALTER TABLE `display_widgets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kind_enabled` (`kind`,`is_enabled`,`sort_order`);

ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_employee_code` (`employee_code`);

ALTER TABLE `employee_advances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_adv_idem` (`idempotency_token`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `advance_date` (`advance_date`);

ALTER TABLE `employee_shop_deposits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_shop_dep_idem` (`idempotency_token`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `deposit_date` (`deposit_date`);

ALTER TABLE `employee_work_descriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_work_desc_engineer` (`engineer_id`);

ALTER TABLE `engineers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `family_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

ALTER TABLE `family_books`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `family_book_folds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `book_fold` (`book_id`,`page_no`);

ALTER TABLE `family_book_highlights`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_page` (`book_id`,`page_no`);

ALTER TABLE `family_book_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_user` (`book_id`,`user_id`,`read_at`);

ALTER TABLE `family_book_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_page` (`book_id`,`page_no`);

ALTER TABLE `family_book_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `book_page` (`book_id`,`page_no`);

ALTER TABLE `family_book_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `book_user` (`book_id`,`user_id`);

ALTER TABLE `family_book_toc`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`,`sort_order`);

ALTER TABLE `family_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

ALTER TABLE `family_doctors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

ALTER TABLE `family_documents`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `family_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_date` (`event_date`);

ALTER TABLE `family_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exp_date` (`exp_date`);

ALTER TABLE `family_groups`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `family_inventory`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `family_medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

ALTER TABLE `family_med_buys`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `family_members`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `family_notes`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `family_rx_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`);

ALTER TABLE `family_shopping`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `idempotency_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_scope_token` (`scope`,`token`),
  ADD KEY `idx_scope_created` (`scope`,`created_at`);

ALTER TABLE `installments`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `installment_contracts`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `invoice_layout_templates`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `invoice_settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `status` (`status`);

ALTER TABLE `ledger_provenance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_stamp` (`stamp`),
  ADD UNIQUE KEY `uniq_kind_ref` (`kind`,`ref_id`),
  ADD KEY `idx_created` (`created_at`);

ALTER TABLE `ledger_provenance_meta`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `loan_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `status` (`status`);

ALTER TABLE `loan_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

ALTER TABLE `loan_installments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan` (`loan_id`),
  ADD KEY `idx_due` (`due_date`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `loan_products`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `loan_recovery_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

ALTER TABLE `loan_repayment_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `due_date` (`due_date`),
  ADD KEY `status` (`status`);

ALTER TABLE `loan_restructure_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

ALTER TABLE `loan_settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `loan_settlements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_id` (`loan_id`);

ALTER TABLE `loan_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loan` (`loan_id`),
  ADD KEY `idx_type` (`txn_type`);

ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_time` (`username`,`attempted_at`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

ALTER TABLE `merchants`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_user` (`user_id`,`is_read`),
  ADD KEY `idx_notification_schedule` (`scheduled_at`,`is_read`),
  ADD KEY `idx_notification_reference` (`reference_type`,`reference_id`);

ALTER TABLE `owner_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_odoc_owner` (`owner_id`);

ALTER TABLE `owner_loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ol_owner` (`owner_id`),
  ADD KEY `idx_ol_bank` (`bank_id`),
  ADD KEY `idx_ol_status` (`status`);

ALTER TABLE `owner_loan_disbursements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_old_loan` (`loan_id`);

ALTER TABLE `owner_loan_installments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_oli_loan` (`loan_id`),
  ADD KEY `idx_oli_due` (`due_date`),
  ADD KEY `idx_oli_status` (`status`);

ALTER TABLE `owner_loan_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_olp_loan` (`loan_id`),
  ADD KEY `idx_olp_owner` (`owner_id`);

ALTER TABLE `owner_savings_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_osa_owner` (`owner_id`);

ALTER TABLE `owner_savings_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ost_account` (`savings_account_id`),
  ADD KEY `idx_ost_owner` (`owner_id`);

ALTER TABLE `owner_savings_withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_osw_account` (`savings_account_id`);

ALTER TABLE `owner_transaction_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otl_owner` (`owner_id`),
  ADD KEY `idx_otl_loan` (`loan_id`);

ALTER TABLE `page_color_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_page_color_key` (`page_key`);

ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `partnership_adjustments`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `partnership_openings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_partner` (`partner_id`);

ALTER TABLE `partner_acct_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity`,`entity_id`);

ALTER TABLE `partner_acct_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_period` (`period_ym`),
  ADD KEY `idx_date` (`expense_date`);

ALTER TABLE `partner_acct_months`
  ADD PRIMARY KEY (`period_ym`);

ALTER TABLE `partner_acct_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_partner` (`partner_id`),
  ADD KEY `idx_period` (`period_ym`);

ALTER TABLE `partner_capital_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_partner` (`partner_id`),
  ADD KEY `idx_type` (`type`);

ALTER TABLE `partner_withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_partner` (`partner_id`);

ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

ALTER TABLE `password_reset_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_otp` (`user_id`,`used`,`expires_at`);

ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

ALTER TABLE `personal_categories`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pf_categories`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pf_family_members`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pf_merchants`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pf_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_account` (`account_id`),
  ADD KEY `idx_date` (`txn_date`),
  ADD KEY `idx_type` (`type`);

ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_position_name` (`position_name`);

ALTER TABLE `pos_title_line`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `sku_2` (`sku`),
  ADD KEY `name` (`name`),
  ADD KEY `stock` (`stock`),
  ADD KEY `idx_prod_status` (`status`),
  ADD KEY `idx_prod_barcode` (`barcode`);

ALTER TABLE `product_serials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_serial_no` (`serial_no`),
  ADD KEY `idx_serial_product` (`product_id`),
  ADD KEY `idx_serial_status` (`status`);

ALTER TABLE `product_units`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `product_unit_factors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_factor` (`product_id`,`from_unit_id`,`to_unit_id`),
  ADD KEY `idx_product` (`product_id`);

ALTER TABLE `product_unit_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_product_unit` (`product_id`,`unit_id`),
  ADD KEY `idx_product` (`product_id`);

ALTER TABLE `product_unit_prices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_price` (`product_id`,`unit_id`);

ALTER TABLE `profit_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

ALTER TABLE `profit_allocation_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_allocation` (`allocation_id`),
  ADD KEY `idx_partner` (`partner_id`);

ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_pur_customer_id` (`customer_id`),
  ADD KEY `idx_supplier_memo` (`supplier_id`,`memo_no`);

ALTER TABLE `purchase_drafts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pd_status` (`status`),
  ADD KEY `idx_pd_user` (`created_by`),
  ADD KEY `idx_pd_store` (`store_id`);

ALTER TABLE `purchase_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_memo` (`memo_no`);

ALTER TABLE `purchase_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_no` (`return_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_supplier` (`supplier_id`);

ALTER TABLE `purchase_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_return` (`return_id`),
  ADD KEY `idx_product` (`product_id`);

ALTER TABLE `purchase_return_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_return` (`return_id`);

ALTER TABLE `rental_accruals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_racc_period` (`contract_id`,`period_key`),
  ADD KEY `idx_racc_cust` (`customer_id`),
  ADD KEY `idx_racc_date` (`period_date`);

ALTER TABLE `rental_agreements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_agr_ver` (`agreement_no`,`version_no`),
  ADD KEY `idx_agr_contract` (`contract_id`),
  ADD KEY `idx_agr_customer` (`customer_id`),
  ADD KEY `idx_agr_status` (`status`);

ALTER TABLE `rental_agreement_signs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ras_agr` (`agreement_id`),
  ADD KEY `idx_ras_role` (`agreement_id`,`role`);

ALTER TABLE `rental_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_raud_row` (`table_name`,`row_id`);

ALTER TABLE `rental_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rc_status_start` (`status`,`start_date`),
  ADD KEY `idx_rc_serial` (`serial_no`),
  ADD KEY `idx_rc_product` (`product_id`),
  ADD KEY `idx_rc_expected` (`expected_return`);

ALTER TABLE `rental_deposit_moves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rdm_contract` (`contract_id`),
  ADD KEY `idx_rdm_customer` (`customer_id`),
  ADD KEY `idx_rdm_kind` (`kind`);

ALTER TABLE `rental_hire_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rhp_contract` (`contract_id`),
  ADD KEY `idx_rhp_customer` (`customer_id`);

ALTER TABLE `rental_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rh_contract` (`contract_id`);

ALTER TABLE `rental_owners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ro_status` (`status`),
  ADD KEY `idx_ro_phone` (`phone`);

ALTER TABLE `rental_owner_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rop_owner` (`owner_id`),
  ADD KEY `idx_rop_status` (`status`);

ALTER TABLE `rental_settings`
  ADD PRIMARY KEY (`setting_key`);

ALTER TABLE `rental_stock_moves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rsm_contract` (`contract_id`),
  ADD KEY `idx_rsm_product` (`product_id`);

ALTER TABLE `rental_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rv_owner` (`owner_id`);

ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_module` (`role_key`,`module_key`);

ALTER TABLE `salaries`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sales_invoice_number` (`invoice_number`),
  ADD UNIQUE KEY `uniq_offline_client_ref` (`offline_client_ref`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `sale_date` (`sale_date`),
  ADD KEY `idx_sales_customer_id` (`customer_id`),
  ADD KEY `idx_sales_status` (`status`),
  ADD KEY `idx_sales_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_sales_guest_phone` (`guest_customer_phone`),
  ADD KEY `idx_sales_created_at` (`created_at`),
  ADD KEY `idx_sales_date` (`sale_date`),
  ADD KEY `idx_sales_status_updated` (`status`,`updated_at`);

ALTER TABLE `sale_due_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_due_customer` (`customer_id`),
  ADD KEY `idx_due_sale` (`sale_id`),
  ADD KEY `idx_due_date_status` (`due_date`,`status`);

ALTER TABLE `sale_exchange_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_sale_item_legacy_key` (`legacy_key`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `sale_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale_payments_sale` (`sale_id`),
  ADD KEY `idx_sale_payments_account` (`account_id`),
  ADD KEY `idx_sale_payments_status` (`payment_status`);

ALTER TABLE `sale_returns`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `sale_returns_v2`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_no` (`return_no`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_sale` (`sale_id`);

ALTER TABLE `sale_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_return` (`return_id`),
  ADD KEY `idx_product` (`product_id`);

ALTER TABLE `sale_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

ALTER TABLE `sale_warranties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

ALTER TABLE `service_commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `status` (`status`);

ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `shop_owners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sho_status` (`status`),
  ADD KEY `idx_sho_mobile` (`mobile`);

ALTER TABLE `sms_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient`),
  ADD KEY `idx_created` (`created_at`);

ALTER TABLE `sms_outbox`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sms_out_status` (`status`,`queued_at`),
  ADD KEY `idx_sms_out_sim` (`sim_id`,`status`);

ALTER TABLE `sms_settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `sms_sims`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sms_sim_token` (`token`),
  ADD KEY `idx_sms_sim_live` (`status`,`is_default`);

ALTER TABLE `stock_counts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_count_no` (`count_no`),
  ADD KEY `idx_count_store` (`store_id`);

ALTER TABLE `stock_count_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_count_item` (`count_id`);

ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `ref_type` (`ref_type`,`ref_id`),
  ADD KEY `movement_type` (`movement_type`);

ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_store_code` (`code`);

ALTER TABLE `store_stock`
  ADD PRIMARY KEY (`store_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

ALTER TABLE `store_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_transfer_no` (`transfer_no`),
  ADD KEY `idx_tr_status` (`status`);

ALTER TABLE `store_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tr_item` (`transfer_id`);

ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `supplier_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supdoc_supplier` (`supplier_id`);

ALTER TABLE `supplier_settlements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_spay_no` (`payment_no`),
  ADD UNIQUE KEY `uniq_spay_token` (`idempotency_token`),
  ADD KEY `idx_spay_supplier` (`supplier_id`),
  ADD KEY `idx_spay_date` (`payment_date`),
  ADD KEY `idx_spay_status` (`status`),
  ADD KEY `idx_spay_ref` (`reference_no`);

ALTER TABLE `supplier_settlement_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_spay_audit` (`settlement_id`);

ALTER TABLE `supplier_settlement_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_spay_line` (`settlement_id`),
  ADD KEY `idx_spay_memo` (`memo_no`);

ALTER TABLE `system_flags`
  ADD PRIMARY KEY (`flag_name`);

ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `system_routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_slug` (`request_slug`);

ALTER TABLE `theme_settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `unit_stock_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`,`id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_username` (`username`);

ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_module` (`user_id`,`module_key`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_key` (`role_key`);

ALTER TABLE `user_stores`
  ADD PRIMARY KEY (`user_id`,`store_id`);

ALTER TABLE `vd_styles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vd_style` (`scope`,`page_key`,`element_key`,`device`,`property`),
  ADD KEY `idx_vd_page` (`page_key`);

ALTER TABLE `whatsapp_settings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `worker_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `employee_id` (`employee_id`);

ALTER TABLE `worker_earnings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_source` (`source_type`,`source_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `work_date` (`work_date`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `pay_status` (`pay_status`);

ALTER TABLE `worker_earnings_settings`
  ADD PRIMARY KEY (`setting_key`);

ALTER TABLE `worker_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `status` (`status`);

ALTER TABLE `worker_payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `earning_id` (`earning_id`);

ALTER TABLE `worker_payment_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `effective_from` (`effective_from`);

ALTER TABLE `worker_payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `payout_date` (`payout_date`);

ALTER TABLE `workshop_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_job_no` (`job_no`),
  ADD KEY `idx_job_status` (`status`),
  ADD KEY `idx_job_customer` (`customer_id`),
  ADD KEY `idx_job_serial` (`serial_no`),
  ADD KEY `idx_job_store` (`store_id`),
  ADD KEY `idx_job_machine` (`machine_id`);

ALTER TABLE `workshop_job_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_part_job` (`job_id`),
  ADD KEY `idx_part_product` (`product_id`),
  ADD KEY `idx_part_reserve` (`product_id`,`reserved`,`issued`);

ALTER TABLE `workshop_machines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wsm_type` (`machine_type`),
  ADD KEY `idx_wsm_model` (`brand`,`model`);

ALTER TABLE `workshop_machine_parts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wsm_part` (`machine_id`,`product_id`),
  ADD KEY `idx_wsmp_product` (`product_id`);

ALTER TABLE `workshop_quotes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_quote_no` (`quote_no`),
  ADD KEY `idx_quote_status` (`status`);

ALTER TABLE `workshop_quote_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_qitem_quote` (`quote_id`);

ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `account_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

ALTER TABLE `account_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1271;

ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `banks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bank_loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bill_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bill_partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bloan_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bloan_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bloan_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bloan_installments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bloan_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `bloan_txns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `cash_closing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

ALTER TABLE `contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `contact_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `critical_action_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1359;

ALTER TABLE `customer_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `customer_call_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `customer_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

ALTER TABLE `customer_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38531;

ALTER TABLE `customer_ledgers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `customer_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9782;

ALTER TABLE `customer_rentals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `customer_to_shop_buys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `customer_withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

ALTER TABLE `damages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `dashboard_banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `display_widgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

ALTER TABLE `employee_advances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `employee_shop_deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `employee_work_descriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

ALTER TABLE `engineers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `family_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `family_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_book_folds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_book_highlights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_book_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_book_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_book_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_book_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_book_toc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `family_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_med_buys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_rx_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `family_shopping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `idempotency_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=192;

ALTER TABLE `installments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `installment_contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoice_layout_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `ledger_provenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

ALTER TABLE `loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `loan_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `loan_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `loan_installments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `loan_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `loan_recovery_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `loan_repayment_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `loan_restructure_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `loan_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `loan_settlements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `loan_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=258;

ALTER TABLE `merchants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owner_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owner_loans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owner_loan_disbursements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owner_loan_installments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owner_loan_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owner_savings_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owner_savings_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owner_savings_withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `owner_transaction_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `page_color_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `partnership_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `partnership_openings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `partner_acct_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `partner_acct_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `partner_acct_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `partner_capital_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `partner_withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_reset_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `personal_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pf_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

ALTER TABLE `pf_family_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pf_merchants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `pf_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=706;

ALTER TABLE `product_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `product_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `product_unit_factors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `product_unit_map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `product_unit_prices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `profit_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `profit_allocation_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4475;

ALTER TABLE `purchase_drafts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `purchase_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `purchase_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

ALTER TABLE `purchase_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `purchase_return_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rental_accruals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

ALTER TABLE `rental_agreements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rental_agreement_signs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rental_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rental_contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `rental_deposit_moves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `rental_hire_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rental_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `rental_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rental_owner_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rental_stock_moves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `rental_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `salaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60978;

ALTER TABLE `sale_due_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sale_exchange_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162833;

ALTER TABLE `sale_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

ALTER TABLE `sale_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sale_returns_v2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sale_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sale_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

ALTER TABLE `sale_warranties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `service_commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `shop_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `sms_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1486;

ALTER TABLE `sms_outbox`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1283;

ALTER TABLE `sms_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `sms_sims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `stock_counts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `stock_count_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=260;

ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `store_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `store_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

ALTER TABLE `supplier_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `supplier_settlements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1692;

ALTER TABLE `supplier_settlement_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

ALTER TABLE `supplier_settlement_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `system_routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `theme_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `unit_stock_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1338520802;

ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5898;

ALTER TABLE `vd_styles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `worker_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

ALTER TABLE `worker_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17052;

ALTER TABLE `worker_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `worker_payment_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

ALTER TABLE `worker_payment_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

ALTER TABLE `worker_payouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `workshop_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `workshop_job_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `workshop_machines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `workshop_machine_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `workshop_quotes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `workshop_quote_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `account_ledger`
  ADD CONSTRAINT `account_ledger_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

ALTER TABLE `contacts`
  ADD CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `contact_groups` (`id`);

ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);

ALTER TABLE `sale_services`
  ADD CONSTRAINT `sale_services_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);

ALTER TABLE `sale_warranties`
  ADD CONSTRAINT `sale_warranties_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`);
