<?php

/*
 * sales টেবিলে প্রয়োজনীয় সব কলাম আছে কিনা নিশ্চিত করে।
 * না থাকলে নিরাপদে ALTER TABLE দিয়ে যোগ করে দেয়।
 * (return_functions.php-এর ensure_return_schema()
 *  প্যাটার্ন অনুসরণ করে)
 */
function ame_sale_stock_required(): bool
{
    if (!empty($_SESSION['ame_sell_without_stock'])) {
        return false;
    }
    return empty($GLOBALS['ame_finish_draft_stock']);
}

function ensure_sales_schema($db)
{
    static $done = false;
    if ($done) {
        return;
    }

    $ver = defined('AME_APP_VERSION')
        ? preg_replace('/[^0-9.]/', '', (string) AME_APP_VERSION)
        : '0';
    $stamp = null;
    $stampDirs = [];
    if (defined('AME_PRIVATE_DATA_DIR')) {
        $stampDirs[] = AME_PRIVATE_DATA_DIR;
    }
    $stampDirs[] = sys_get_temp_dir();
    foreach ($stampDirs as $dir) {
        if ($dir !== '' && is_dir($dir) && is_writable($dir)) {
            $stamp = rtrim($dir, '/') . '/ame_sales_schema_' . $ver . '.ok';
            break;
        }
    }
    if ($stamp && is_file($stamp)) {
        if (!function_exists('ame_sale_load_exchange_items')) {
            require_once __DIR__ . '/sale_exchange.php';
        }
        $done = true;
        return;
    }

    $db->query(
        "CREATE TABLE IF NOT EXISTS sales (
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
            INDEX(status),
            INDEX(customer_id)
        )"
    );

    $required_columns = [
        'sale_date' => "DATETIME NULL",
        'created_at' => "DATETIME NULL",
        'vat_percent' => "DECIMAL(5,2) NOT NULL DEFAULT 0",
        'vat_amount' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'service_amount' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'rounding_amount' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'due_amount' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'draft_note' => "VARCHAR(255) NULL",
        'previous_customer_balance' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'due_adjustment_amount' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'customer_balance_after' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'change_amount' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'updated_at' => "DATETIME NULL",
        'completed_at' => "DATETIME NULL",
        'offline_client_ref' => "VARCHAR(80) NULL",
        'guest_customer_name' => "VARCHAR(150) NULL",
        'guest_customer_phone' => "VARCHAR(30) NULL",
        'guest_customer_note' => "VARCHAR(255) NULL",
        'store_id' => "INT NULL",
    ];

    $existing = [];
    $columns_result = $db->query("SHOW COLUMNS FROM sales");

    if ($columns_result) {
        while ($col = $columns_result->fetch_assoc()) {
            $existing[$col['Field']] = true;
        }
    }

    foreach ($required_columns as $name => $definition) {
        if (!isset($existing[$name])) {
            $db->query(
                "ALTER TABLE sales ADD COLUMN `$name` $definition"
            );
        }
    }

    /*
     * sale_items টেবিলে item_name কলাম যোগ করা (স্টকে নেই এমন
     * কাস্টম পণ্যের নাম রাখার জন্য - product_id NULL থাকবে তখন)
     */
    $item_cols = [];
    $item_columns_result = $db->query("SHOW COLUMNS FROM sale_items");
    if ($item_columns_result) {
        while ($col = $item_columns_result->fetch_assoc()) {
            $item_cols[$col['Field']] = true;
        }
    }
    if (!isset($item_cols['item_name'])) {
        $db->query(
            "ALTER TABLE sale_items ADD COLUMN item_name VARCHAR(255) NULL DEFAULT NULL"
        );
    }

    $product_id_col = @$db->query("SHOW COLUMNS FROM sale_items LIKE 'product_id'");
    if ($product_id_col && ($col = $product_id_col->fetch_assoc())) {
        if (strtoupper((string)($col['Null'] ?? 'YES')) === 'NO') {
            @$db->query(
                'ALTER TABLE sale_items MODIFY COLUMN product_id INT NULL DEFAULT NULL'
            );
        }
    }

    $idx = @$db->query("SHOW INDEX FROM sales WHERE Key_name = 'uniq_offline_client_ref'");
    if ($idx && $idx->num_rows === 0) {
        @$db->query("ALTER TABLE sales ADD UNIQUE KEY uniq_offline_client_ref (offline_client_ref)");
    }

    $draftIdx = @$db->query("SHOW INDEX FROM sales WHERE Key_name = 'idx_sales_status_updated'");
    if ($draftIdx && $draftIdx->num_rows === 0) {
        @$db->query('ALTER TABLE sales ADD INDEX idx_sales_status_updated (status, updated_at)');
    }

    $account_col = @$db->query("SHOW COLUMNS FROM sales LIKE 'account_id'");
    if ($account_col && ($col = $account_col->fetch_assoc())) {
        if (strtoupper((string)($col['Null'] ?? 'YES')) === 'NO') {
            @$db->query('ALTER TABLE sales MODIFY COLUMN account_id INT NULL DEFAULT NULL');
        }
    }

    $db->query(
        "CREATE TABLE IF NOT EXISTS sale_services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            engineer_id INT NULL,
            engineer_name VARCHAR(150) NULL DEFAULT NULL,
            service_type VARCHAR(255) NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            INDEX (sale_id),
            INDEX (engineer_id)
        )"
    );
    $ss_name = @$db->query("SHOW COLUMNS FROM sale_services LIKE 'engineer_name'");
    if ($ss_name && !$ss_name->fetch_assoc()) {
        @$db->query('ALTER TABLE sale_services ADD COLUMN engineer_name VARCHAR(150) NULL DEFAULT NULL');
    }

    $db->query(
        "CREATE TABLE IF NOT EXISTS sale_payments (
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
        )"
    );

    if (!function_exists('ensure_sale_exchange_schema')) {
        require_once __DIR__ . '/sale_exchange.php';
    }
    ensure_sale_exchange_schema($db);

    if (!function_exists('ame_sale_align_net_with_ledger')) {
        require_once __DIR__ . '/sale_invoice.php';
    }
    if (function_exists('ame_sale_align_net_with_ledger')) {
        ame_sale_align_net_with_ledger($db);
    }

    if ($stamp) {
        @file_put_contents($stamp, (string) time());
    }
    $done = true;
}

