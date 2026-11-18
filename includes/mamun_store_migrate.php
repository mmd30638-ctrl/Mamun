<?php
/**
 * মামু স্টোর → Aponshop ডাটা মাইগ্রেশন
 */

function mamun_store_tables(): array
{
    return [
        'categories',
        'customers',
        'ledger',
        'products',
        'purchases',
        'purchase_items',
        'sales',
        'sale_items',
        'settings',
        'suppliers',
        'users',
    ];
}

function mamun_store_is_dump(string $sql): bool
{
    $sql = strtolower($sql);
    if (strpos($sql, 'name_bn') === false && strpos($sql, '`categories`') === false) {
        return false;
    }
    if (strpos($sql, 'setting_key') === false && strpos($sql, '`settings`') === false) {
        return false;
    }
    return strpos($sql, 'create table') !== false;
}

function mamun_store_prepare_sql(string $sql): string
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*![\s\S]*?\*\//', '', $sql);
    $sql = preg_replace('/-- Indexes for dumped tables[\s\S]*?COMMIT;/i', 'COMMIT;', $sql);
    $sql = preg_replace('/ALTER TABLE\s+`[^`]+`[\s\S]*?;/i', '', $sql);
    $sql = preg_replace('/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN KEY\s*\([^)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^)]+\)\s*(ON DELETE\s+\w+)?\s*(ON UPDATE\s+\w+)?/i', '', $sql);

    foreach (mamun_store_tables() as $table) {
        $sql = str_replace("CREATE TABLE `$table`", "CREATE TABLE IF NOT EXISTS `ms_$table`", $sql);
        $sql = str_replace("INSERT INTO `$table`", "INSERT INTO `ms_$table`", $sql);
    }

    return $sql;
}

function mamun_store_drop_staging(mysqli $db): void
{
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (mamun_store_tables() as $table) {
        $db->query("DROP TABLE IF EXISTS `ms_$table`");
    }
    $db->query('SET FOREIGN_KEY_CHECKS=1');
}

function mamun_store_load_staging(mysqli $db, string $sql): void
{
    if (!mamun_store_is_dump($sql)) {
        throw new InvalidArgumentException('এটি মামু স্টোরের বৈধ SQL ডাম্প বলে মনে হচ্ছে না। phpMyAdmin থেকে Export → SQL ফরম্যাটে ফাইলটি আবার ডাউনলোড করুন।');
    }

    mamun_store_drop_staging($db);
    $prepared = mamun_store_prepare_sql($sql);

    $db->query('SET FOREIGN_KEY_CHECKS=0');
    $db->query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');

    if (!$db->multi_query($prepared)) {
        $err = $db->error;
        mamun_store_drop_staging($db);
        throw new RuntimeException('স্টেজিং টেবিল লোডে সমস্যা: ' . $err);
    }

    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
    } while ($db->more_results() && $db->next_result());

    if ($db->errno) {
        $err = $db->error;
        mamun_store_drop_staging($db);
        throw new RuntimeException('SQL চালানোর সময় সমস্যা: ' . $err);
    }

    $db->query('SET FOREIGN_KEY_CHECKS=1');
}

