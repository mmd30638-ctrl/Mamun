<?php
/**
 * APONSHOP — পূর্ণ স্কিমা ক্যাটালগ।
 * প্রতিস্থাপন নয়: যা আছে থাকে, যা নেই সেটা CREATE/ADD হয়।
 * DROP TABLE / DROP COLUMN / TRUNCATE এখানে নেই।
 */

function ame_schema_engine(): string
{
    return 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}

function ame_schema_modules(): array
{
    return [
        'settings' => 'সেটিংস / লগইন',
        'inventory' => 'স্টক / পণ্য',
        'sales' => 'বিক্রয় / POS',
        'purchase' => 'ক্রয়',
        'customers' => 'কাস্টমার',
        'accounts' => 'হিসাব / ক্যাশ',
        'suppliers' => 'সাপ্লায়ার',
        'hrm' => 'কর্মী / বেতন',
        'workshop' => 'ওয়ার্কশপ',
        'rental' => 'ভাড়া / মালিক',
        'loans' => 'ঋণ',
        'partners' => 'পার্টনারশিপ',
        'family' => 'পারিবারিক খাতা',
        'contacts' => 'কন্টাক্ট',
        'personal' => 'ব্যক্তিগত হিসাব',
        'utilities' => 'ইউটিলিটি বিল',
        'reports' => 'রিপোর্ট / লগ',
        'stores' => 'স্টোর / শাখা',
    ];
}

/**
 * যে টেবিলগুলোর CREATE overlay-এ ছিল না — লাইভ বেস ইনস্টল থেকে আসা ধরা হয়।
 * নতুন সাইট বা ভাঙা ডিবি-তে এগুলো এখান থেকে তৈরি হয়।
 *
 * @return array<string, array{module:string, sql:string, columns?:array<string,string>, indexes?:list<array{name:string, sql:string}>}>
 */