function ame_sale_fail($is_english, $bn, $en)
{
    throw new InvalidArgumentException(
        $is_english ? $en : $bn
    );
}

function ame_sale_item_is_custom($item)
{
    if (!empty($item['is_custom'])) {
        return true;
    }

    $product_id = $item['product_id'] ?? $item['id'] ?? null;

    return $product_id === null
        || $product_id === ''
        || (int)$product_id <= 0;
}

function ame_sale_write_item_row($db, $sale_id, $item, $decrement_stock = false)
{
    $quantity = (float)($item['quantity'] ?? $item['qty'] ?? 0);
    $purchase_price = (float)($item['purchase_price'] ?? 0);
    $sale_price = (float)($item['sale_price'] ?? 0);
    $name = trim((string)($item['name'] ?? $item['item_name'] ?? ''));
    $is_custom = ame_sale_item_is_custom($item);

    if ($is_custom) {
        $insert = $db->prepare(
            'INSERT INTO sale_items (
                sale_id,
                product_id,
                item_name,
                qty,
                purchase_price,
                sale_price
             ) VALUES (?, NULL, ?, ?, ?, ?)'
        );

        $insert->bind_param(
            'isddd',
            $sale_id,
            $name,
            $quantity,
            $purchase_price,
            $sale_price
        );

        $insert->execute();
        $insert->close();

        return;
    }

    $product_id = (int)$item['product_id'];

    $insert = $db->prepare(
        'INSERT INTO sale_items (
            sale_id,
            product_id,
            item_name,
            qty,
            purchase_price,
            sale_price
         ) VALUES (?, ?, ?, ?, ?, ?)'
    );

    $insert->bind_param(
        'iisddd',
        $sale_id,
        $product_id,
        $name,
        $quantity,
        $purchase_price,
        $sale_price
    );

    $insert->execute();
    $sale_item_id = (int)$db->insert_id;
    $insert->close();

    if (!$decrement_stock) {
        return;
    }

    $unit_id = (int)($item['unit_id'] ?? 0);
    $stock_qty = $unit_id > 0 && isset($item['base_qty']) ? (float)$item['base_qty'] : $quantity;
    if ($unit_id > 0 && is_file(__DIR__ . '/../unit_engine.php')) {
        require_once __DIR__ . '/../unit_engine.php';
        ame_unit_move($db, $product_id, ame_unit_num((string)(-1 * $stock_qty)), [
            'kind' => 'sale',
            'entered_qty' => $quantity,
            'unit_id' => $unit_id,
            'ref_type' => 'sale',
            'ref_id' => $sale_id,
            'note' => $name,
        ], ame_sale_stock_required());
        $mark = $db->prepare('UPDATE sale_items SET unit_id=?, base_qty=? WHERE id=?');
        $base_saved = ame_unit_num((string)$stock_qty);
        $mark->bind_param('idi', $unit_id, $base_saved, $sale_item_id);
        $mark->execute();
        $mark->close();
        return;
    }

    $store_id = function_exists('current_store_id') ? current_store_id() : 0;
    if ($store_id > 0 && function_exists('ame_store_stock_change')) {
        if (!ame_store_stock_change($db, $store_id, $product_id, -$quantity, ame_sale_stock_required())) {
            throw new RuntimeException(
                'পণ্যের স্টক পরিবর্তন করা যায়নি।'
            );
        }
        return;
    }

    $need_stock = ame_sale_stock_required();
    if ($need_stock) {
        $stock = $db->prepare(
            'UPDATE products
             SET stock = stock - ?
             WHERE id = ?
               AND stock >= ?'
        );
        $stock->bind_param(
            'did',
            $quantity,
            $product_id,
            $quantity
        );
    } else {
        $stock = $db->prepare(
            'UPDATE products
             SET stock = stock - ?
             WHERE id = ?'
        );
        $stock->bind_param(
            'di',
            $quantity,
            $product_id
        );
    }

    $stock->execute();

    if ($stock->affected_rows !== 1) {
        $stock->close();

        throw new RuntimeException(
            'পণ্যের স্টক পরিবর্তন করা যায়নি।'
        );
    }

    $stock->close();
}