function mamun_store_count(mysqli $db, string $table): int
{
    $res = $db->query("SELECT COUNT(*) AS c FROM `ms_$table`");
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

function mamun_store_ensure_schemas(mysqli $db): void
{
    if (function_exists('ensure_customer_tables')) {
        require_once __DIR__ . '/../modules/customers/customer_schema.php';
        ensure_customer_tables($db);
    }
    if (function_exists('ensure_sales_schema')) {
        require_once __DIR__ . '/sales/sale_common.php';
        ensure_sales_schema($db);
    }
    if (function_exists('ensure_purchase_schema')) {
        require_once __DIR__ . '/purchase_functions.php';
        ensure_purchase_schema($db);
    }
    if (function_exists('ensure_user_schema')) {
        require_once __DIR__ . '/user_functions.php';
        ensure_user_schema($db);
    }
    if (function_exists('ensure_theme_schema')) {
        require_once __DIR__ . '/theme_functions.php';
        ensure_theme_schema($db);
    }
    if (function_exists('ensure_inventory_schema')) {
        require_once __DIR__ . '/inventory_functions.php';
        ensure_inventory_schema($db);
    }
}

function mamun_store_default_account_id(mysqli $db): int
{
    $res = $db->query("SELECT id FROM accounts WHERE status='active' ORDER BY id ASC LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        return (int)$row['id'];
    }
    return 0;
}

function mamun_store_unit_id(mysqli $db, string $unit): int
{
    $unit = trim($unit);
    if ($unit === '') {
        $unit = 'pcs';
    }
    $chk = $db->prepare('SELECT id FROM product_units WHERE unit_name = ? LIMIT 1');
    if ($chk) {
        $chk->bind_param('s', $unit);
        $chk->execute();
        $row = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($row) {
            return (int)$row['id'];
        }
    }
    $ins = $db->prepare('INSERT INTO product_units (unit_name) VALUES (?)');
    if (!$ins) {
        return 0;
    }
    $ins->bind_param('s', $unit);
    $ins->execute();
    $id = (int)$ins->insert_id;
    $ins->close();
    return $id;
}

function mamun_store_existing_skus(mysqli $db): array
{
    $out = [];
    $res = $db->query("SELECT sku FROM products WHERE sku IS NOT NULL AND sku != ''");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[strtolower((string)$row['sku'])] = true;
        }
    }
    return $out;
}

function mamun_store_existing_invoices(mysqli $db): array
{
    $out = [];
    $res = $db->query("SELECT invoice_number FROM sales WHERE invoice_number IS NOT NULL AND invoice_number != ''");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[strtolower((string)$row['invoice_number'])] = true;
        }
    }
    return $out;
}

function mamun_store_map_role(string $role): string
{
    $role = strtolower(trim($role));
    if ($role === 'admin') {
        return 'admin';
    }
    if ($role === 'manager') {
        return 'manager';
    }
    return 'employee';
}

function mamun_store_unique_username(mysqli $db, string $base, int $old_id): string
{
    $base = preg_replace('/[^a-zA-Z0-9._-]/', '', $base);
    if ($base === '') {
        $base = 'msuser' . $old_id;
    }
    $candidate = strtolower(substr($base, 0, 40));
    $try = $candidate;
    $n = 1;
    while (true) {
        $st = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        if (!$st) {
            return $candidate;
        }
        $st->bind_param('s', $try);
        $st->execute();
        $exists = $st->get_result()->num_rows > 0;
        $st->close();
        if (!$exists) {
            return $try;
        }
        $n++;
        $try = substr($candidate, 0, 35) . $n;
    }
}

function mamun_store_import_settings(mysqli $db, array &$stats): void
{
    $res = $db->query('SELECT setting_key, setting_value FROM ms_settings');
    if (!$res) {
        return;
    }

    $kv = [];
    while ($row = $res->fetch_assoc()) {
        $kv[$row['setting_key']] = $row['setting_value'];
    }

    if (isset($kv['company_name']) && trim((string)$kv['company_name']) !== '') {
        $name = trim((string)$kv['company_name']);
        $st = $db->prepare('UPDATE settings SET shop_name = ? WHERE id = 1');
        if ($st) {
            $st->bind_param('s', $name);
            if ($st->execute() && $st->affected_rows === 0) {
                $ins = $db->prepare('INSERT INTO settings (shop_name) VALUES (?)');
                if ($ins) {
                    $ins->bind_param('s', $name);
                    $ins->execute();
                    $ins->close();
                }
            }
            $st->close();
            $stats['settings']['shop_name'] = 1;
        }
    }

    $theme = [];
    if (!empty($kv['theme_bg'])) {
        $theme['bg_color'] = $kv['theme_bg'];
    }
    if (!empty($kv['theme_primary'])) {
        $theme['secondary_color'] = $kv['theme_primary'];
        $theme['accent_color'] = $kv['theme_primary'];
    }
    if (!empty($kv['theme_secondary'])) {
        $theme['text_color'] = $kv['theme_secondary'];
    }
    if ($theme && function_exists('ame_save_theme')) {
        $current = function_exists('ame_load_theme') ? ame_load_theme($db) : [];
        ame_save_theme($db, array_merge($current, $theme));
        $stats['settings']['theme'] = 1;
    }
}