function ame_schema_base_tables(): array
{
    $e = ame_schema_engine();
    return [
        'users' => [
            'module' => 'settings',
            'sql' => "CREATE TABLE IF NOT EXISTS `users` (
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
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                removed_at DATETIME DEFAULT NULL,
                removed_by INT DEFAULT NULL,
                removed_username VARCHAR(120) DEFAULT NULL,
                UNIQUE KEY uniq_username (username)
            ) {$e}",
            'columns' => [
                'email' => 'VARCHAR(120) DEFAULT NULL',
                'photo' => 'VARCHAR(255) DEFAULT NULL',
                'phone' => 'VARCHAR(20) DEFAULT NULL',
                'whatsapp' => 'VARCHAR(20) DEFAULT NULL',
                'must_change_password' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'address' => 'VARCHAR(255) DEFAULT NULL',
                'document' => 'VARCHAR(255) DEFAULT NULL',
                'status' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'auth_version' => 'INT UNSIGNED NOT NULL DEFAULT 0',
                'locked_until' => 'DATETIME NULL DEFAULT NULL',
                'employee_id' => 'INT NULL DEFAULT NULL',
                'use_custom_perms' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
                'removed_at' => 'DATETIME DEFAULT NULL',
                'removed_by' => 'INT DEFAULT NULL',
                'removed_username' => 'VARCHAR(120) DEFAULT NULL',
            ],
            'indexes' => [
                ['name' => 'uniq_username', 'sql' => 'UNIQUE KEY uniq_username (username)'],
            ],
        ],
        'products' => [
            'module' => 'inventory',
            'sql' => "CREATE TABLE IF NOT EXISTS `products` (
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
            ) {$e}",
            'columns' => [
                'sku' => 'VARCHAR(64) DEFAULT NULL',
                'barcode' => 'VARCHAR(64) DEFAULT NULL',
                'manufacturer_name' => 'VARCHAR(150) DEFAULT NULL',
                'sub_category' => 'VARCHAR(100) DEFAULT NULL',
                'product_type' => "VARCHAR(32) NOT NULL DEFAULT 'part'",
                'short_description' => 'TEXT NULL',
                'description' => 'TEXT NULL',
                'highlights' => 'TEXT NULL',
                'tags' => 'VARCHAR(255) DEFAULT NULL',
                'internal_notes' => 'TEXT NULL',
                'reference_no' => 'VARCHAR(80) DEFAULT NULL',
                'vat_percent' => 'DECIMAL(8,2) NOT NULL DEFAULT 0',
                'discount_percent' => 'DECIMAL(8,2) NOT NULL DEFAULT 0',
                'moq' => 'DECIMAL(10,2) NOT NULL DEFAULT 1',
                'warranty_months' => 'INT DEFAULT NULL',
                'warranty_type' => 'VARCHAR(80) DEFAULT NULL',
                'return_policy' => 'TEXT NULL',
                'model_name' => 'VARCHAR(120) DEFAULT NULL',
                'size_label' => 'VARCHAR(80) DEFAULT NULL',
                'color_label' => 'VARCHAR(80) DEFAULT NULL',
                'weight_kg' => 'DECIMAL(10,3) DEFAULT NULL',
                'country_origin' => 'VARCHAR(80) DEFAULT NULL',
                'hs_code' => 'VARCHAR(32) DEFAULT NULL',
                'image_path' => 'VARCHAR(255) DEFAULT NULL',
                'seo_title' => 'VARCHAR(180) DEFAULT NULL',
                'seo_keywords' => 'VARCHAR(255) DEFAULT NULL',
                'is_old_battery' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'shelf_location' => 'VARCHAR(100) DEFAULT NULL',
                'oem_code' => 'VARCHAR(80) DEFAULT NULL',
                'supplier_code' => 'VARCHAR(80) DEFAULT NULL',
                'dealer_price' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'wholesale_price' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'min_stock' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            ],
        ],
        'categories' => [
            'module' => 'inventory',
            'sql' => "CREATE TABLE IF NOT EXISTS `categories` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                status TINYINT(1) NOT NULL DEFAULT 1,
                is_partnership_shared TINYINT(1) NOT NULL DEFAULT 0,
                is_exchange TINYINT(1) NOT NULL DEFAULT 0,
                archived TINYINT(1) NOT NULL DEFAULT 0,
                archived_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) {$e}",
            'columns' => [
                'status' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'is_partnership_shared' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'is_exchange' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'archived_at' => 'DATETIME NULL',
            ],
        ],
        'brands' => [
            'module' => 'inventory',
            'sql' => "CREATE TABLE IF NOT EXISTS `brands` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) {$e}",
            'columns' => [
                'status' => 'TINYINT(1) NOT NULL DEFAULT 1',
            ],
        ],
        'product_units' => [
            'module' => 'inventory',
            'sql' => "CREATE TABLE IF NOT EXISTS `product_units` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                unit_name VARCHAR(40) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) {$e}",
        ],
        'sale_items' => [
            'module' => 'sales',
            'sql' => "CREATE TABLE IF NOT EXISTS `sale_items` (
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
            ) {$e}",
            'columns' => [
                'item_name' => 'VARCHAR(255) NULL DEFAULT NULL',
                'legacy_key' => 'VARCHAR(96) NULL DEFAULT NULL',
            ],
            'indexes' => [
                ['name' => 'uniq_sale_item_legacy_key', 'sql' => 'UNIQUE KEY uniq_sale_item_legacy_key (legacy_key)'],
            ],
        ],
        'sale_returns' => [
            'module' => 'sales',
            'sql' => "CREATE TABLE IF NOT EXISTS `sale_returns` (
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
            ) {$e}",
            'columns' => [
                'product_id' => 'INT DEFAULT NULL',
                'qty' => 'DECIMAL(10,2) DEFAULT NULL',
                'return_type' => "VARCHAR(30) NOT NULL DEFAULT 'with_invoice'",
                'customer_name' => 'VARCHAR(100) DEFAULT NULL',
                'reason' => 'VARCHAR(255) DEFAULT NULL',
                'exchange_product_id' => 'INT DEFAULT NULL',
                'exchange_qty' => 'DECIMAL(10,2) DEFAULT NULL',
                'price_difference' => 'DECIMAL(12,2) DEFAULT NULL',
            ],
        ],
        'sale_warranties' => [
            'module' => 'reports',
            'sql' => "CREATE TABLE IF NOT EXISTS `sale_warranties` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_id INT NULL,
                product_id INT NULL,
                serial_no VARCHAR(80) NOT NULL,
                expiry_date DATE NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sw_serial (serial_no),
                INDEX idx_sw_sale (sale_id)
            ) {$e}",
        ],
        'employees' => [
            'module' => 'hrm',
            'sql' => "CREATE TABLE IF NOT EXISTS `employees` (
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
                shop_deposit_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_emp_code (employee_code)
            ) {$e}",
            'columns' => [
                'phone' => 'VARCHAR(30) DEFAULT NULL',
                'address' => 'VARCHAR(255) DEFAULT NULL',
                'photo' => 'VARCHAR(255) DEFAULT NULL',
                'salary' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
                'department_id' => 'INT NULL DEFAULT NULL',
                'salary_type' => "VARCHAR(20) NOT NULL DEFAULT 'monthly'",
                'employee_code' => 'VARCHAR(30) NULL DEFAULT NULL',
                'joining_date' => 'DATE NULL DEFAULT NULL',
                'service_share_percent' => 'DECIMAL(5,2) NOT NULL DEFAULT 50.00',
                'worker_type' => "VARCHAR(40) NOT NULL DEFAULT 'artisan'",
                'payment_type' => "VARCHAR(20) NOT NULL DEFAULT 'percentage'",
                'advance_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'shop_deposit_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            ],
        ],
        'employee_advances' => [
            'module' => 'hrm',
            'sql' => "CREATE TABLE IF NOT EXISTS `employee_advances` (
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
            ) {$e}",
            'columns' => [
                'kind' => "VARCHAR(20) NOT NULL DEFAULT 'give'",
                'previous_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'current_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'affects_cash' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'idempotency_token' => 'VARCHAR(64) NULL DEFAULT NULL',
            ],
        ],
        'employee_shop_deposits' => [
            'module' => 'hrm',
            'sql' => "CREATE TABLE IF NOT EXISTS `employee_shop_deposits` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                account_id INT NOT NULL DEFAULT 0,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                kind VARCHAR(20) NOT NULL DEFAULT 'deposit',
                deposit_date DATE NOT NULL,
                note VARCHAR(255) NULL DEFAULT NULL,
                previous_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
                current_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
                idempotency_token VARCHAR(64) NULL DEFAULT NULL,
                created_by INT NULL DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY employee_id (employee_id),
                KEY deposit_date (deposit_date),
                UNIQUE KEY uniq_shop_dep_idem (idempotency_token)
            ) {$e}",
            'columns' => [
                'kind' => "VARCHAR(20) NOT NULL DEFAULT 'deposit'",
                'previous_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'current_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'idempotency_token' => 'VARCHAR(64) NULL DEFAULT NULL',
            ],
        ],
        'engineers' => [
            'module' => 'hrm',
            'sql' => "CREATE TABLE IF NOT EXISTS `engineers` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(30) NULL DEFAULT NULL,
                employee_id INT NULL DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) {$e}",
            'columns' => [
                'employee_id' => 'INT NULL DEFAULT NULL',
                'phone' => 'VARCHAR(30) NULL DEFAULT NULL',
            ],
        ],
        'salaries' => [
            'module' => 'hrm',
            'sql' => "CREATE TABLE IF NOT EXISTS `salaries` (
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
            ) {$e}",
            'columns' => [
                'base_amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
                'bonus_amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
                'deduction_amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
                'snacks_amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
                'commission_amount' => 'DECIMAL(15,2) NOT NULL DEFAULT 0',
                'pay_mode_snapshot' => 'VARCHAR(20) NULL DEFAULT NULL',
                'pay_rule_id' => 'INT NULL DEFAULT NULL',
            ],
        ],
        'accounts' => [
            'module' => 'accounts',
            'sql' => "CREATE TABLE IF NOT EXISTS `accounts` (
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
            ) {$e}",
            'columns' => [
                'context' => "VARCHAR(20) NOT NULL DEFAULT 'business'",
                'low_balance_threshold' => 'DECIMAL(12,2) DEFAULT NULL',
                'alert_phone' => 'VARCHAR(20) DEFAULT NULL',
                'last_alert_sent_at' => 'DATETIME DEFAULT NULL',
                'account_number' => 'VARCHAR(50) DEFAULT NULL',
                'bank_branch' => 'VARCHAR(150) DEFAULT NULL',
                'is_default' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'owner_member_id' => 'INT NULL',
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
            ],
        ],
        'account_ledger' => [
            'module' => 'accounts',
            'sql' => "CREATE TABLE IF NOT EXISTS `account_ledger` (
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
            ) {$e}",
            'columns' => [
                'is_reconciled' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'attachment' => 'VARCHAR(255) DEFAULT NULL',
                'category_id' => 'INT DEFAULT NULL',
                'merchant_id' => 'INT DEFAULT NULL',
                'member_id' => 'INT DEFAULT NULL',
            ],
        ],
        'suppliers' => [
            'module' => 'suppliers',
            'sql' => "CREATE TABLE IF NOT EXISTS `suppliers` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(30) DEFAULT NULL,
                address TEXT DEFAULT NULL,
                total_due DECIMAL(12,2) NOT NULL DEFAULT 0,
                credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
                status ENUM('active','inactive') NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                email VARCHAR(150) DEFAULT NULL,
                alt_phone VARCHAR(30) DEFAULT NULL,
                business_name VARCHAR(150) DEFAULT NULL,
                business_type VARCHAR(80) DEFAULT NULL,
                tax_number VARCHAR(60) DEFAULT NULL,
                trade_license_no VARCHAR(60) DEFAULT NULL,
                website VARCHAR(150) DEFAULT NULL,
                payment_terms VARCHAR(80) DEFAULT NULL,
                preferred_payment_method VARCHAR(40) DEFAULT NULL,
                bank_name VARCHAR(120) DEFAULT NULL,
                bank_account_no VARCHAR(60) DEFAULT NULL,
                bank_branch VARCHAR(120) DEFAULT NULL,
                opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
                supply_categories VARCHAR(255) DEFAULT NULL,
                lead_time_days INT DEFAULT NULL,
                minimum_order_note VARCHAR(120) DEFAULT NULL,
                supply_notes TEXT DEFAULT NULL,
                rating TINYINT DEFAULT NULL,
                performance_note TEXT DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL
            ) {$e}",
            'columns' => [
                'total_due' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'credit_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'status' => "ENUM('active','inactive') NOT NULL DEFAULT 'active'",
                'email' => 'VARCHAR(150) DEFAULT NULL',
                'alt_phone' => 'VARCHAR(30) DEFAULT NULL',
                'business_name' => 'VARCHAR(150) DEFAULT NULL',
                'business_type' => 'VARCHAR(80) DEFAULT NULL',
                'tax_number' => 'VARCHAR(60) DEFAULT NULL',
                'trade_license_no' => 'VARCHAR(60) DEFAULT NULL',
                'website' => 'VARCHAR(150) DEFAULT NULL',
                'payment_terms' => 'VARCHAR(80) DEFAULT NULL',
                'preferred_payment_method' => 'VARCHAR(40) DEFAULT NULL',
                'bank_name' => 'VARCHAR(120) DEFAULT NULL',
                'bank_account_no' => 'VARCHAR(60) DEFAULT NULL',
                'bank_branch' => 'VARCHAR(120) DEFAULT NULL',
                'opening_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'created_at' => 'DATETIME NULL DEFAULT CURRENT_TIMESTAMP',
                'supply_categories' => 'VARCHAR(255) DEFAULT NULL',
                'lead_time_days' => 'INT DEFAULT NULL',
                'minimum_order_note' => 'VARCHAR(120) DEFAULT NULL',
                'supply_notes' => 'TEXT DEFAULT NULL',
                'rating' => 'TINYINT DEFAULT NULL',
                'performance_note' => 'TEXT DEFAULT NULL',
                'updated_at' => 'DATETIME DEFAULT NULL',
                'party_code' => 'VARCHAR(40) DEFAULT NULL',
                'credit_limit' => 'DECIMAL(14,2) NOT NULL DEFAULT 0',
            ],
        ],
        'supplier_documents' => [
            'module' => 'suppliers',
            'sql' => "CREATE TABLE IF NOT EXISTS `supplier_documents` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                supplier_id INT NOT NULL,
                title VARCHAR(150) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_type VARCHAR(20) DEFAULT NULL,
                file_size INT NOT NULL DEFAULT 0,
                uploaded_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_supdoc_supplier (supplier_id)
            ) {$e}",
        ],
        'supplier_settlements' => [
            'module' => 'suppliers',
            'sql' => "CREATE TABLE IF NOT EXISTS `supplier_settlements` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payment_no VARCHAR(40) NOT NULL,
                supplier_id INT NOT NULL,
                payment_date DATE NOT NULL,
                amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'BDT',
                fx_rate DECIMAL(14,6) NOT NULL DEFAULT 1,
                amount_bdt DECIMAL(14,2) NOT NULL DEFAULT 0,
                method VARCHAR(30) NOT NULL DEFAULT 'cash',
                account_id INT NOT NULL DEFAULT 0,
                reference_no VARCHAR(80) DEFAULT NULL,
                cheque_no VARCHAR(40) DEFAULT NULL,
                note VARCHAR(500) DEFAULT NULL,
                attachment VARCHAR(255) DEFAULT NULL,
                allocation_mode VARCHAR(20) NOT NULL DEFAULT 'oldest',
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                previous_due DECIMAL(14,2) NOT NULL DEFAULT 0,
                remaining_due DECIMAL(14,2) NOT NULL DEFAULT 0,
                ledger_id INT DEFAULT NULL,
                idempotency_token VARCHAR(80) DEFAULT NULL,
                created_by INT NULL,
                approved_by INT NULL,
                approved_at DATETIME NULL,
                paid_at DATETIME NULL,
                reversed_by INT NULL,
                reversed_at DATETIME NULL,
                reversal_reason VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_spay_no (payment_no),
                UNIQUE KEY uniq_spay_token (idempotency_token),
                INDEX idx_spay_supplier (supplier_id),
                INDEX idx_spay_date (payment_date),
                INDEX idx_spay_status (status),
                INDEX idx_spay_ref (reference_no)
            ) {$e}",
            'columns' => [
                'cheque_no' => 'VARCHAR(40) DEFAULT NULL',
            ],
        ],
        'supplier_settlement_lines' => [
            'module' => 'suppliers',
            'sql' => "CREATE TABLE IF NOT EXISTS `supplier_settlement_lines` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                settlement_id INT NOT NULL,
                memo_no VARCHAR(50) NOT NULL DEFAULT '',
                allocated DECIMAL(14,2) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_spay_line (settlement_id),
                INDEX idx_spay_memo (memo_no)
            ) {$e}",
        ],
        'supplier_settlement_audit' => [
            'module' => 'suppliers',
            'sql' => "CREATE TABLE IF NOT EXISTS `supplier_settlement_audit` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                settlement_id INT NOT NULL,
                action VARCHAR(30) NOT NULL,
                user_id INT NULL,
                note VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_spay_audit (settlement_id)
            ) {$e}",
        ],
        // ⭐ "দোকান মালিক ও ঋণ ব্যবস্থাপনা" (Shop Owner & Loan Management) — ঋণ
        // মডিউলের ভেতরেই নতুন সেট, বিদ্যমান customer-lending (loans/loan_applications)
        // ও bank_loans সিস্টেম স্পর্শ না করে। দোকান-মালিক (owner) এখন থেকে একটা
        // প্রথম-শ্রেণীর সত্তা, আলাদা ব্যাংক মাস্টার-লিস্ট, এবং লোন+সঞ্চয় দুই আলাদা
        // লেজারে যাবে (কোর অ্যাকাউন্টিং রুল অনুযায়ী)।
        'shop_owners' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `shop_owners` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_name VARCHAR(150) NOT NULL,
                parent_name VARCHAR(150) DEFAULT NULL,
                nid_no VARCHAR(60) DEFAULT NULL,
                mobile VARCHAR(30) DEFAULT NULL,
                address TEXT DEFAULT NULL,
                shop_name VARCHAR(150) DEFAULT NULL,
                shop_type VARCHAR(80) DEFAULT NULL,
                business_address TEXT DEFAULT NULL,
                trade_license_no VARCHAR(80) DEFAULT NULL,
                photo VARCHAR(255) DEFAULT NULL,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sho_status (status),
                INDEX idx_sho_mobile (mobile)
            ) {$e}",
        ],
        'owner_documents' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `owner_documents` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_id INT NOT NULL,
                doc_category VARCHAR(30) NOT NULL DEFAULT 'owner',
                ref_id INT NULL,
                title VARCHAR(150) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_type VARCHAR(20) DEFAULT NULL,
                file_size INT NOT NULL DEFAULT 0,
                uploaded_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_odoc_owner (owner_id)
            ) {$e}",
        ],
        'banks' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `banks` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bank_name VARCHAR(150) NOT NULL,
                branch_name VARCHAR(150) DEFAULT NULL,
                branch_address TEXT DEFAULT NULL,
                account_info VARCHAR(255) DEFAULT NULL,
                contact_info VARCHAR(255) DEFAULT NULL,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bank_status (status)
            ) {$e}",
        ],
        'owner_loans' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `owner_loans` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_id INT NOT NULL,
                bank_id INT NULL,
                loan_type VARCHAR(80) DEFAULT NULL,
                loan_purpose VARCHAR(255) DEFAULT NULL,
                approved_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
                loan_term_months INT NOT NULL DEFAULT 0,
                installment_count INT NOT NULL DEFAULT 0,
                installment_frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
                installment_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                approval_date DATE NULL,
                disbursement_date DATE NULL,
                total_payable DECIMAL(14,2) NOT NULL DEFAULT 0,
                paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                outstanding_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_ol_owner (owner_id),
                INDEX idx_ol_bank (bank_id),
                INDEX idx_ol_status (status)
            ) {$e}",
        ],
        'owner_loan_disbursements' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `owner_loan_disbursements` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                loan_id INT NOT NULL,
                owner_id INT NOT NULL,
                bank_id INT NULL,
                disbursement_date DATE NOT NULL,
                approved_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                disbursed_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                processing_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
                other_charges DECIMAL(12,2) NOT NULL DEFAULT 0,
                net_amount_received DECIMAL(14,2) NOT NULL DEFAULT 0,
                bank_ref_no VARCHAR(100) DEFAULT NULL,
                payment_method VARCHAR(30) DEFAULT NULL,
                account_id INT NULL,
                remarks VARCHAR(255) DEFAULT NULL,
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_old_loan (loan_id)
            ) {$e}",
        ],
        'owner_loan_installments' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `owner_loan_installments` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                loan_id INT NOT NULL,
                installment_no INT NOT NULL,
                due_date DATE NOT NULL,
                principal_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                interest_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                other_charges DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_installment DECIMAL(14,2) NOT NULL DEFAULT 0,
                paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                remaining_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                payment_date DATE NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'upcoming',
                INDEX idx_oli_loan (loan_id),
                INDEX idx_oli_due (due_date),
                INDEX idx_oli_status (status)
            ) {$e}",
        ],
        'owner_loan_payments' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `owner_loan_payments` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                loan_id INT NOT NULL,
                owner_id INT NOT NULL,
                installment_id INT NULL,
                payment_date DATE NOT NULL,
                principal_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
                interest_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
                penalty_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_payment DECIMAL(14,2) NOT NULL DEFAULT 0,
                payment_method VARCHAR(30) DEFAULT NULL,
                account_id INT NULL,
                reference_no VARCHAR(100) DEFAULT NULL,
                collected_by INT NULL,
                remarks VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_olp_loan (loan_id),
                INDEX idx_olp_owner (owner_id)
            ) {$e}",
        ],
        'owner_savings_accounts' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `owner_savings_accounts` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_id INT NOT NULL,
                account_opening_date DATE NOT NULL,
                opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
                current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_osa_owner (owner_id)
            ) {$e}",
        ],
        'owner_savings_transactions' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `owner_savings_transactions` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                savings_account_id INT NOT NULL,
                owner_id INT NOT NULL,
                txn_date DATE NOT NULL,
                txn_type VARCHAR(20) NOT NULL DEFAULT 'deposit',
                amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                previous_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
                new_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
                payment_method VARCHAR(30) DEFAULT NULL,
                reference_no VARCHAR(100) DEFAULT NULL,
                collected_by INT NULL,
                remarks VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ost_account (savings_account_id),
                INDEX idx_ost_owner (owner_id)
            ) {$e}",
        ],
        'owner_savings_withdrawals' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `owner_savings_withdrawals` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                savings_account_id INT NOT NULL,
                owner_id INT NOT NULL,
                withdrawal_date DATE NOT NULL,
                amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                previous_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
                remaining_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
                reason VARCHAR(255) DEFAULT NULL,
                approved_by INT NULL,
                paid_by INT NULL,
                payment_method VARCHAR(30) DEFAULT NULL,
                reference_no VARCHAR(100) DEFAULT NULL,
                remarks VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_osw_account (savings_account_id)
            ) {$e}",
        ],
        'owner_transaction_ledger' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `owner_transaction_ledger` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                owner_id INT NOT NULL,
                loan_id INT NULL,
                savings_account_id INT NULL,
                txn_date DATE NOT NULL,
                txn_type VARCHAR(30) NOT NULL,
                description VARCHAR(255) DEFAULT NULL,
                debit DECIMAL(14,2) NOT NULL DEFAULT 0,
                credit DECIMAL(14,2) NOT NULL DEFAULT 0,
                balance DECIMAL(14,2) NOT NULL DEFAULT 0,
                reference VARCHAR(100) DEFAULT NULL,
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_otl_owner (owner_id),
                INDEX idx_otl_loan (loan_id)
            ) {$e}",
        ],
        'expenses' => [
            'module' => 'accounts',
            'sql' => "CREATE TABLE IF NOT EXISTS `expenses` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(120) NOT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                account_id INT NULL,
                expense_date DATE NOT NULL,
                note VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_exp_date (expense_date)
            ) {$e}",
        ],
        'activity_logs' => [
            'module' => 'reports',
            'sql' => "CREATE TABLE IF NOT EXISTS `activity_logs` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                username VARCHAR(120) DEFAULT NULL,
                action VARCHAR(80) NOT NULL,
                module VARCHAR(80) DEFAULT NULL,
                details TEXT,
                action_type VARCHAR(40) DEFAULT NULL,
                page_url VARCHAR(255) DEFAULT NULL,
                entity_type VARCHAR(40) DEFAULT NULL,
                entity_id VARCHAR(40) DEFAULT NULL,
                reference VARCHAR(80) DEFAULT NULL,
                product_id INT NULL,
                product_name VARCHAR(180) DEFAULT NULL,
                party_type VARCHAR(20) DEFAULT NULL,
                party_id INT NULL,
                party_name VARCHAR(180) DEFAULT NULL,
                amount_total DECIMAL(14,2) NULL,
                amount_paid DECIMAL(14,2) NULL,
                amount_due DECIMAL(14,2) NULL,
                amount_discount DECIMAL(14,2) NULL,
                old_value TEXT,
                new_value TEXT,
                old_json TEXT,
                new_json TEXT,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                device_info VARCHAR(80) DEFAULT NULL,
                session_key VARCHAR(64) DEFAULT NULL,
                prev_hash CHAR(64) DEFAULT NULL,
                row_hash CHAR(64) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_al_user (user_id),
                INDEX idx_al_created (created_at)
            ) {$e}",
        ],
        'contacts' => [
            'module' => 'contacts',
            'sql' => "CREATE TABLE IF NOT EXISTS `contacts` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NULL,
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(30) DEFAULT NULL,
                address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ct_group (group_id)
            ) {$e}",
        ],
        'contact_groups' => [
            'module' => 'contacts',
            'sql' => "CREATE TABLE IF NOT EXISTS `contact_groups` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_name VARCHAR(120) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) {$e}",
        ],
        'bill_partners' => [
            'module' => 'utilities',
            'sql' => "CREATE TABLE IF NOT EXISTS `bill_partners` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                monthly_share DECIMAL(12,2) NOT NULL DEFAULT 0,
                phone VARCHAR(30) DEFAULT NULL,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) {$e}",
        ],
        'bill_collections' => [
            'module' => 'utilities',
            'sql' => "CREATE TABLE IF NOT EXISTS `bill_collections` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                partner_id INT NOT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                month_year VARCHAR(40) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bc_partner (partner_id)
            ) {$e}",
        ],
        'loans' => [
            'module' => 'loans',
            'sql' => "CREATE TABLE IF NOT EXISTS `loans` (
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
            ) {$e}",
            'columns' => [
                'customer_id' => 'INT DEFAULT NULL',
                'product_id' => 'INT DEFAULT NULL',
                'application_id' => 'INT DEFAULT NULL',
                'interest_rate' => 'DECIMAL(5,2) DEFAULT NULL',
                'interest_type' => 'VARCHAR(20) DEFAULT NULL',
                'term_months' => 'INT DEFAULT NULL',
                'disbursed_at' => 'DATETIME DEFAULT NULL',
                'disbursed_by' => 'INT DEFAULT NULL',
                'disbursement_account_id' => 'INT DEFAULT NULL',
                'next_due_date' => 'DATE DEFAULT NULL',
            ],
        ],
        'profit_allocations' => [
            'module' => 'partners',
            'sql' => "CREATE TABLE IF NOT EXISTS `profit_allocations` (
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
            ) {$e}",
        ],
        'profit_allocation_details' => [
            'module' => 'partners',
            'sql' => "CREATE TABLE IF NOT EXISTS `profit_allocation_details` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                allocation_id INT NOT NULL,
                partner_id INT NOT NULL,
                share_percentage_used DECIMAL(5,2) NOT NULL DEFAULT 0,
                allocated_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                INDEX idx_pad_alloc (allocation_id)
            ) {$e}",
        ],
        'personal_categories' => [
            'module' => 'personal',
            'sql' => "CREATE TABLE IF NOT EXISTS `personal_categories` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'expense',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) {$e}",
        ],
        'invoice_layout_templates' => [
            'module' => 'settings',
            'sql' => "CREATE TABLE IF NOT EXISTS `invoice_layout_templates` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                layout_json LONGTEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 0,
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) {$e}",
        ],
        'system_routes' => [
            'module' => 'settings',
            'sql' => "CREATE TABLE IF NOT EXISTS `system_routes` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_slug VARCHAR(120) NOT NULL,
                target_file VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY uniq_slug (request_slug)
            ) {$e}",
        ],
        'customer_call_log' => [
            'module' => 'customers',
            'sql' => "CREATE TABLE IF NOT EXISTS `customer_call_log` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                log_date DATE NOT NULL,
                outcome VARCHAR(40) NOT NULL DEFAULT 'called',
                note VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_customer_date (customer_id, log_date)
            ) {$e}",
        ],
    ];
}