function ame_sale_payments($db, $data, $is_english)
{
    $raw_payments = $data['payments'] ?? [];

    /* পুরোনো একক Payment-এর সামঞ্জস্য */
    if (empty($raw_payments)) {
        $legacy_account = (int)(
            $data['account_id'] ?? 0
        );

        $legacy_amount = round(
            (float)($data['paid'] ?? 0),
            2
        );

        if (
            $legacy_account > 0
            && $legacy_amount > 0
        ) {
            $raw_payments = [[
                'account_id' => $legacy_account,
                'amount' => $legacy_amount,
                'reference_number' => ''
            ]];
        }
    }

    if (!is_array($raw_payments)) {
        ame_sale_fail(
            $is_english,
            'পেমেন্টের তথ্য সঠিক নয়।',
            'Invalid payment information.'
        );
    }

    $payments = [];
    $used_accounts = [];

    foreach ($raw_payments as $raw_payment) {
        $account_id = (int)(
            $raw_payment['account_id'] ?? 0
        );

        $amount = round(
            (float)($raw_payment['amount'] ?? 0),
            2
        );

        if ($amount <= 0 || $account_id <= 0) {
            if ($amount > 0 && $account_id <= 0) {
                ame_sale_fail(
                    $is_english,
                    'প্রতিটি পরিশোধের অ্যাকাউন্ট নির্বাচন করুন।',
                    'Select an account for every payment.'
                );
            }
            continue;
        }

        if (isset($used_accounts[$account_id])) {
            ame_sale_fail(
                $is_english,
                'একই অ্যাকাউন্ট দুইবার ব্যবহার করা যাবে না।',
                'The same account cannot be used twice.'
            );
        }

        if (!function_exists('ame_selectable_account')) {
            $acctFile = dirname(__DIR__) . '/account_functions.php';
            if (is_file($acctFile)) {
                require_once $acctFile;
            }
        }
        $exists = function_exists('ame_selectable_account')
            && ame_selectable_account($db, $account_id) !== null;

        if (!$exists) {
            ame_sale_fail(
                $is_english,
                'নির্বাচিত পেমেন্ট অ্যাকাউন্ট সক্রিয় নয়।',
                'A selected payment account is inactive.'
            );
        }

        $used_accounts[$account_id] = true;

        $payments[] = [
            'account_id' => $account_id,
            'amount' => $amount,
            'reference_number' => substr(
                trim((string)(
                    $raw_payment['reference_number'] ?? ''
                )),
                0,
                100
            )
        ];
    }

    return [
        'items' => $payments,
        'total' => round(
            array_sum(array_column($payments, 'amount')),
            2
        ),
        'primary_account_id' => $payments
            ? (int)$payments[0]['account_id']
            : null
    ];
}

