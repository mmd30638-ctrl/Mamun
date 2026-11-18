<?php
/**
 * Idempotent schema sync: keep existing tables/rows, create missing tables,
 * add missing columns. Never DROP / TRUNCATE.
 */

require_once __DIR__ . '/schema_catalog.php';

/**
 * একবারের স্কিমা কাজ। true দিলে স্ট্যাম্প লেখে।
 * আর্গুমেন্ট একটা হলে এই ভার্সনে আগেই হয়ে থাকলে true — কলার তখন কাজ বাদ দেয়।
 */
function ame_schema_stamp(string $key, bool $write = false): bool
{
    static $cache = null;
    $db = $GLOBALS['db'] ?? null;
    if (!($db instanceof mysqli)) {
        return false;
    }
    $key = strtolower((string)preg_replace('/[^a-z0-9_]+/', '', $key));
    if ($key === '') {
        return false;
    }
    $flag = 'sch_' . $key;
    $ver = defined('AME_APP_VERSION') ? (string)AME_APP_VERSION : '1';
    if (!is_array($cache)) {
        $cache = [];
        $res = @$db->query("SELECT flag_name, flag_value FROM system_flags WHERE flag_name LIKE 'sch\\_%'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cache[(string)$row['flag_name']] = (string)$row['flag_value'];
            }
            $res->free();
        }
    }
    if ($write) {
        if (($cache[$flag] ?? '') === $ver) {
            return true;
        }
        $stmt = @$db->prepare(
            'INSERT INTO system_flags (flag_name, flag_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)'
        );
        if ($stmt) {
            $stmt->bind_param('ss', $flag, $ver);
            $stmt->execute();
            $stmt->close();
            $cache[$flag] = $ver;
        }
        return true;
    }
    return ($cache[$flag] ?? '') === $ver;
}

function ame_schema_table_exists(mysqli $db, string $table): bool
{
    $safe = $db->real_escape_string($table);
    $r = @$db->query("SHOW TABLES LIKE '{$safe}'");
    return $r && $r->num_rows > 0;
}

function ame_schema_columns(mysqli $db, string $table): array
{
    $out = [];
    if (!ame_schema_table_exists($db, $table)) {
        return $out;
    }
    $res = @$db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[$row['Field']] = true;
        }
    }
    return $out;
}

function ame_schema_index_exists(mysqli $db, string $table, string $name): bool
{
    $safe = $db->real_escape_string($name);
    $tbl = str_replace('`', '', $table);
    $res = @$db->query("SHOW INDEX FROM `{$tbl}` WHERE Key_name = '{$safe}'");
    return $res && $res->num_rows > 0;
}

function ame_schema_status(mysqli $db): array
{
    $expected = ame_schema_expected_tables();
    $present = [];
    $missing = [];
    $res = @$db->query('SHOW TABLES');
    $have = [];
    if ($res) {
        while ($row = $res->fetch_row()) {
            $have[$row[0]] = true;
        }
    }
    foreach ($expected as $t) {
        if (isset($have[$t])) {
            $present[] = $t;
        } else {
            $missing[] = $t;
        }
    }
    return [
        'expected' => count($expected),
        'present' => $present,
        'missing' => $missing,
        'extra' => array_values(array_diff(array_keys($have), $expected)),
        'version' => defined('AME_APP_VERSION') ? AME_APP_VERSION : '',
    ];
}