/**
 * Overlay-এ CREATE আছে এমন টেবিল — সিঙ্কের দ্বিতীয় ধাপে ensure_* চালায়।
 * রিপোর্টে দেখানোর জন্য নামের তালিকা।
 */
function ame_schema_overlay_tables(): array
{
    return [
        'settings' => ['settings', 'dashboard_banners', 'theme_settings', 'vd_styles', 'display_widgets', 'pos_title_line', 'invoice_settings', 'sms_settings', 'sms_sims', 'whatsapp_settings', 'sms_log', 'sms_outbox', 'user_roles', 'role_permissions', 'user_permissions', 'login_attempts', 'password_resets', 'password_reset_otps', 'critical_action_otps', 'system_flags', 'app_settings', 'idempotency_keys'],
        'sales' => ['sales', 'sale_services', 'sale_payments', 'sale_exchange_items', 'sale_returns_v2', 'sale_return_items', 'stock_movements'],
        'purchase' => ['purchases', 'purchase_expenses', 'purchase_drafts', 'purchase_returns', 'purchase_return_items', 'purchase_return_logs'],
        'customers' => ['customers', 'customer_ledger', 'customer_payments', 'customer_withdrawals', 'customer_groups', 'customer_areas', 'installment_contracts', 'rental_contracts', 'rental_deposit_moves', 'rental_accruals', 'rental_history', 'rental_hire_payments', 'rental_stock_moves', 'rental_settings'],
        'accounts' => ['merchants', 'expense_categories', 'cash_closing', 'ledger_provenance', 'ledger_provenance_meta', 'pf_family_members', 'pf_categories', 'pf_merchants'],
        'hrm' => ['departments', 'positions', 'attendance', 'leave_requests', 'service_commissions', 'worker_payouts', 'worker_payment_rules', 'worker_earnings', 'worker_payments', 'worker_payment_allocations', 'worker_audit_log', 'worker_earnings_settings'],
        'workshop' => ['workshop_jobs', 'workshop_job_parts', 'workshop_machines', 'workshop_machine_parts', 'workshop_quotes', 'workshop_quote_items', 'product_serials'],
        'stores' => ['stores', 'store_stock', 'user_stores', 'store_transfers', 'store_transfer_items', 'stock_counts', 'stock_count_items'],
        'rental' => ['rental_owners', 'rental_vehicles', 'rental_owner_payments', 'rental_audit'],
        'loans' => ['loan_products', 'loan_applications', 'loan_repayment_schedule', 'loan_recovery_actions', 'loan_restructure_log', 'loan_settings', 'loan_fees', 'loan_settlements', 'bank_loans', 'loan_installments', 'loan_transactions'],
        'partners' => ['partners', 'partner_withdrawals', 'partnership_adjustments', 'partnership_openings', 'partner_capital_transactions', 'partner_acct_expenses', 'partner_acct_payments', 'partner_acct_months', 'partner_acct_audit'],
        'family' => ['family_groups', 'family_contacts', 'family_members', 'family_doctors', 'family_medicines', 'family_med_buys', 'family_books', 'family_book_pages', 'family_book_folds', 'family_book_notes', 'family_book_highlights', 'family_book_progress', 'family_book_history', 'family_book_toc', 'family_rx_lines', 'family_shopping', 'family_inventory', 'family_expenses', 'family_events', 'family_documents', 'family_notes', 'family_access'],
    ];
}