/**
 * সরবরাহকারী বা ক্রেতার আসল নেট ব্যালেন্স নির্ধারণ:
 * পজিটিভ (+) = ক্রেতার বকেয়া (দোকান পাবে)
 * নেগেটিভ (−) = সরবরাহকারী/পার্টির পাওনা (দোকানের দেনা)
 */
function ame_party_supplier_due(array $customer, $db = null): float
{
    $is_sup = !empty($customer['is_supplier']) || (int)($customer['linked_supplier_id'] ?? 0) > 0;
    $packed = round((float)($customer['supplier_due'] ?? 0), 2);
    if ($packed < 0.009 && $is_sup) {
        $packed = round((float)($customer['total_due'] ?? 0), 2);
    }
    if ($packed > 0.009) {
        return $packed;
    }
    if (!($db instanceof mysqli)) {
        return 0.0;
    }
    $linked = (int)($customer['linked_supplier_id'] ?? 0);
    $phone = trim((string)($customer['phone'] ?? ''));
    $name = trim((string)($customer['name'] ?? ''));
    $is_sup = !empty($customer['is_supplier']);
    $sup_due = 0.0;

    if ($linked > 0) {
        $s_stmt = @$db->prepare('SELECT total_due FROM suppliers WHERE id = ? LIMIT 1');
        if ($s_stmt) {
            $s_stmt->bind_param('i', $linked);
            $s_stmt->execute();
            $s_row = $s_stmt->get_result()->fetch_assoc();
            $s_stmt->close();
            if ($s_row) {
                $sup_due = (float)$s_row['total_due'];
            }
        }
    }
    if ($sup_due <= 0.009 && ($is_sup || $phone !== '' || $name !== '')) {
        $live = function_exists('ame_supplier_live_sql') ? (' AND ' . ame_supplier_live_sql($db)) : " AND status = 'active'";
        if ($phone !== '') {
            $s_stmt = @$db->prepare("SELECT total_due FROM suppliers WHERE phone = ?{$live} ORDER BY id DESC LIMIT 1");
            if ($s_stmt) {
                $s_stmt->bind_param('s', $phone);
                $s_stmt->execute();
                $s_row = $s_stmt->get_result()->fetch_assoc();
                $s_stmt->close();
                if ($s_row && (float)$s_row['total_due'] > 0.009) {
                    $sup_due = (float)$s_row['total_due'];
                }
            }
        }
        if ($sup_due <= 0.009 && $name !== '') {
            $s_stmt = @$db->prepare("SELECT total_due FROM suppliers WHERE name = ?{$live} ORDER BY id DESC LIMIT 1");
            if ($s_stmt) {
                $s_stmt->bind_param('s', $name);
                $s_stmt->execute();
                $s_row = $s_stmt->get_result()->fetch_assoc();
                $s_stmt->close();
                if ($s_row && (float)$s_row['total_due'] > 0.009) {
                    $sup_due = (float)$s_row['total_due'];
                }
            }
        }
    }
    return $sup_due > 0.009 ? round($sup_due, 2) : 0.0;
}

function ame_resolve_party_sale_balance(array $customer, $db = null): float
{
    $rec_bal = round((float)($customer['receivable_balance'] ?? 0), 2);
    $pay_bal = round((float)($customer['payable_balance'] ?? 0), 2);
    $net = round($rec_bal - max(0.0, $pay_bal), 2);
    $sup_due = 0.0;
    if ($pay_bal < 0.009) {
        $sup_due = ame_party_supplier_due($customer, $db);
    }
    if ($sup_due > 0.009) {
        $net = round($net - $sup_due, 2);
    }
    return $net;
}

function ame_supplier_customer_link_boot(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $col = @$db->query("SHOW COLUMNS FROM customers LIKE 'linked_supplier_id'");
    if ($col && $col->num_rows === 0) {
        @$db->query('ALTER TABLE customers ADD COLUMN linked_supplier_id INT NULL DEFAULT NULL');
    }
    $idx = @$db->query("SHOW INDEX FROM customers WHERE Key_name = 'idx_cust_linked_supplier'");
    if ($idx && $idx->num_rows === 0) {
        @$db->query('ALTER TABLE customers ADD INDEX idx_cust_linked_supplier (linked_supplier_id)');
    }
}