function ame_schema_sync_run(mysqli $db): array
{
    $report = [
        'created_tables' => [],
        'kept_tables' => [],
        'added_columns' => [],
        'added_indexes' => [],
        'ensure_ok' => [],
        'ensure_fail' => [],
        'errors' => [],
    ];

    $old = mysqli_report(MYSQLI_REPORT_OFF);

    foreach (ame_schema_base_tables() as $table => $def) {
        $existed = ame_schema_table_exists($db, $table);
        $ok = @$db->query($def['sql']);
        if (!$ok) {
            $report['errors'][] = $table . ': ' . $db->error;
            continue;
        }
        if ($existed) {
            $report['kept_tables'][] = $table;
        } else {
            $report['created_tables'][] = $table;
        }
        if (!empty($def['indexes'])) {
            foreach ($def['indexes'] as $idx) {
                if (ame_schema_index_exists($db, $table, $idx['name'])) {
                    continue;
                }
                if (@$db->query('ALTER TABLE `' . $table . '` ADD ' . $idx['sql'])) {
                    $report['added_indexes'][] = $table . '.' . $idx['name'];
                }
            }
        }
    }

    foreach (ame_schema_extra_columns() as $table => $cols) {
        if (!ame_schema_table_exists($db, $table)) {
            continue;
        }
        $have = ame_schema_columns($db, $table);
        foreach ($cols as $col => $ddl) {
            if (isset($have[$col])) {
                continue;
            }
            $sql = 'ALTER TABLE `' . str_replace('`', '', $table) . '` ADD COLUMN `' . str_replace('`', '', $col) . '` ' . $ddl;
            if (@$db->query($sql)) {
                $report['added_columns'][] = $table . '.' . $col;
                $have[$col] = true;
            } else {
                $report['errors'][] = $table . '.' . $col . ': ' . $db->error;
            }
        }
    }

    // স্পিড — ঘন ঘন ব্যবহৃত ফিল্টার/সর্ট কলামে বাদ পড়া ইনডেক্স যোগ করা (ডেটা
    // স্পর্শ হয় না, শুধু লুকআপ দ্রুত হয়)
    if (function_exists('ame_schema_extra_indexes')) {
        foreach (ame_schema_extra_indexes() as $table => $idxList) {
            if (!ame_schema_table_exists($db, $table)) {
                continue;
            }
            $have = ame_schema_columns($db, $table);
            foreach ($idxList as $idx) {
                $name = (string)($idx['name'] ?? '');
                if ($name === '' || ame_schema_index_exists($db, $table, $name)) {
                    continue;
                }
                // ইনডেক্সের কলাম টেবিলে না থাকলে চেষ্টা করা হয় না (নীরবে এড়ানো)
                if (preg_match('/\(([^)]+)\)/', (string)($idx['sql'] ?? ''), $m)) {
                    $cols = array_map('trim', explode(',', $m[1]));
                    foreach ($cols as $c) {
                        if (!isset($have[$c])) {
                            continue 2;
                        }
                    }
                }
                $sql = 'ALTER TABLE `' . str_replace('`', '', $table) . '` ADD ' . $idx['sql'];
                if (@$db->query($sql)) {
                    $report['added_indexes'][] = $table . '.' . $name;
                } else {
                    $report['errors'][] = $table . '.' . $name . ': ' . $db->error;
                }
            }
        }
    }

    ame_schema_run_ensure_pass($db, $report);
    // ব্যর্থ/অসম্পূর্ণ sync-কে সফল version হিসেবে stamp করলে পরের request আর
    // missing table/column মেরামতের চেষ্টা করে না। সব ধাপ সফল হলেই stamp হবে।
    if (!$report['errors'] && !$report['ensure_fail']) {
        ame_schema_stamp_version($db);
    }

    mysqli_report($old);
    return $report;
}