function ame_schema_expected_tables(): array
{
    $out = array_keys(ame_schema_base_tables());
    foreach (ame_schema_overlay_tables() as $tables) {
        foreach ($tables as $t) {
            $out[] = $t;
        }
    }
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

function ame_schema_extra_columns(): array
{
    $base = [];
    foreach (ame_schema_base_tables() as $table => $def) {
        if (!empty($def['columns'])) {
            $base[$table] = $def['columns'];
        }
    }
    $base['settings'] = array_merge($base['settings'] ?? [], [
        'shop_logo' => 'VARCHAR(255) DEFAULT NULL',
        'shop_tagline' => 'VARCHAR(180) NULL DEFAULT NULL',
        'default_pay_account_id' => 'INT NULL DEFAULT NULL',
        'brand_type_json' => 'TEXT NULL',
    ]);
    $base['customers'] = array_merge($base['customers'] ?? [], [
        'due_watch' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'due_watch_on' => 'DATE DEFAULT NULL',
        'due_notify_suppressed' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'next_due_date' => 'DATE DEFAULT NULL',
        'next_due_amount' => 'DECIMAL(12,2) NULL DEFAULT NULL',
        'due_note' => 'VARCHAR(255) DEFAULT NULL',
        'is_borrower' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'guarantor_name' => 'VARCHAR(150) DEFAULT NULL',
        'guarantor_phone' => 'VARCHAR(20) DEFAULT NULL',
        'group_id' => 'INT DEFAULT NULL',
        'area_id' => 'INT DEFAULT NULL',
    ]);
    $base['sales'] = array_merge($base['sales'] ?? [], [
        'sale_date' => 'DATETIME NULL',
        'vat_percent' => 'DECIMAL(5,2) NOT NULL DEFAULT 0',
        'vat_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'service_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'rounding_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'due_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'offline_client_ref' => 'VARCHAR(80) NULL',
        'guest_customer_name' => 'VARCHAR(150) NULL',
        'guest_customer_phone' => 'VARCHAR(30) NULL',
        'store_id' => 'INT NULL',
        'exchange_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'cash_paid_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'vehicle_no' => 'VARCHAR(40) DEFAULT NULL',
    ]);
    $base['purchases'] = array_merge($base['purchases'] ?? [], [
        'memo_no' => 'VARCHAR(50) NULL',
        'dealer_price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'payment_type' => "VARCHAR(10) NOT NULL DEFAULT 'cash'",
        'paid_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'due_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'notes' => 'VARCHAR(500) NULL',
        'store_id' => 'INT NULL',
    ]);
    $base['rental_owners'] = array_merge($base['rental_owners'] ?? [], [
        'vehicle_no' => 'VARCHAR(40) DEFAULT NULL',
        'vehicle_name' => 'VARCHAR(120) DEFAULT NULL',
        'entry_date' => 'DATE DEFAULT NULL',
    ]);
    $base['rental_contracts'] = array_merge($base['rental_contracts'] ?? [], [
        'product_id' => 'INT NULL DEFAULT NULL',
        'product_sku' => 'VARCHAR(80) DEFAULT NULL',
        'serial_no' => 'VARCHAR(80) DEFAULT NULL',
        'barcode' => 'VARCHAR(80) DEFAULT NULL',
        'product_category' => 'VARCHAR(120) DEFAULT NULL',
        'qty' => 'DECIMAL(12,3) NOT NULL DEFAULT 1',
        'start_date' => 'DATE DEFAULT NULL',
        'expected_return' => 'DATE DEFAULT NULL',
        'billing_start' => 'DATE DEFAULT NULL',
        'billing_day' => 'TINYINT NOT NULL DEFAULT 1',
        'billing_method' => "VARCHAR(24) NOT NULL DEFAULT 'calendar_month'",
        'advance_usage' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'account_id' => 'INT NULL DEFAULT NULL',
        'payment_method' => "VARCHAR(30) NOT NULL DEFAULT 'CASH'",
        'notes' => 'TEXT DEFAULT NULL',
        'employee_id' => 'INT NULL DEFAULT NULL',
        'created_by' => 'INT NULL DEFAULT NULL',
        'pause_start' => 'DATE DEFAULT NULL',
        'pause_end' => 'DATE DEFAULT NULL',
        'late_fee_type' => "VARCHAR(20) NOT NULL DEFAULT 'none'",
        'late_fee_value' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'returned_at' => 'DATETIME DEFAULT NULL',
        'return_condition' => 'VARCHAR(40) DEFAULT NULL',
        'meter_reading' => 'VARCHAR(40) DEFAULT NULL',
        'damage_notes' => 'TEXT DEFAULT NULL',
        'damage_charge' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'received_by' => 'INT NULL DEFAULT NULL',
        'parent_contract_id' => 'INT NULL DEFAULT NULL',
        'cancelled_at' => 'DATETIME DEFAULT NULL',
        'cancel_reason' => 'TEXT DEFAULT NULL',
        'updated_at' => 'DATETIME DEFAULT NULL',
        'monthly_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'contract_letter' => 'MEDIUMTEXT NULL',
        'rental_source' => "VARCHAR(12) NOT NULL DEFAULT 'OWN'",
        'item_kind' => "VARCHAR(24) NOT NULL DEFAULT 'product'",
        'owner_deposit' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'owner_id' => 'INT DEFAULT NULL',
        'owner_rate' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'owner_cycle' => "VARCHAR(16) NOT NULL DEFAULT 'monthly'",
        'owner_custom_days' => 'INT NOT NULL DEFAULT 7',
        'owner_start_date' => 'DATE DEFAULT NULL',
        'vehicle_no' => 'VARCHAR(40) DEFAULT NULL',
        'vehicle_id' => 'INT DEFAULT NULL',
    ]);
    $base['partners'] = array_merge($base['partners'] ?? [], [
        'partner_type' => "VARCHAR(30) NOT NULL DEFAULT 'equity'",
        'phone' => 'VARCHAR(20) NULL',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
        'opening_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'initial_capital' => 'DECIMAL(14,2) NOT NULL DEFAULT 0',
    ]);
    $base['sms_settings'] = array_merge($base['sms_settings'] ?? [], [
        'channel' => "VARCHAR(20) NOT NULL DEFAULT 'sim'",
    ]);
    $base['activity_logs'] = array_merge($base['activity_logs'] ?? [], [
        'username' => 'VARCHAR(120) DEFAULT NULL',
        'action_type' => 'VARCHAR(40) DEFAULT NULL',
        'page_url' => 'VARCHAR(255) DEFAULT NULL',
        'entity_type' => 'VARCHAR(40) DEFAULT NULL',
        'entity_id' => 'VARCHAR(40) DEFAULT NULL',
        'reference' => 'VARCHAR(80) DEFAULT NULL',
        'product_id' => 'INT NULL',
        'product_name' => 'VARCHAR(180) DEFAULT NULL',
        'party_type' => 'VARCHAR(20) DEFAULT NULL',
        'party_id' => 'INT NULL',
        'party_name' => 'VARCHAR(180) DEFAULT NULL',
        'amount_total' => 'DECIMAL(14,2) NULL',
        'amount_paid' => 'DECIMAL(14,2) NULL',
        'amount_due' => 'DECIMAL(14,2) NULL',
        'amount_discount' => 'DECIMAL(14,2) NULL',
        'old_value' => 'TEXT NULL',
        'new_value' => 'TEXT NULL',
        'old_json' => 'TEXT NULL',
        'new_json' => 'TEXT NULL',
        'ip_address' => 'VARCHAR(45) DEFAULT NULL',
        'user_agent' => 'VARCHAR(255) DEFAULT NULL',
        'device_info' => 'VARCHAR(80) DEFAULT NULL',
        'session_key' => 'VARCHAR(64) DEFAULT NULL',
        'prev_hash' => 'CHAR(64) DEFAULT NULL',
        'row_hash' => 'CHAR(64) DEFAULT NULL',
    ]);
    return $base;
}

/**
 * স্পিড — ঘন ঘন ফিল্টার/সর্ট হওয়া কলামে ইনডেক্স, যেগুলো টেবিল তৈরির সময় বাদ
 * পড়ে গিয়েছিল (যেমন `sales.created_at`, `customers.phone`)। ame_schema_extra_columns()-এর
 * মতোই এটা `ame_schema_base_tables()`-এ তালিকাভুক্ত না-থাকা টেবিলেও (sales,
 * customers) কাজ করে — শুধু টেবিলটা বাস্তবে থাকলেই ইনডেক্স যোগ হয়, ডেটা
 * স্পর্শ করা হয় না, এবং আগে থেকে থাকলে আবার যোগ করার চেষ্টা হয় না।
 */
function ame_schema_extra_indexes(): array
{
    return [
        'sales' => [
            ['name' => 'idx_sales_created_at', 'sql' => 'INDEX idx_sales_created_at (created_at)'],
            ['name' => 'idx_sales_date', 'sql' => 'INDEX idx_sales_date (sale_date)'],
            // "সাম্প্রতিক বিক্রয়/ড্রাফট" ড্রয়ার খুললে ঠিক এই ধরনের কোয়েরি চলে:
            // WHERE status='draft' ... ORDER BY updated_at DESC LIMIT 50।
            // sales টেবিলটা অনেক আগে তৈরি হয়ে থাকলে status/updated_at-এ কোনো
            // ইনডেক্স নাও থাকতে পারে (নতুন টেবিলে শুধু status-এর একটা একক
            // ইনডেক্স তৈরি হয়, updated_at-এ কখনোই হয় না) — ফলে বিক্রয়ের সংখ্যা
            // বেড়ে গেলে পুরো টেবিল স্ক্যান করে প্রতিবার ড্রয়ার খুলতে সময় নিত।
            ['name' => 'idx_sales_status_updated', 'sql' => 'INDEX idx_sales_status_updated (status, updated_at)'],
            ['name' => 'idx_sales_customer_id', 'sql' => 'INDEX idx_sales_customer_id (customer_id)'],
            ['name' => 'idx_sales_customer_date', 'sql' => 'INDEX idx_sales_customer_date (customer_id, sale_date)'],
        ],
        'customer_payments' => [
            ['name' => 'idx_cp_customer_date', 'sql' => 'INDEX idx_cp_customer_date (customer_id, payment_date)'],
        ],
        'customers' => [
            ['name' => 'idx_cust_phone', 'sql' => 'INDEX idx_cust_phone (phone)'],
            ['name' => 'idx_cust_name', 'sql' => 'INDEX idx_cust_name (name)'],
        ],
        'products' => [
            ['name' => 'idx_prod_status', 'sql' => 'INDEX idx_prod_status (status)'],
            ['name' => 'idx_prod_barcode', 'sql' => 'INDEX idx_prod_barcode (barcode)'],
        ],
        // পারফরম্যান্স ফিক্স: পাওনা/দেনা "এজিং" রিপোর্টে (payable_ageing.php)
        // প্রতিটা সাপ্লায়ারের জন্য account_ledger-এ ref_type+ref_id দিয়ে
        // খোঁজা হয়, কিন্তু এই দুটো কলামে কোনো ইনডেক্স ছিল না — ফলে প্রতিটা
        // সাপ্লায়ারের জন্য পুরো account_ledger টেবিল স্ক্যান হতো। এখন
        // stock_movements টেবিলের মতোই একটা কম্পোজিট ইনডেক্স যোগ করা হলো।
        'account_ledger' => [
            ['name' => 'idx_al_ref', 'sql' => 'INDEX idx_al_ref (ref_type, ref_id)'],
        ],
        'activity_logs' => [
            ['name' => 'idx_al_action_type', 'sql' => 'INDEX idx_al_action_type (action_type, created_at)'],
            ['name' => 'idx_al_module', 'sql' => 'INDEX idx_al_module (module)'],
            ['name' => 'idx_al_reference', 'sql' => 'INDEX idx_al_reference (reference)'],
        ],
    ];
}