function ame_supplier_sale_customer_code(mysqli $db, string $wanted, int $excludeId = 0): string
{
    $wanted = substr(trim($wanted), 0, 30);
    if ($wanted !== '') {
        $chk = $db->prepare('SELECT id FROM customers WHERE customer_code = ? LIMIT 1');
        if ($chk) {
            $chk->bind_param('s', $wanted);
            $chk->execute();
            $hit = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$hit || (int)$hit['id'] === $excludeId) {
                return $wanted;
            }
        }
    }
    if (function_exists('generate_customer_code')) {
        return generate_customer_code($db);
    }
    return 'CUS-' . (string)random_int(100000, 999999);
}

/**
 * প্রজেক্টের লাইভ সাপ্লায়ারদের বিক্রয় পিকারে আনে (is_supplier=1)।
 * পুরনো ক্রয়-লেজার মুছে না; মুছে যাওয়া সাপ্লায়ার নতুন বিক্রয়ে আসে না।
 */
function ame_supplier_pos_fingerprint(mysqli $db, string $live): string
{
    $withParty = "SELECT COUNT(*) c, COALESCE(MAX(id),0) mid, ROUND(COALESCE(SUM(total_due),0),2) due,
        COALESCE(SUM(CRC32(CONCAT_WS('#', id, name, IFNULL(phone,''), IFNULL(address,''), IFNULL(party_code,''), ROUND(IFNULL(total_due,0),2)))),0) sig
        FROM suppliers WHERE {$live}";
    $plain = "SELECT COUNT(*) c, COALESCE(MAX(id),0) mid, ROUND(COALESCE(SUM(total_due),0),2) due,
        COALESCE(SUM(CRC32(CONCAT_WS('#', id, name, IFNULL(phone,''), IFNULL(address,''), ROUND(IFNULL(total_due,0),2)))),0) sig
        FROM suppliers WHERE {$live}";
    $fpRes = @$db->query($withParty);
    if (!$fpRes) {
        $fpRes = @$db->query($plain);
    }
    if (!$fpRes || !($fpRow = $fpRes->fetch_assoc())) {
        return '';
    }
    return $fpRow['c'] . '|' . $fpRow['mid'] . '|' . $fpRow['due'] . '|' . $fpRow['sig'];
}

function ame_supplier_pos_fingerprint_save(mysqli $db, string $fp): void
{
    if ($fp === '') {
        return;
    }
    $stmt = @$db->prepare(
        "INSERT INTO system_flags (flag_name, flag_value) VALUES ('sup_pos_fp', ?)
         ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)"
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $fp);
    $stmt->execute();
    $stmt->close();
}