function mamun_store_import_categories(mysqli $db, array &$stats, bool $skip_existing): array
{
    $map = [];
    $res = $db->query('SELECT id, name_bn, name_en FROM ms_categories ORDER BY id ASC');
    if (!$res) {
        return $map;
    }

    $ins = $db->prepare('INSERT INTO categories (name) VALUES (?)');
    while ($row = $res->fetch_assoc()) {
        $name = trim((string)($row['name_bn'] ?: $row['name_en']));
        if ($name === '') {
            continue;
        }
        if ($skip_existing) {
            $chk = $db->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
            if ($chk) {
                $chk->bind_param('s', $name);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $map[(int)$row['id']] = (int)$found['id'];
                    $stats['categories']['skipped']++;
                    continue;
                }
            }
        }
        if ($ins) {
            $ins->bind_param('s', $name);
            $ins->execute();
            $map[(int)$row['id']] = (int)$ins->insert_id;
            $stats['categories']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
    return $map;
}

function mamun_store_import_products(mysqli $db, array $cat_map, array &$stats, bool $skip_existing): array
{
    $map = [];
    $existing_skus = $skip_existing ? mamun_store_existing_skus($db) : [];

    $res = $db->query(
        'SELECT id, category_id, name, sku, image, purchase_price, wholesale_price, retail_price,
                stock_qty, unit, low_stock_alert, status
         FROM ms_products ORDER BY id ASC'
    );
    if (!$res) {
        return $map;
    }

    $ins = $db->prepare(
        'INSERT INTO products (
            sku, name, category_id, unit_id, purchase_price, wholesale_price, dealer_price,
            sale_price, stock, min_stock, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    while ($row = $res->fetch_assoc()) {
        $old_id = (int)$row['id'];
        $sku = trim((string)($row['sku'] ?? ''));
        if ($sku === '') {
            $sku = 'MS-P' . str_pad((string)$old_id, 5, '0', STR_PAD_LEFT);
        }
        if ($skip_existing && isset($existing_skus[strtolower($sku)])) {
            $chk = $db->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
            if ($chk) {
                $chk->bind_param('s', $sku);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $map[$old_id] = (int)$found['id'];
                    $stats['products']['skipped']++;
                    continue;
                }
            }
        }

        $cat_id = 0;
        $ms_cat = (int)($row['category_id'] ?? 0);
        if ($ms_cat > 0 && isset($cat_map[$ms_cat])) {
            $cat_id = $cat_map[$ms_cat];
        }
        $unit_id = mamun_store_unit_id($db, (string)($row['unit'] ?? 'pcs'));
        $purchase = (float)$row['purchase_price'];
        $wholesale = (float)$row['wholesale_price'];
        $retail = (float)$row['retail_price'];
        $stock = (float)$row['stock_qty'];
        $min_stock = (float)($row['low_stock_alert'] ?? 5);
        $status = strtolower((string)($row['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';
        $name = trim((string)$row['name']);
        $cat_bind = $cat_id > 0 ? $cat_id : 0;
        $unit_bind = $unit_id > 0 ? $unit_id : 0;

        if ($ins) {
            $ins->bind_param(
                'ssiidddddds',
                $sku,
                $name,
                $cat_bind,
                $unit_bind,
                $purchase,
                $wholesale,
                $wholesale,
                $retail,
                $stock,
                $min_stock,
                $status
            );
            $ins->execute();
            $map[$old_id] = (int)$ins->insert_id;
            $existing_skus[strtolower($sku)] = true;
            $stats['products']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
    return $map;
}

function mamun_store_import_customers(mysqli $db, array &$stats, bool $skip_existing): array
{
    $map = [];
    $res = $db->query(
        'SELECT id, name, phone, address, photo, customer_type, due_balance
         FROM ms_customers ORDER BY id ASC'
    );
    if (!$res) {
        return $map;
    }

    $ins = $db->prepare(
        'INSERT INTO customers (
            customer_code, name, phone, address, photo, receivable_balance, status
        ) VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    while ($row = $res->fetch_assoc()) {
        $old_id = (int)$row['id'];
        $code = 'MS-CUS-' . str_pad((string)$old_id, 5, '0', STR_PAD_LEFT);
        if ($skip_existing) {
            $chk = $db->prepare('SELECT id FROM customers WHERE customer_code = ? LIMIT 1');
            if ($chk) {
                $chk->bind_param('s', $code);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $map[$old_id] = (int)$found['id'];
                    $stats['customers']['skipped']++;
                    continue;
                }
            }
        }
        $name = trim((string)$row['name']);
        $phone = trim((string)($row['phone'] ?? ''));
        $address = trim((string)($row['address'] ?? ''));
        $photo = trim((string)($row['photo'] ?? ''));
        $due = (float)($row['due_balance'] ?? 0);
        if ($ins) {
            $ins->bind_param('sssssd', $code, $name, $phone, $address, $photo, $due);
            $ins->execute();
            $map[$old_id] = (int)$ins->insert_id;
            $stats['customers']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
    return $map;
}

function mamun_store_import_suppliers(mysqli $db, array &$stats, bool $skip_existing): array
{
    $map = [];
    $res = $db->query(
        'SELECT id, name, phone, address, due_balance FROM ms_suppliers ORDER BY id ASC'
    );
    if (!$res) {
        return $map;
    }

    $ins = $db->prepare(
        'INSERT INTO suppliers (name, phone, address, total_due, status) VALUES (?, ?, ?, ?, ?)'
    );

    while ($row = $res->fetch_assoc()) {
        $old_id = (int)$row['id'];
        $name = trim((string)$row['name']);
        if ($skip_existing && $name !== '') {
            $chk = $db->prepare('SELECT id FROM suppliers WHERE name = ? LIMIT 1');
            if ($chk) {
                $chk->bind_param('s', $name);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $map[$old_id] = (int)$found['id'];
                    $stats['suppliers']['skipped']++;
                    continue;
                }
            }
        }
        $phone = trim((string)($row['phone'] ?? ''));
        $address = trim((string)($row['address'] ?? ''));
        $due = (float)($row['due_balance'] ?? 0);
        $status = 'active';
        if ($ins) {
            $ins->bind_param('sssds', $name, $phone, $address, $due, $status);
            $ins->execute();
            $map[$old_id] = (int)$ins->insert_id;
            $stats['suppliers']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
    return $map;
}

function mamun_store_import_users(mysqli $db, array &$stats, bool $skip_existing): array
{
    $map = [];
    $res = $db->query('SELECT id, name, email, password, role, status FROM ms_users ORDER BY id ASC');
    if (!$res) {
        return $map;
    }

    $ins = $db->prepare(
        'INSERT INTO users (username, password, role, email, status) VALUES (?, ?, ?, ?, ?)'
    );

    while ($row = $res->fetch_assoc()) {
        $old_id = (int)$row['id'];
        $email = trim((string)($row['email'] ?? ''));
        $base = $email !== '' ? strstr($email, '@', true) : preg_replace('/\s+/', '', (string)$row['name']);
        $username = mamun_store_unique_username($db, (string)$base, $old_id);

        if ($skip_existing) {
            $chk = $db->prepare('SELECT id FROM users WHERE username = ? OR (email IS NOT NULL AND email = ? AND ? != "") LIMIT 1');
            if ($chk) {
                $chk->bind_param('sss', $username, $email, $email);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $map[$old_id] = (int)$found['id'];
                    $stats['users']['skipped']++;
                    continue;
                }
            }
        }

        $password = (string)$row['password'];
        $role = mamun_store_map_role((string)($row['role'] ?? 'staff'));
        $status = strtolower((string)($row['status'] ?? 'active')) === 'inactive' ? 0 : 1;
        if ($ins) {
            $ins->bind_param('ssssi', $username, $password, $role, $email, $status);
            $ins->execute();
            $map[$old_id] = (int)$ins->insert_id;
            $stats['users']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
    return $map;
}

function mamun_store_import_purchases(
    mysqli $db,
    array $sup_map,
    array $prod_map,
    array $user_map,
    int $account_id,
    array &$stats,
    bool $skip_existing
): void
{
    $items_res = $db->query(
        'SELECT pi.*, p.supplier_id, p.total, p.paid, p.due, p.purchase_date, p.created_by, p.created_at, p.uuid
         FROM ms_purchase_items pi
         JOIN ms_purchases p ON p.id = pi.purchase_id
         ORDER BY pi.id ASC'
    );
    if (!$items_res) {
        return;
    }

    $ins = $db->prepare(
        'INSERT INTO purchases (
            supplier_id, memo_no, product_id, qty, purchase_price, wholesale_price, sale_price,
            total_cost, account_id, created_by, created_at, paid_amount, due_amount, payment_type, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    while ($row = $items_res->fetch_assoc()) {
        $memo = 'MS-' . substr((string)($row['uuid'] ?? ''), 0, 8) . '-P' . (int)$row['purchase_id'];
        if ($skip_existing) {
            $chk = $db->prepare(
                'SELECT id FROM purchases WHERE memo_no = ? AND product_id = ? LIMIT 1'
            );
            if ($chk) {
                $prod_old = (int)$row['product_id'];
                $prod_new = $prod_map[$prod_old] ?? 0;
                $chk->bind_param('si', $memo, $prod_new);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $chk->close();
                    $stats['purchases']['skipped']++;
                    continue;
                }
                $chk->close();
            }
        }

        $sup_old = (int)($row['supplier_id'] ?? 0);
        $sup_new = ($sup_old > 0 && isset($sup_map[$sup_old])) ? $sup_map[$sup_old] : 0;
        $prod_old = (int)$row['product_id'];
        if (!isset($prod_map[$prod_old])) {
            $stats['purchases']['errors']++;
            continue;
        }
        $prod_new = $prod_map[$prod_old];

        $pr = $db->prepare('SELECT wholesale_price, retail_price FROM ms_products WHERE id = ? LIMIT 1');
        $wholesale = (float)$row['unit_price'];
        $sale = (float)$row['unit_price'];
        if ($pr) {
            $pr->bind_param('i', $prod_old);
            $pr->execute();
            $prow = $pr->get_result()->fetch_assoc();
            $pr->close();
            if ($prow) {
                $wholesale = (float)($prow['wholesale_price'] ?? $wholesale);
                $sale = (float)($prow['retail_price'] ?? $sale);
            }
        }

        $qty = (float)$row['qty'];
        $unit_price = (float)$row['unit_price'];
        $line_total = (float)$row['line_total'];
        $purchase_total = (float)($row['total'] ?? 0);
        $purchase_paid = (float)($row['paid'] ?? 0);
        $ratio = ($purchase_total > 0) ? ($line_total / $purchase_total) : 1;
        $line_paid = round($purchase_paid * $ratio, 2);
        $line_due = round($line_total - $line_paid, 2);
        $payment_type = $line_due > 0.009 ? 'credit' : 'cash';
        $created_by_old = (int)($row['created_by'] ?? 0);
        $created_by = ($created_by_old > 0 && isset($user_map[$created_by_old])) ? $user_map[$created_by_old] : 0;
        $created_at = $row['created_at'] ?: ($row['purchase_date'] . ' 00:00:00');
        $account_bind = $account_id > 0 ? $account_id : 0;
        $notes = 'মামু স্টোর ইমপোর্ট';

        if ($ins) {
            $ins->bind_param(
                'isidddddiisddss',
                $sup_new,
                $memo,
                $prod_new,
                $qty,
                $unit_price,
                $wholesale,
                $sale,
                $line_total,
                $account_bind,
                $created_by,
                $created_at,
                $line_paid,
                $line_due,
                $payment_type,
                $notes
            );
            $ins->execute();
            $stats['purchases']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
}

function mamun_store_import_sales(
    mysqli $db,
    array $cust_map,
    array $prod_map,
    array $user_map,
    array &$stats,
    bool $skip_existing
): array
{
    $sale_map = [];
    $existing = $skip_existing ? mamun_store_existing_invoices($db) : [];

    $res = $db->query(
        'SELECT id, invoice_no, customer_id, sale_type, subtotal, discount, total, paid, due,
                sale_date, created_by, created_at
         FROM ms_sales ORDER BY id ASC'
    );
    if (!$res) {
        return $sale_map;
    }

    $ins = $db->prepare(
        'INSERT INTO sales (
            invoice_number, customer_id, customer_type, status, total_bill, discount, net_payable,
            paid_amount, due_amount, created_by, created_at, completed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    while ($row = $res->fetch_assoc()) {
        $old_id = (int)$row['id'];
        $invoice = trim((string)$row['invoice_no']);
        if ($invoice === '') {
            $invoice = 'MS-INV-' . str_pad((string)$old_id, 6, '0', STR_PAD_LEFT);
        }
        if ($skip_existing && isset($existing[strtolower($invoice)])) {
            $chk = $db->prepare('SELECT id FROM sales WHERE invoice_number = ? LIMIT 1');
            if ($chk) {
                $chk->bind_param('s', $invoice);
                $chk->execute();
                $found = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($found) {
                    $sale_map[$old_id] = (int)$found['id'];
                    $stats['sales']['skipped']++;
                    continue;
                }
            }
        }

        $cust_old = (int)($row['customer_id'] ?? 0);
        $cust_new = ($cust_old > 0 && isset($cust_map[$cust_old])) ? $cust_map[$cust_old] : 0;
        $ctype = strtolower((string)($row['sale_type'] ?? 'retail'));
        $status = 'completed';
        $subtotal = (float)($row['subtotal'] ?? $row['total']);
        $discount = (float)($row['discount'] ?? 0);
        $total = (float)$row['total'];
        $paid = (float)($row['paid'] ?? 0);
        $due = (float)($row['due'] ?? 0);
        $created_by_old = (int)($row['created_by'] ?? 0);
        $created_by = ($created_by_old > 0 && isset($user_map[$created_by_old])) ? $user_map[$created_by_old] : 0;
        $created_at = $row['created_at'] ?: ($row['sale_date'] . ' 00:00:00');
        $completed_at = $created_at;

        if ($ins) {
            $ins->bind_param(
                'sissdddddiss',
                $invoice,
                $cust_new,
                $ctype,
                $status,
                $subtotal,
                $discount,
                $total,
                $paid,
                $due,
                $created_by,
                $created_at,
                $completed_at
            );
            $ins->execute();
            $new_id = (int)$ins->insert_id;
            $sale_map[$old_id] = $new_id;
            $existing[strtolower($invoice)] = true;
            $stats['sales']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }

    $items = $db->query(
        'SELECT sale_id, product_id, qty, unit_price, line_total FROM ms_sale_items ORDER BY id ASC'
    );
    if (!$items) {
        return $sale_map;
    }

    $item_ins = $db->prepare(
        'INSERT INTO sale_items (sale_id, product_id, qty, purchase_price, sale_price) VALUES (?, ?, ?, ?, ?)'
    );

    while ($row = $items->fetch_assoc()) {
        $sale_old = (int)$row['sale_id'];
        if (!isset($sale_map[$sale_old])) {
            $stats['sale_items']['errors']++;
            continue;
        }
        $prod_old = (int)$row['product_id'];
        if (!isset($prod_map[$prod_old])) {
            $stats['sale_items']['errors']++;
            continue;
        }
        $purchase_price = 0.0;
        $pr = $db->prepare('SELECT purchase_price FROM ms_products WHERE id = ? LIMIT 1');
        if ($pr) {
            $pr->bind_param('i', $prod_old);
            $pr->execute();
            $prow = $pr->get_result()->fetch_assoc();
            $pr->close();
            $purchase_price = (float)($prow['purchase_price'] ?? 0);
        }
        $qty = (float)$row['qty'];
        $sale_price = (float)$row['unit_price'];
        if ($item_ins) {
            $item_ins->bind_param(
                'iiddd',
                $sale_map[$sale_old],
                $prod_map[$prod_old],
                $qty,
                $purchase_price,
                $sale_price
            );
            $item_ins->execute();
            $stats['sale_items']['imported']++;
        }
    }
    if ($item_ins) {
        $item_ins->close();
    }

    return $sale_map;
}

function mamun_store_import_ledger(
    mysqli $db,
    array $cust_map,
    array $sale_map,
    array &$stats
): void
{
    $res = $db->query(
        'SELECT type, ref_id, description, debit, credit, entry_date FROM ms_ledger ORDER BY id ASC'
    );
    if (!$res) {
        return;
    }

    $ins = $db->prepare(
        'INSERT INTO customer_ledger (
            customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)'
    );

    while ($row = $res->fetch_assoc()) {
        $type = strtolower((string)$row['type']);
        if ($type !== 'sale') {
            $stats['ledger']['skipped']++;
            continue;
        }
        $ref_id = (int)($row['ref_id'] ?? 0);
        if ($ref_id <= 0 || !isset($sale_map[$ref_id])) {
            $stats['ledger']['skipped']++;
            continue;
        }
        $sale_row = $db->prepare('SELECT customer_id, invoice_no FROM ms_sales WHERE id = ? LIMIT 1');
        if (!$sale_row) {
            continue;
        }
        $sale_row->bind_param('i', $ref_id);
        $sale_row->execute();
        $sdata = $sale_row->get_result()->fetch_assoc();
        $sale_row->close();
        if (!$sdata) {
            $stats['ledger']['skipped']++;
            continue;
        }
        $cust_old = (int)($sdata['customer_id'] ?? 0);
        if ($cust_old <= 0 || !isset($cust_map[$cust_old])) {
            $stats['ledger']['skipped']++;
            continue;
        }
        $module = 'mamun_import';
        $ref_no = (string)($sdata['invoice_no'] ?? '');
        $desc = trim((string)($row['description'] ?? 'মামু স্টোর লেজার'));
        $debit = (float)($row['debit'] ?? 0);
        $credit = (float)($row['credit'] ?? 0);
        $txn_date = (string)$row['entry_date'];
        $balance_field = 'receivable_balance';
        $cust_new = $cust_map[$cust_old];
        if ($ins) {
            $ins->bind_param('issssdds', $cust_new, $txn_date, $module, $ref_no, $desc, $debit, $credit, $balance_field);
            $ins->execute();
            $stats['ledger']['imported']++;
        }
    }
    if ($ins) {
        $ins->close();
    }
}

function mamun_store_empty_stats(): array
{
    return [
        'staging' => [],
        'settings' => [],
        'categories' => ['imported' => 0, 'skipped' => 0],
        'products' => ['imported' => 0, 'skipped' => 0],
        'customers' => ['imported' => 0, 'skipped' => 0],
        'suppliers' => ['imported' => 0, 'skipped' => 0],
        'users' => ['imported' => 0, 'skipped' => 0],
        'purchases' => ['imported' => 0, 'skipped' => 0, 'errors' => 0],
        'sales' => ['imported' => 0, 'skipped' => 0],
        'sale_items' => ['imported' => 0, 'errors' => 0],
        'ledger' => ['imported' => 0, 'skipped' => 0],
    ];
}

function mamun_store_run_migration(mysqli $db, string $sql, array $options = []): array
{
    $opts = array_merge([
        'import_settings' => true,
        'import_master' => true,
        'import_transactions' => true,
        'skip_existing' => true,
    ], $options);

    $stats = mamun_store_empty_stats();
    mamun_store_ensure_schemas($db);
    mamun_store_load_staging($db, $sql);

    foreach (mamun_store_tables() as $table) {
        $stats['staging'][$table] = mamun_store_count($db, $table);
    }

    $db->begin_transaction();
    try {
        if ($opts['import_settings']) {
            mamun_store_import_settings($db, $stats);
        }

        $cat_map = [];
        $prod_map = [];
        $cust_map = [];
        $sup_map = [];
        $user_map = [];
        $sale_map = [];

        if ($opts['import_master']) {
            $cat_map = mamun_store_import_categories($db, $stats, (bool)$opts['skip_existing']);
            $prod_map = mamun_store_import_products($db, $cat_map, $stats, (bool)$opts['skip_existing']);
            $cust_map = mamun_store_import_customers($db, $stats, (bool)$opts['skip_existing']);
            $sup_map = mamun_store_import_suppliers($db, $stats, (bool)$opts['skip_existing']);
            $user_map = mamun_store_import_users($db, $stats, (bool)$opts['skip_existing']);
        }

        if ($opts['import_transactions']) {
            $account_id = mamun_store_default_account_id($db);
            if ($account_id <= 0 && mamun_store_count($db, 'purchase_items') > 0) {
                throw new RuntimeException('ক্রয় ইমপোর্টের জন্য অন্তত একটি সক্রিয় ক্যাশ/ব্যাংক অ্যাকাউন্ট দরকার। Accounts মেনু থেকে অ্যাকাউন্ট তৈরি করুন।');
            }
            mamun_store_import_purchases($db, $sup_map, $prod_map, $user_map, $account_id, $stats, (bool)$opts['skip_existing']);
            $sale_map = mamun_store_import_sales($db, $cust_map, $prod_map, $user_map, $stats, (bool)$opts['skip_existing']);
            mamun_store_import_ledger($db, $cust_map, $sale_map, $stats);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        mamun_store_drop_staging($db);
        throw $e;
    }

    mamun_store_drop_staging($db);
    return $stats;
}