function ame_schema_stamp_version(mysqli $db): void
{
    $ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '0';
    if (!ame_schema_table_exists($db, 'system_flags')) {
        @$db->query("CREATE TABLE IF NOT EXISTS system_flags (
            flag_name VARCHAR(50) NOT NULL PRIMARY KEY,
            flag_value VARCHAR(50) NOT NULL DEFAULT '1',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    $stmt = $db->prepare("INSERT INTO system_flags (flag_name, flag_value) VALUES ('schema_catalog_version', ?)
        ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value), updated_at = NOW()");
    if ($stmt) {
        $stmt->bind_param('s', $ver);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_schema_version_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    $ver = defined('AME_APP_VERSION') ? (string) AME_APP_VERSION : '';
    if ($ver === '') {
        return false;
    }
    try {
        $r = @$db->query("SELECT flag_value FROM system_flags WHERE flag_name = 'schema_catalog_version' LIMIT 1");
        if ($r && ($row = $r->fetch_assoc()) && (string) $row['flag_value'] === $ver) {
            $ready = true;
        }
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/**
 * প্রোডাকশন ক্লিন 10.98.534 — ভার্সন বদলের প্রথম রিকোয়েস্টে
 * ওয়েবে থাকা ইনস্টলার, ওয়াইপ স্ক্রিপ্ট ও অব্যবহৃত পুরোনো অ্যাসেট সরায়।
 * সহজ আপডেট, sql স্কিমা, SPEED.txt ও warm.php ছোঁয় না।
 */
function ame_release_cleanup_files(): void
{
    $root = dirname(__DIR__);
    $rootReal = realpath($root);
    if ($rootReal === false) {
        return;
    }
    $rels = [
        'install_wipe_data.php',
        'import.php',
        'check-db.php',
        'bump_asset_version.php',
        'cleanup_unused_files.php',
        'modules/settings/download_wipe_sql.php',
        'sql/wipe_all_business_data.sql',
        'assets/CSS/pos-terminal.css',
        'assets/css/directory.808.css',
        'assets/css/directory.809.css',
        'assets/css/family-desks.812.css',
        'assets/css/family-os.814.css',
        'assets/css/family-os.815.css',
        'assets/css/family-os.816.css',
        'assets/css/hire-yard.807.css',
        'assets/css/inventory-first-home.700.css',
        'assets/css/kitab-house.813.css',
        'assets/css/kitab-reader.812.css',
        'assets/css/loan-desk.805.css',
        'assets/css/saas-os.800.css',
        'assets/css/saas-os.801.css',
        'assets/css/saas-os.803.css',
        'assets/css/saas-os.804.css',
        'assets/css/settings-display.802.css',
        'assets/css/settings-display.804.css',
        'assets/css/shell-in.818.css',
        'assets/css/ticker-voice.819.css',
        'assets/css/ticker-voice.820.css',
        'assets/css/ticker-voice.821.css',
        'assets/css/ticker-voice.822.css',
        'assets/css/ticker-voice.823.css',
        'assets/css/ticker-voice.824.css',
        'assets/css/ticker-voice.825.css',
        'assets/css/ticker-voice.826.css',
        'assets/css/trust-vault.807.css',
        'assets/js/brand-type.804.js',
        'assets/js/inventory-first-home.700.js',
        'assets/js/kitab-reader.812.js',
        'assets/js/kitab-reader.815.js',
        'assets/js/kitab-reader.817.js',
        'assets/js/kitab-scan.813.js',
        'assets/js/kitab-studio.815.js',
        'assets/js/premium-saas.700.js',
        'assets/js/saas-os.800.js',
        'assets/js/saas-os.801.js',
        'assets/js/saas-os.803.js',
        'assets/js/saas-os.804.js',
        'assets/js/shell-in.818.js',
        'assets/js/ticker-voice.819.js',
        'assets/js/ticker-voice.820.js',
        'assets/js/ticker-voice.821.js',
        'assets/js/ticker-voice.822.js',
        'assets/js/ticker-voice.823.js',
        'assets/js/ticker-voice.824.js',
        'assets/js/ticker-voice.825.js',
        'assets/js/ticker-voice.826.js',
    ];
    $prefix = $rootReal . DIRECTORY_SEPARATOR;
    foreach ($rels as $rel) {
        $path = $root . '/' . $rel;
        if (!is_file($path)) {
            continue;
        }
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, $prefix)) {
            continue;
        }
        @unlink($real);
    }
}

function ame_schema_sync_if_needed(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '0';
    if (ame_schema_table_exists($db, 'system_flags')) {
        $safe = $db->real_escape_string($ver);
        $r = @$db->query("SELECT flag_value FROM system_flags WHERE flag_name = 'schema_catalog_version' LIMIT 1");
        if ($r && ($row = $r->fetch_assoc()) && (string)$row['flag_value'] === $ver) {
            return;
        }
    }
    ame_release_cleanup_files();
    if (is_file(__DIR__ . '/public_host_files.php')) {
        require_once __DIR__ . '/public_host_files.php';
        if (function_exists('ame_ensure_public_host_files')) {
            ame_ensure_public_host_files();
        }
    }
    try {
        ame_schema_sync_run($db);
    } catch (Throwable $e) {
        error_log('schema_sync_if_needed: ' . $e->getMessage());
    }
    if (!function_exists('ame_ledger_books_sealed') && is_file(__DIR__ . '/ledger_lock.php')) {
        require_once __DIR__ . '/ledger_lock.php';
    }
    $sealed = function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed();
    if (!$sealed && is_file(__DIR__ . '/pre_domain_amount_restore.php')) {
        require_once __DIR__ . '/pre_domain_amount_restore.php';
        if (function_exists('ame_restore_pre_domain_party_amounts')) {
            try {
                ame_restore_pre_domain_party_amounts($db);
            } catch (Throwable $e) {
                error_log('pre-domain amount restore: ' . $e->getMessage());
            }
        }
        if (function_exists('ame_pre_domain_keep_later_payments')) {
            try {
                ame_pre_domain_keep_later_payments($db);
            } catch (Throwable $e) {
                error_log('pre-domain keep payments: ' . $e->getMessage());
            }
        }
    }
    if (is_file(__DIR__ . '/supplier_settlement.php')) {
        require_once __DIR__ . '/supplier_settlement.php';
        if (function_exists('ame_spay_release_held_payments')) {
            try {
                ame_spay_release_held_payments($db);
            } catch (Throwable $e) {
                error_log('supplier pending pay release: ' . $e->getMessage());
            }
        }
    }
}

function ame_schema_run_ensure_pass(mysqli $db, array &$report): void
{
    $files = [
        __DIR__ . '/user_functions.php',
        __DIR__ . '/module_guard.php',
        __DIR__ . '/product_studio.php',
        __DIR__ . '/inventory_functions.php',
        __DIR__ . '/account_functions.php',
        __DIR__ . '/hrm_functions.php',
        __DIR__ . '/earnings_functions.php',
        __DIR__ . '/sales/sale_common.php',
        __DIR__ . '/purchase_functions.php',
        __DIR__ . '/supplier_settlement.php',
        __DIR__ . '/purchase_return_functions.php',
        __DIR__ . '/return_functions.php',
        __DIR__ . '/return_v2_functions.php',
        __DIR__ . '/loan_schema.php',
        __DIR__ . '/loan_functions.php',
        __DIR__ . '/partnership_functions.php',
        __DIR__ . '/partner_accounting.php',
        __DIR__ . '/partner_accounting_v2.php',
        __DIR__ . '/family_schema.php',
        __DIR__ . '/family_library.php',
        __DIR__ . '/rental_schema.php',
        __DIR__ . '/hire_schema.php',
        __DIR__ . '/invoice_functions.php',
        __DIR__ . '/integrity_functions.php',
        __DIR__ . '/sms_functions.php',
        __DIR__ . '/whatsapp_functions.php',
        __DIR__ . '/sms_log_functions.php',
        __DIR__ . '/attendance_functions.php',
        __DIR__ . '/leave_functions.php',
        __DIR__ . '/display_widgets.php',
        __DIR__ . '/password_reset_otp.php',
        __DIR__ . '/critical_action_otp.php',
        __DIR__ . '/pf_functions.php',
        __DIR__ . '/store_functions.php',
        __DIR__ . '/workshop_functions.php',
        dirname(__DIR__) . '/modules/customers/customer_schema.php',
    ];
    foreach ($files as $file) {
        if (is_file($file)) {
            require_once $file;
        }
    }

    $calls = [
        'ensure_user_schema',
        'ensure_permission_schema',
        'ame_module_control_ensure',
        'ensure_theme_schema',
        'ensure_vd_schema',
        'ensure_display_widgets_schema',
        'ensure_customer_tables',
        'ensure_sales_schema',
        'ensure_purchase_schema',
        'ame_spay_ensure',
        'ensure_purchase_return_schema',
        'ensure_return_schema',
        'ensure_return_v2_schema',
        'ensure_product_studio_schema',
        'ensure_inventory_schema',
        'ensure_accounts_schema',
        'ensure_hrm_schema',
        'ensure_earnings_schema',
        'ensure_workshop_schema',
        'ensure_store_schema',
        'ensure_rental_schema',
        'ensure_hire_schema',
        'ensure_loan_tables',
        'ensure_bank_loan_schema',
        'ensure_partnership_schema',
        'ensure_profit_allocation_schema',
        'ensure_capital_schema',
        'ensure_partner_accounting_schema',
        'pav2_ensure_schema',
        'ensure_family_schema',
        'ensure_invoice_settings_schema',
        'ensure_invoice_designer_schema',
        'ensure_integrity_schema',
        'ensure_whatsapp_schema',
        'ensure_sms_log_schema',
        'ensure_sms_sim_schema',
        'ensure_attendance_schema',
        'ensure_leave_schema',
        'ensure_password_reset_schema',
        'ensure_critical_action_otp_schema',
        'ensure_pf_schema',
        'ensure_provenance_schema',
    ];

    foreach ($calls as $fn) {
        if (!function_exists($fn)) {
            $report['ensure_fail'][] = $fn . ' (missing)';
            continue;
        }
        try {
            $result = $fn($db);
            if ($result === false) {
                $report['ensure_fail'][] = $fn . ' (returned false)';
            } else {
                $report['ensure_ok'][] = $fn;
            }
        } catch (Throwable $e) {
            $report['ensure_fail'][] = $fn . ': ' . $e->getMessage();
        }
    }
}