function ame_sync_active_suppliers_to_customers($db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $checked = (int)($_SESSION['ame_sup_pos_ok'] ?? 0);
        if ($checked > 0 && (time() - $checked) < 120) {
            return;
        }
    }
    if (is_file(__DIR__ . '/../supplier_functions.php')) {
        require_once __DIR__ . '/../supplier_functions.php';
    }
    $stbl = @$db->query("SHOW TABLES LIKE 'suppliers'");
    if (!$stbl || $stbl->num_rows === 0) {
        return;
    }
    ame_supplier_customer_link_boot($db);

    $live = function_exists('ame_supplier_live_sql') ? ame_supplier_live_sql($db) : '1=1';
    $fp = ame_supplier_pos_fingerprint($db, $live);
    $stored = '';
    $flagRes = @$db->query("SELECT flag_value FROM system_flags WHERE flag_name = 'sup_pos_fp' LIMIT 1");
    if ($flagRes && ($flagRow = $flagRes->fetch_assoc())) {
        $stored = (string)$flagRow['flag_value'];
    }
    if ($fp !== '' && $stored === $fp) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ame_sup_pos_ok'] = time();
        }
        return;
    }

    $sres = @$db->query("SELECT id, name, phone, address, total_due, party_code FROM suppliers WHERE {$live}");
    if (!$sres) {
        $sres = @$db->query("SELECT id, name, phone, address, total_due FROM suppliers WHERE {$live}");
    }
    if (!$sres) {
        return;
    }
    $suppliers = [];
    while ($s = $sres->fetch_assoc()) {
        $suppliers[] = $s;
    }
    $sres->free();

    $byLink = [];
    $unlinked = [];
    $cres = @$db->query(
        'SELECT id, linked_supplier_id, phone, name, receivable_balance, payable_balance, is_supplier, customer_code
         FROM customers
         WHERE is_supplier = 1 OR (linked_supplier_id IS NOT NULL AND linked_supplier_id > 0)'
    );
    if ($cres) {
        while ($row = $cres->fetch_assoc()) {
            $link = (int)($row['linked_supplier_id'] ?? 0);
            if ($link > 0) {
                $byLink[$link] = $row;
            } elseif ((int)($row['is_supplier'] ?? 0) === 1) {
                $unlinked[] = $row;
            }
        }
        $cres->free();
    }

    $ustmt = $db->prepare('UPDATE customers SET is_supplier = 1, linked_supplier_id = ?, customer_code = ? WHERE id = ?');
    $istmt = $db->prepare('INSERT INTO customers (customer_code, name, phone, address, is_customer, is_supplier, receivable_balance, linked_supplier_id) VALUES (?, ?, ?, ?, 0, 1, ?, ?)');
    foreach ($suppliers as $s) {
        $sid = (int)($s['id'] ?? 0);
        $sname = trim((string)($s['name'] ?? ''));
        $sphone = trim((string)($s['phone'] ?? ''));
        $saddr = trim((string)($s['address'] ?? ''));
        $sdue = round((float)($s['total_due'] ?? 0), 2);
        $sparty = trim((string)($s['party_code'] ?? ''));
        if ($sid <= 0 || $sname === '') {
            continue;
        }

        $found = $byLink[$sid] ?? null;
        if (!$found && $sphone !== '') {
            foreach ($unlinked as $row) {
                if (trim((string)($row['phone'] ?? '')) === $sphone) {
                    $found = $row;
                    break;
                }
            }
        }
        if (!$found && $sname !== '') {
            foreach ($unlinked as $row) {
                if (trim((string)($row['name'] ?? '')) === $sname) {
                    $found = $row;
                    break;
                }
            }
        }

        if ($found) {
            $cid = (int)$found['id'];
            $code = trim((string)($found['customer_code'] ?? ''));
            if ($sparty !== '' && ($code === '' || str_starts_with($code, 'CUS-'))) {
                $code = ame_supplier_sale_customer_code($db, $sparty, $cid);
            }
            $changed = (int)($found['is_supplier'] ?? 0) !== 1
                || (int)($found['linked_supplier_id'] ?? 0) !== $sid
                || $code !== trim((string)($found['customer_code'] ?? ''));
            if ($changed && $ustmt) {
                $ustmt->bind_param('isi', $sid, $code, $cid);
                $ustmt->execute();
            }
        } elseif ($istmt) {
            $code = ame_supplier_sale_customer_code($db, $sparty);
            $opening_rec = $sdue > 0.009 ? -$sdue : 0.0;
            $istmt->bind_param('ssssdi', $code, $sname, $sphone, $saddr, $opening_rec, $sid);
            $istmt->execute();
        }
    }
    if ($ustmt) {
        $ustmt->close();
    }
    if ($istmt) {
        $istmt->close();
    }
    ame_supplier_pos_fingerprint_save($db, $fp);
}

/*
 * নির্বাচিত কাস্টমার বা সরবরাহকারীকে Row-Level Lock (FOR UPDATE) সহ নিয়ে আসে,
 * যাতে একই সাথে একাধিক বিক্রয় হলে balance-এ Race Condition না হয়।
 * কার্যকর ব্যালেন্স (effective_balance) স্বয়ংক্রিয়ভাবে প্লাস-মাইনাস সমাধান করা হয়।
 */
