<?php
/**
 * Battery / old-stock trade-in on a sale.
 * Exchange value settles the invoice but is not cash income.
 */

function ensure_sale_exchange_schema($db)
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS sale_exchange_items (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->query(
        "CREATE TABLE IF NOT EXISTS stock_movements (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $sale_cols = [];
    $res = @$db->query('SHOW COLUMNS FROM sales');
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $sale_cols[$col['Field']] = true;
        }
    }
    if (!isset($sale_cols['exchange_amount'])) {
        @$db->query(
            'ALTER TABLE sales ADD COLUMN exchange_amount DECIMAL(12,2) NOT NULL DEFAULT 0'
        );
    }
    if (!isset($sale_cols['cash_paid_amount'])) {
        @$db->query(
            'ALTER TABLE sales ADD COLUMN cash_paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0'
        );
    }

    $prod_cols = [];
    $pres = @$db->query('SHOW COLUMNS FROM products');
    if ($pres) {
        while ($col = $pres->fetch_assoc()) {
            $prod_cols[$col['Field']] = true;
        }
    }
    if (!isset($prod_cols['is_old_battery'])) {
        @$db->query(
            'ALTER TABLE products ADD COLUMN is_old_battery TINYINT(1) NOT NULL DEFAULT 0'
        );
    }
}

function ame_sale_prepare_exchange($db, $rows, $is_english)
{
    if (!is_array($rows) || $rows === []) {
        return [
            'items' => [],
            'total' => 0.0,
        ];
    }

    if (count($rows) > 80) {
        ame_sale_fail(
            $is_english,
            'এক্সচেঞ্জে অতিরিক্ত পণ্য দেওয়া হয়েছে।',
            'Too many exchange products in one sale.'
        );
    }

    $combined = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $product_id = (int)($row['id'] ?? $row['product_id'] ?? 0);
        $qty = (float)($row['qty'] ?? 0);
        $rate = round((float)($row['exchange_rate'] ?? $row['rate'] ?? 0), 2);

        if ($product_id <= 0 || $qty <= 0) {
            ame_sale_fail(
                $is_english,
                'পুরাতন ব্যাটারির পণ্য ও পরিমাণ সঠিক নয়।',
                'Old battery product or quantity is invalid.'
            );
        }
        if ($rate < 0) {
            ame_sale_fail(
                $is_english,
                'এক্সচেঞ্জ রেট শূন্যের কম হতে পারে না।',
                'Exchange rate cannot be negative.'
            );
        }

        if (isset($combined[$product_id])) {
            if (abs($combined[$product_id]['exchange_rate'] - $rate) >= 0.01) {
                ame_sale_fail(
                    $is_english,
                    'একই পুরাতন ব্যাটারিতে একাধিক রেট ব্যবহার করা যাবে না।',
                    'An old battery cannot use multiple exchange rates.'
                );
            }
            $combined[$product_id]['qty'] += $qty;
        } else {
            $combined[$product_id] = [
                'product_id' => $product_id,
                'qty' => $qty,
                'exchange_rate' => $rate,
            ];
        }
    }

    ksort($combined);
    $items = [];
    $total = 0.0;

    foreach ($combined as $requested) {
        $product_id = (int)$requested['product_id'];
        $stmt = $db->prepare(
            "SELECT id, name, purchase_price, stock
             FROM products
             WHERE id = ?
               AND (
                    status = 'active'
                    OR status IS NULL
                    OR status = ''
               )
             FOR UPDATE"
        );
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            ame_sale_fail(
                $is_english,
                'নির্বাচিত পুরাতন ব্যাটারি পাওয়া যায়নি।',
                'A selected old battery was not found.'
            );
        }

        $qty = (float)$requested['qty'];
        $rate = (float)$requested['exchange_rate'];
        $line = round($qty * $rate, 2);
        $total += $line;

        $items[] = [
            'product_id' => $product_id,
            'name' => (string)$product['name'],
            'qty' => $qty,
            'exchange_rate' => $rate,
            'line_total' => $line,
            'unit_cost' => $rate,
        ];
    }

    return [
        'items' => $items,
        'total' => round($total, 2),
    ];
}

