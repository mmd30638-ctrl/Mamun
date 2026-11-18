-- APONSHOP 10.98.165
-- Idempotent schema repair for the 2026-09-24 backup.
-- Run on a staging copy first. MariaDB DDL auto-commits.

CREATE TABLE IF NOT EXISTS `ame_migrations` (
  `version` VARCHAR(64) NOT NULL PRIMARY KEY,
  `description` VARCHAR(255) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add historical timestamp columns as nullable first. Existing records remain
-- NULL instead of being assigned a false migration-time timestamp.
ALTER TABLE `accounts` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `bill_collections` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `bill_partners` ADD COLUMN IF NOT EXISTS `status` TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `bill_partners` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `brands` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `categories` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `contact_groups` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `customers` ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `customer_areas` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `customer_call_log` ADD COLUMN IF NOT EXISTS `note` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `customer_groups` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `employees` ADD COLUMN IF NOT EXISTS `emp_id` VARCHAR(30) NULL DEFAULT NULL;
ALTER TABLE `employees` ADD COLUMN IF NOT EXISTS `department` VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `employees` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `engineers` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `personal_categories` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `supplier_id` INT NULL DEFAULT NULL;
ALTER TABLE `product_units` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `salaries` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `sale_returns` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `sale_warranties` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `suppliers` ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT NULL;

-- Defaults apply only to future records; existing NULL values stay unknown.
ALTER TABLE `accounts` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `bill_collections` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `bill_partners` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `brands` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `categories` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `contact_groups` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `customers` MODIFY COLUMN `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `customer_areas` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `customer_groups` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `employees` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `engineers` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `personal_categories` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `product_units` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `salaries` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `sale_returns` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `sale_warranties` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `settings` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `suppliers` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

-- Preserve compatibility with the legacy employee endpoints without inventing
-- missing business data.
UPDATE `employees`
SET `emp_id` = NULLIF(`employee_code`, '')
WHERE (`emp_id` IS NULL OR `emp_id` = '')
  AND `employee_code` IS NOT NULL;

UPDATE `employees` AS e
LEFT JOIN `departments` AS d ON d.id = e.department_id
SET e.`department` = d.dept_name
WHERE (e.`department` IS NULL OR e.`department` = '')
  AND d.dept_name IS NOT NULL;

-- Idempotency key used by both web and CLI legacy sale-item migration.
ALTER TABLE `sale_items`
  ADD COLUMN IF NOT EXISTS `legacy_key` VARCHAR(96) NULL DEFAULT NULL;
ALTER TABLE `sale_items`
  ADD UNIQUE INDEX IF NOT EXISTS `uniq_sale_item_legacy_key` (`legacy_key`);
ALTER TABLE `products`
  ADD INDEX IF NOT EXISTS `idx_products_supplier` (`supplier_id`);

INSERT INTO `ame_migrations` (`version`, `description`)
VALUES ('001_schema_repair_10.98.165', 'Schema compatibility and sale-item idempotency')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