function ame_sale_customer($db, $customer_id, $is_english)
{
    if ($customer_id === null) {
        return null;
    }

    if ($db instanceof mysqli) {
        ame_supplier_customer_link_boot($db);
    }

    $customer = null;
    $stmt = @$db->prepare(
        'SELECT id, name, phone, customer_code, receivable_balance, payable_balance, is_supplier, is_customer, credit_limit, linked_supplier_id, status
         FROM customers
         WHERE id = ?
         FOR UPDATE'
    );
    if ($stmt) {
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $stmt2 = $db->prepare(
            'SELECT id, name, receivable_balance, credit_limit
             FROM customers
             WHERE id = ?
             FOR UPDATE'
        );
        if ($stmt2) {
            $stmt2->bind_param('i', $customer_id);
            $stmt2->execute();
            $customer = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        }
    }

    if (!$customer) {
        ame_sale_fail(
            $is_english,
            'নির্বাচিত ক্রেতা বা সরবরাহকারীকে পাওয়া যায়নি।',
            'The selected party was not found.'
        );
    }
    if (function_exists('ame_customer_row_operable') && !ame_customer_row_operable($customer)) {
        ame_sale_fail(
            $is_english,
            'বন্ধ কাস্টমারকে বিক্রয় করা যায় না। কাস্টমার তালিকা থেকে চালু করুন।',
            'Inactive customers cannot be sold to. Enable them from the customer list.'
        );
    }

    $ledgerFile = dirname(__DIR__, 2) . '/modules/customers/customer_ledger_functions.php';
    if (!function_exists('ame_ledger_net') && is_file($ledgerFile)) {
        require_once $ledgerFile;
    }
    $cid = (int)($customer['id'] ?? 0);
    if ($db instanceof mysqli && $cid > 0 && function_exists('ame_customer_sale_return_sync')) {
        ame_customer_sale_return_sync($db, $cid);
    }
    if ($db instanceof mysqli && $cid > 0 && function_exists('ame_ledger_net')) {
        $hasLedger = @$db->prepare('SELECT id FROM customer_ledger WHERE customer_id = ? LIMIT 1');
        if ($hasLedger) {
            $hasLedger->bind_param('i', $cid);
            $hasLedger->execute();
            $hasRow = $hasLedger->get_result()->fetch_assoc();
            $hasLedger->close();
            if ($hasRow) {
                if (function_exists('ame_ledger_recompute_running')) {
                    ame_ledger_recompute_running($db, $cid);
                }
                $customer['receivable_balance'] = ame_ledger_net($db, $cid);
            }
        }
    }

    $customer['effective_balance'] = ame_resolve_party_sale_balance($customer, $db);
    return $customer;
}

function ame_sale_guest_snapshot(array $data, $customer_id, $is_english)
{
    if ($customer_id !== null) {
        return [
            'name' => '',
            'phone' => '',
            'note' => '',
        ];
    }

    return [
        'name' => substr(
            trim((string)($data['guest_customer_name'] ?? '')),
            0,
            150
        ),
        'phone' => substr(
            trim((string)($data['guest_customer_phone'] ?? '')),
            0,
            30
        ),
        'note' => substr(
            trim((string)($data['guest_customer_note'] ?? '')),
            0,
            255
        ),
    ];
}

/** বিক্রয় সম্পন্নের পর অতিথি ক্রেতার নাম/ফোন মেমোতে ও ফেরত-রেজিস্টারে রাখা */
function ame_sale_apply_guest_snapshot(mysqli $db, int $saleId, array $data, ?int $customerId): void
{
    if ($saleId <= 0) {
        return;
    }
    ensure_sales_schema($db);
    $guest = ame_sale_guest_snapshot($data, $customerId, false);
  $stmt = $db->prepare(
        'UPDATE sales SET guest_customer_name = ?, guest_customer_phone = ?, guest_customer_note = ? WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param(
        'sssi',
        $guest['name'],
        $guest['phone'],
        $guest['note'],
        $saleId
    );
    $stmt->execute();
    $stmt->close();
}

/** ফেরত/এক্সচেঞ্জ UI-এর জন্য প্রদর্শন নাম (নিবন্ধিত + অতিথি) */
function ame_sale_display_customer_name(array $row, bool $english = false): string
{
    $name = trim((string)($row['registered_customer_name'] ?? $row['customer_name'] ?? ''));
    if ($name === '') {
        $name = trim((string)($row['guest_customer_name'] ?? ''));
    }
    if ($name === '') {
        return $english ? 'Retail Customer' : 'খুচরা ক্রেতা';
    }

    return $name;
}

function ame_sale_display_customer_phone(array $row): string
{
    $phone = trim((string)($row['registered_customer_phone'] ?? $row['customer_phone'] ?? ''));
    if ($phone === '') {
        $phone = trim((string)($row['guest_customer_phone'] ?? ''));
    }

    return $phone;
}

function ame_sale_row_return_registry_ready(array $row): bool
{
    if (!empty($row['customer_id'])) {
        return true;
    }

    return trim((string)($row['guest_customer_name'] ?? '')) !== '';
}