function ame_sale_exchange_settlement($grand_total, $exchange_amount, $payment_total)
{
    $grand_total = round((float)$grand_total, 2);
    $exchange_amount = round((float)$exchange_amount, 2);
    $payment_total = round((float)$payment_total, 2);

    $net_cash_due = max(0, round($grand_total - $exchange_amount, 2));
    $cash_paid = min($payment_total, $net_cash_due);
    $paid_for_sale = min(
        round($cash_paid + $exchange_amount, 2),
        $grand_total
    );
    $current_due = max(0, round($grand_total - $paid_for_sale, 2));
    $extra_cash = max(0, round($payment_total - $net_cash_due, 2));

    return [
        'exchange_amount' => $exchange_amount,
        'net_cash_due' => $net_cash_due,
        'cash_paid' => round($cash_paid, 2),
        'paid_for_sale' => round($paid_for_sale, 2),
        'current_due' => $current_due,
        'extra_cash' => $extra_cash,
    ];
}

function ame_sale_log_movement(
    $db,
    $product_id,
    $store_id,
    $qty_in,
    $qty_out,
    $unit_value,
    $movement_type,
    $ref_type,
    $ref_id,
    $customer_id = null,
    $note = ''
) {
    $product_id = (int)$product_id;
    if ($product_id <= 0) {
        return;
    }
    $qty_in = max(0, (float)$qty_in);
    $qty_out = max(0, (float)$qty_out);
    $unit_value = (float)$unit_value;
    $line_value = round(($qty_in + $qty_out) * $unit_value, 2);
    $store_id = (int)$store_id;
    $customer_bind = (int)($customer_id ?? 0);
    if ($customer_bind <= 0) {
        $customer_bind = 0;
    }
    $note = substr(trim((string)$note), 0, 255);
    $ref_id = (int)$ref_id;
    $stmt = $db->prepare(
        'INSERT INTO stock_movements (
            product_id, store_id, qty_in, qty_out, unit_value, line_value,
            movement_type, ref_type, ref_id, customer_id, note
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?)'
    );
    $stmt->bind_param(
        'iiddddssiis',
        $product_id,
        $store_id,
        $qty_in,
        $qty_out,
        $unit_value,
        $line_value,
        $movement_type,
        $ref_type,
        $ref_id,
        $customer_bind,
        $note
    );
    $stmt->execute();
    $stmt->close();
}

