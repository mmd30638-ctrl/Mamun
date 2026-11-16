<?php
/**
 * APONSHOP Performance Indexes — গরম কলামে idempotent ইনডেক্স।
 * ============================================================
 * - SHOW INDEX দিয়ে আগে দেখে, না থাকলে CREATE INDEX।
 * - ব্যবসায়িক ডাটা ছোঁয় না, শুধু ইনডেক্স যোগ করে (নিরাপদ)।
 * - maintenance.php / schema_sync থেকে চালানো হয়।
 * স্পিড লক SPEED.txt §৪ — এই তালিকা মুছবেন না; নতুন সার্চ কলাম এখানে যোগ করুন।
 */

if (!function_exists('ame_perf_index_list')) {
    function ame_perf_index_list(): array
    {
        // [table, index_name, columns]
        return [
            ['sales', 'idx_sales_date', 'sale_date'],
            ['sales', 'idx_sales_status', 'status'],
            ['sales', 'idx_sales_customer', 'customer_id'],
            ['sales', 'idx_sales_store', 'store_id'],
            ['sales', 'idx_sales_invoice', 'invoice_number'],
            ['sale_items', 'idx_si_sale', 'sale_id'],
            ['sale_items', 'idx_si_product', 'product_id'],
            ['sale_returns', 'idx_sr_sale', 'sale_id'],
            ['sale_returns', 'idx_sr_date', 'return_date'],
            ['purchases', 'idx_pur_memo', 'memo_no'],
            ['purchases', 'idx_pur_supplier', 'supplier_id'],
            ['purchase_expenses', 'idx_pex_memo', 'memo_no'],
            ['customers', 'idx_cus_phone', 'phone'],
            ['customers', 'idx_cus_name', 'name'],
            ['customers', 'idx_cus_supplier', 'is_supplier'],
            ['customers', 'idx_cus_code', 'cus_id_code'],
            ['customers', 'idx_cus_customer_code', 'customer_code'],
            ['suppliers', 'idx_sup_phone', 'phone'],
            ['suppliers', 'idx_sup_name', 'name'],
            ['products', 'idx_pro_name', 'name'],
            ['products', 'idx_pro_sku', 'sku'],
            ['products', 'idx_pro_status', 'status'],
            ['products', 'idx_pro_barcode', 'barcode'],
            ['expenses', 'idx_exp_date', 'expense_date'],
            ['accounts', 'idx_acc_status', 'status'],
            ['customer_ledger', 'idx_cl_customer', 'customer_id'],
            ['worker_earnings', 'idx_we_sale', 'sale_id'],
            ['worker_earnings', 'idx_we_employee', 'employee_id'],
            ['workshop_jobs', 'idx_wj_customer', 'customer_id'],
            ['workshop_jobs', 'idx_wj_engineer', 'engineer_id'],
            ['customers', 'idx_cus_due_date', 'next_due_date'],
            ['customers', 'idx_cus_due_bal', 'receivable_balance'],
            ['sales', 'idx_sales_customer_date', 'customer_id, sale_date'],
            ['customer_payments', 'idx_cp_customer_date', 'customer_id, payment_date'],
            ['customers', 'idx_cus_due_watch', 'due_watch'],
            ['customers', 'idx_cus_due_watch_on', 'due_watch_on'],
            ['customer_payments', 'idx_cp_customer', 'customer_id'],
            ['customer_payments', 'idx_cp_pay_date', 'payment_date'],
            ['products', 'idx_pro_min_stock', 'min_stock'],
        ];
    }
}

if (!function_exists('ame_perf_table_columns')) {
    function ame_perf_table_columns(mysqli $db, string $table): array
    {
        $out = [];
        $res = @$db->query('SHOW COLUMNS FROM `' . $db->real_escape_string($table) . '`');
        if (!$res) {
            return $out;
        }
        while ($row = $res->fetch_assoc()) {
            $out[] = (string)($row['Field'] ?? '');
        }
        $res->close();
        return $out;
    }
}

if (!function_exists('ame_perf_ensure_indexes')) {
    /**
     * @return array{checked:int, created:int, skipped:int, errors:array}
     */
    function ame_perf_ensure_indexes(mysqli $db): array
    {
        $report = ['checked' => 0, 'created' => 0, 'skipped' => 0, 'errors' => []];
        @$db->query('SET SESSION lock_wait_timeout = 8');
        $colCache = [];
        foreach (ame_perf_index_list() as [$table, $index, $column]) {
            $report['checked']++;
            if (!isset($colCache[$table])) {
                $colCache[$table] = ame_perf_table_columns($db, $table);
            }
            $cols = $colCache[$table];
            $parts = array_values(array_filter(array_map('trim', explode(',', (string)$column)), static fn($c) => $c !== ''));
            if ($cols === [] || $parts === []) {
                $report['skipped']++;
                continue;
            }
            $missingCol = false;
            foreach ($parts as $part) {
                if (!in_array($part, $cols, true)) {
                    $missingCol = true;
                    break;
                }
            }
            if ($missingCol) {
                $report['skipped']++;
                continue;
            }
            $have = false;
            $res = @$db->query('SHOW INDEX FROM `' . $db->real_escape_string($table) . "` WHERE Key_name = '" . $db->real_escape_string($index) . "'");
            if ($res) {
                $have = $res->num_rows > 0;
                $res->close();
            }
            if ($have) {
                continue;
            }
            $colSql = '`' . implode('`, `', array_map(static fn($c) => str_replace('`', '', $c), $parts)) . '`';
            $sql = 'CREATE INDEX `' . $db->real_escape_string($index) . '` ON `' . $db->real_escape_string($table) . '` (' . $colSql . ')';
            if (@$db->query($sql) === true) {
                $report['created']++;
            } else {
                $report['skipped']++;
                $report['errors'][] = $table . '.' . $index . ': ' . $db->error;
            }
        }
        return $report;
    }
}

if (!function_exists('ame_perf_boot_indexes')) {
    function ame_perf_boot_indexes(mysqli $db): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $ver = defined('AME_APP_VERSION') ? (string)AME_APP_VERSION : '1';
        $flag = @$db->query("SELECT flag_value FROM system_flags WHERE flag_name = 'perf_idx_boot' LIMIT 1");
        if ($flag && ($row = $flag->fetch_assoc()) && (string)$row['flag_value'] === $ver) {
            $flag->free();
            return;
        }
        if ($flag) {
            $flag->free();
        }
        ame_perf_ensure_indexes($db);
        $stmt = @$db->prepare(
            "INSERT INTO system_flags (flag_name, flag_value) VALUES ('perf_idx_boot', ?)
             ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)"
        );
        if ($stmt) {
            $stmt->bind_param('s', $ver);
            $stmt->execute();
            $stmt->close();
        }
    }
}
