<?php
/**
 * Customer hire / rental contract schema.
 * Extends existing rental_contracts. Does not replace owner-side rental_owners.
 * Never DROP / TRUNCATE.
 */

function hire_schema_add_columns(mysqli $db, string $table, array $columns): void
{
    $have = [];
    $res = @$db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $have[$row['Field']] = true;
        }
    }
    foreach ($columns as $name => $ddl) {
        if (isset($have[$name])) {
            continue;
        }
        $safe = preg_replace('/[^a-z0-9_]/i', '', $name);
        if ($safe === '') {
            continue;
        }
        @$db->query('SET SESSION lock_wait_timeout = 8');
        @$db->query('ALTER TABLE `' . str_replace('`', '', $table) . '` ADD COLUMN `' . $safe . '` ' . $ddl);
    }
}

function hire_schema_add_index(mysqli $db, string $table, string $name, string $ddl): void
{
    $safe = $db->real_escape_string($name);
    $tbl = str_replace('`', '', $table);
    $res = @$db->query("SHOW INDEX FROM `{$tbl}` WHERE Key_name = '{$safe}'");
    if ($res && $res->num_rows > 0) {
        return;
    }
    @$db->query('ALTER TABLE `' . $tbl . '` ADD ' . $ddl);
}

function ensure_hire_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('hire_schema_v771')) {
        $done = true;
        return;
    }
    $done = true;

    $old = mysqli_report(MYSQLI_REPORT_OFF);

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_contracts (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    hire_schema_add_columns($db, 'rental_contracts', [
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
    ]);

    hire_schema_add_index($db, 'rental_contracts', 'idx_rc_status_start', 'INDEX idx_rc_status_start (status, start_date)');
    hire_schema_add_index($db, 'rental_contracts', 'idx_rc_serial', 'INDEX idx_rc_serial (serial_no)');
    hire_schema_add_index($db, 'rental_contracts', 'idx_rc_product', 'INDEX idx_rc_product (product_id)');
    hire_schema_add_index($db, 'rental_contracts', 'idx_rc_expected', 'INDEX idx_rc_expected (expected_return)');

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_deposit_moves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            move_no VARCHAR(40) NOT NULL,
            contract_id INT NOT NULL,
            customer_id INT NOT NULL,
            kind VARCHAR(16) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            remaining_after DECIMAL(12,2) NOT NULL DEFAULT 0,
            account_id INT NULL DEFAULT NULL,
            payment_method VARCHAR(30) DEFAULT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            approved_by INT NULL DEFAULT NULL,
            created_by INT NULL DEFAULT NULL,
            ledger_ref VARCHAR(50) DEFAULT NULL,
            reversed TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rdm_contract (contract_id),
            INDEX idx_rdm_customer (customer_id),
            INDEX idx_rdm_kind (kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_accruals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            accrual_no VARCHAR(50) NOT NULL,
            contract_id INT NOT NULL,
            customer_id INT NOT NULL,
            period_key VARCHAR(24) NOT NULL,
            period_date DATE NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            rate_used DECIMAL(12,2) NOT NULL DEFAULT 0,
            rate_type VARCHAR(20) NOT NULL DEFAULT 'DAILY',
            ledger_ref VARCHAR(50) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_racc_period (contract_id, period_key),
            INDEX idx_racc_cust (customer_id),
            INDEX idx_racc_date (period_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    hire_schema_add_index($db, 'rental_accruals', 'uq_racc_period', 'UNIQUE KEY uq_racc_period (contract_id, period_key)');

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_id INT NOT NULL,
            field_name VARCHAR(60) NOT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            user_id INT NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rh_contract (contract_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_hire_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pay_no VARCHAR(40) NOT NULL,
            contract_id INT NOT NULL,
            customer_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            account_id INT NULL DEFAULT NULL,
            payment_method VARCHAR(30) NOT NULL DEFAULT 'CASH',
            pay_date DATE NOT NULL,
            reference_no VARCHAR(80) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            created_by INT NULL DEFAULT NULL,
            ledger_ref VARCHAR(50) DEFAULT NULL,
            reversed TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rhp_contract (contract_id),
            INDEX idx_rhp_customer (customer_id),
            UNIQUE KEY uq_rhp_pay_no (pay_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    hire_schema_add_index($db, 'rental_hire_payments', 'uq_rhp_pay_no', 'UNIQUE KEY uq_rhp_pay_no (pay_no)');

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_stock_moves (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contract_id INT NOT NULL,
            product_id INT NOT NULL,
            qty DECIMAL(12,3) NOT NULL DEFAULT 0,
            kind VARCHAR(20) NOT NULL,
            serial_no VARCHAR(80) DEFAULT NULL,
            note VARCHAR(255) DEFAULT NULL,
            created_by INT NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rsm_contract (contract_id),
            INDEX idx_rsm_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS rental_settings (
            setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL DEFAULT '',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $defaults = [
        'billing_method' => 'calendar_month',
        'default_billing_day' => '1',
        'late_fee_type' => 'none',
        'late_fee_value' => '0',
        'accrue_on_page_load' => '1',
    ];
    foreach ($defaults as $k => $v) {
        $stmt = $db->prepare('INSERT IGNORE INTO rental_settings (setting_key, setting_value) VALUES (?, ?)');
        if ($stmt) {
            $stmt->bind_param('ss', $k, $v);
            $stmt->execute();
            $stmt->close();
        }
    }

    $prodChk = @$db->query("SHOW TABLES LIKE 'products'");
    if ($prodChk && $prodChk->num_rows > 0) {
        hire_schema_add_columns($db, 'products', [
            'rental_qty' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'damaged_qty' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
        ]);
    }

    $custChk = @$db->query("SHOW TABLES LIKE 'customers'");
    if ($custChk && $custChk->num_rows > 0) {
        hire_schema_add_columns($db, 'customers', [
            'rental_deposit_balance' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        ]);
    }

    mysqli_report($old);
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('hire_schema_v771', true);
    }
}