function ame_sale_product_available_qty($db, $product_id)
{
    $product_id = (int)$product_id;
    $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
    if ($store_id > 0 && function_exists('ame_store_stock_qty')) {
        return (float)ame_store_stock_qty($db, $store_id, $product_id);
    }
    $stmt = $db->prepare('SELECT stock FROM products WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (float)($row['stock'] ?? 0);
}

function ame_sale_apply_weighted_cost($db, $product_id, $in_qty, $in_rate)
{
    $product_id = (int)$product_id;
    $in_qty = (float)$in_qty;
    $in_rate = (float)$in_rate;
    if ($product_id <= 0 || $in_qty <= 0) {
        return;
    }

    $old_qty = ame_sale_product_available_qty($db, $product_id);
    $stmt = $db->prepare(
        'SELECT purchase_price FROM products WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $old_cost = (float)($row['purchase_price'] ?? 0);
    $new_qty = $old_qty + $in_qty;
    $new_cost = $new_qty > 0.0001
        ? round((($old_qty * $old_cost) + ($in_qty * $in_rate)) / $new_qty, 4)
        : $in_rate;

    $upd = $db->prepare(
        'UPDATE products SET purchase_price = ?, is_old_battery = 1 WHERE id = ?'
    );
    $upd->bind_param('di', $new_cost, $product_id);
    $upd->execute();
    $upd->close();
}

function ame_sale_stock_in_product($db, $product_id, $qty, $is_english)
{
    $product_id = (int)$product_id;
    $qty = (float)$qty;
    $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
    if ($store_id > 0 && function_exists('ame_store_stock_change')) {
        if (!ame_store_stock_change($db, $store_id, $product_id, $qty, false)) {
            throw new RuntimeException(
                $is_english
                    ? 'Old battery stock could not be increased.'
                    : 'পুরাতন ব্যাটারির স্টক বাড়ানো যায়নি।'
            );
        }
        return;
    }

    $stock = $db->prepare(
        'UPDATE products SET stock = stock + ? WHERE id = ?'
    );
    $stock->bind_param('di', $qty, $product_id);
    $stock->execute();
    if ($stock->affected_rows !== 1) {
        $stock->close();
        throw new RuntimeException(
            $is_english
                ? 'Old battery stock could not be increased.'
                : 'পুরাতন ব্যাটারির স্টক বাড়ানো যায়নি।'
        );
    }
    $stock->close();
}

function ame_sale_delete_exchange_items($db, $sale_id)
{
    $sale_id = (int)$sale_id;
    $stmt = $db->prepare('DELETE FROM sale_exchange_items WHERE sale_id = ?');
    $stmt->bind_param('i', $sale_id);
    $stmt->execute();
    $stmt->close();
}

function ame_sale_store_exchange_items($db, $sale_id, $items, $apply_stock, $customer_id, $is_english)
{
    $sale_id = (int)$sale_id;
    ame_sale_delete_exchange_items($db, $sale_id);
    if (!is_array($items) || $items === []) {
        return;
    }

    $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
    $insert = $db->prepare(
        'INSERT INTO sale_exchange_items (
            sale_id, product_id, item_name, qty, exchange_rate, line_total, unit_cost
         ) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($items as $item) {
        $product_id = (int)($item['product_id'] ?? 0);
        $name = (string)($item['name'] ?? '');
        $qty = (float)($item['qty'] ?? 0);
        $rate = (float)($item['exchange_rate'] ?? 0);
        $line = (float)($item['line_total'] ?? round($qty * $rate, 2));
        $cost = (float)($item['unit_cost'] ?? $rate);

        $insert->bind_param(
            'iisdddd',
            $sale_id,
            $product_id,
            $name,
            $qty,
            $rate,
            $line,
            $cost
        );
        $insert->execute();

        if ($apply_stock) {
            ame_sale_apply_weighted_cost($db, $product_id, $qty, $rate);
            ame_sale_stock_in_product($db, $product_id, $qty, $is_english);
            ame_sale_log_movement(
                $db,
                $product_id,
                $store_id,
                $qty,
                0,
                $rate,
                'exchange_in',
                'sale',
                $sale_id,
                $customer_id,
                $name
            );
        }
    }

    $insert->close();
}

function ame_sale_log_sale_out_movements($db, $sale_id, $items, $customer_id)
{
    $sale_id = (int)$sale_id;
    $store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if (!empty($item['is_custom'])) {
            continue;
        }
        $product_id = (int)($item['product_id'] ?? 0);
        $qty = (float)($item['quantity'] ?? $item['qty'] ?? 0);
        if ($product_id <= 0 || $qty <= 0) {
            continue;
        }
        $unit = (float)($item['sale_price'] ?? 0);
        ame_sale_log_movement(
            $db,
            $product_id,
            $store_id,
            0,
            $qty,
            $unit,
            'sale_out',
            'sale',
            $sale_id,
            $customer_id,
            (string)($item['name'] ?? '')
        );
    }
}

function ame_sale_set_exchange_columns($db, $sale_id, $exchange_amount, $cash_paid)
{
    $sale_id = (int)$sale_id;
    $exchange_amount = round((float)$exchange_amount, 2);
    $cash_paid = round((float)$cash_paid, 2);
    $stmt = $db->prepare(
        'UPDATE sales SET exchange_amount = ?, cash_paid_amount = ? WHERE id = ?'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ddi', $exchange_amount, $cash_paid, $sale_id);
    $stmt->execute();
    $stmt->close();
}

function ame_sale_load_exchange_items($db, $sale_id)
{
    $sale_id = (int)$sale_id;
    $items = [];
    $table = @$db->query("SHOW TABLES LIKE 'sale_exchange_items'");
    if (!$table || $table->num_rows === 0) {
        return $items;
    }
    $stmt = $db->prepare(
        'SELECT product_id, item_name, qty, exchange_rate, line_total
         FROM sale_exchange_items
         WHERE sale_id = ?
         ORDER BY id ASC'
    );
    if (!$stmt) {
        return $items;
    }
    $stmt->bind_param('i', $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $qty = (float)$row['qty'];
        $rate = (float)$row['exchange_rate'];
        $items[] = [
            'product_id' => (int)$row['product_id'],
            'name' => (string)$row['item_name'],
            'qty' => $qty,
            'exchange_rate' => $rate,
            'line_total' => (float)$row['line_total'],
        ];
    }
    $stmt->close();
    return $items;
}
