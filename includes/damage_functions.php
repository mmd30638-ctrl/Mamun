<?php

function ame_damage_reasons(): array
{
    return [
        'broken' => 'ভাঙা',
        'expired' => 'মেয়াদ শেষ',
        'wet' => 'ভেজা / নষ্ট',
        'lost' => 'হারিয়ে গেছে',
        'other' => 'অন্যান্য',
    ];
}

function ame_damage_ensure(mysqli $db): void
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS inventory_damage (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ame_damage_product_cols(mysqli $db): array
{
    static $cols = null;
    if (is_array($cols)) {
        return $cols;
    }
    $cols = [];
    $res = @$db->query('SHOW COLUMNS FROM products');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[$row['Field']] = true;
        }
    }
    return $cols;
}

function ame_damage_cost_sql(array $cols): string
{
    foreach (['purchase_price', 'buy_price', 'cost_price', 'cost'] as $name) {
        if (isset($cols[$name])) {
            return 'p.' . $name;
        }
    }
    return '0';
}

function ame_damage_code_sql(array $cols): string
{
    foreach (['sku', 'product_code', 'barcode'] as $name) {
        if (isset($cols[$name])) {
            return 'p.' . $name;
        }
    }
    return "''";
}

function ame_damage_find_products(mysqli $db, string $q): array
{
    $cols = ame_damage_product_cols($db);
    if (!isset($cols['id'], $cols['name'])) {
        return [];
    }
    $cost = ame_damage_cost_sql($cols);
    $code = ame_damage_code_sql($cols);
    $stock = isset($cols['stock']) ? 'p.stock' : '0';
    $where = '1=1';
    $types = '';
    $params = [];
    if (isset($cols['status'])) {
        if (!function_exists('ame_product_option_sql')) {
            require_once __DIR__ . '/helpers.php';
        }
        $where .= ' AND ' . ame_product_option_sql('p.status');
    }
    if ($q !== '') {
        $where .= " AND (p.name LIKE ? OR {$code} LIKE ?)";
        $like = '%' . $q . '%';
        $types = 'ss';
        $params = [$like, $like];
    }
    $sql = "SELECT p.id, p.name, {$code} AS code, {$code} AS sku, {$stock} AS stock, {$cost} AS unit_cost
            FROM products p
            WHERE {$where}
            ORDER BY p.name ASC
            LIMIT 24";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function ame_damage_apply_stock(mysqli $db, int $productId, float $qty, int $storeId, bool $restore): bool
{
    $delta = $restore ? $qty : -$qty;
    if ($storeId > 0 && function_exists('ame_store_stock_change')) {
        return (bool) ame_store_stock_change($db, $storeId, $productId, $delta, !$restore);
    }
    if ($restore) {
        $upd = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        if (!$upd) {
            return false;
        }
        $upd->bind_param('di', $qty, $productId);
    } else {
        $upd = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
        if (!$upd) {
            return false;
        }
        $upd->bind_param('did', $qty, $productId, $qty);
    }
    $upd->execute();
    $ok = $upd->affected_rows === 1;
    $upd->close();
    return $ok;
}

function ame_damage_log_movement(mysqli $db, int $productId, int $storeId, float $qty, float $unitCost, int $damageId, string $note, bool $restore): void
{
    $has = @$db->query("SHOW TABLES LIKE 'stock_movements'");
    if (!$has || $has->num_rows === 0) {
        return;
    }
    if (!function_exists('ame_sale_log_movement')) {
        $file = __DIR__ . '/sales/sale_exchange.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
    if (!function_exists('ame_sale_log_movement')) {
        return;
    }
    $qtyIn = $restore ? $qty : 0.0;
    $qtyOut = $restore ? 0.0 : $qty;
    ame_sale_log_movement(
        $db,
        $productId,
        $storeId,
        $qtyIn,
        $qtyOut,
        $unitCost,
        'damage',
        'inventory_damage',
        $damageId,
        null,
        $note
    );
}

function ame_damage_fmt_qty(float $qty): string
{
    return rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
}

function ame_damage_report(mysqli $db, string $from, string $to, string $search = ''): array
{
    $reasons = ame_damage_reasons();
    $cols = ame_damage_product_cols($db);
    $code = ame_damage_code_sql($cols);
    $where = "d.damage_date BETWEEN ? AND ?";
    $types = 'ss';
    $params = [$from, $to];
    if ($search !== '') {
        $where .= " AND (p.name LIKE ? OR {$code} LIKE ? OR IFNULL(d.note, '') LIKE ?)";
        $like = '%' . $search . '%';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }
    $rows = [];
    $sql = "SELECT d.id, d.product_id, d.qty, d.unit_cost, d.loss_amount, d.reason, d.note, d.damage_date, d.status,
                   p.name AS product_name, {$code} AS code
            FROM inventory_damage d
            JOIN products p ON p.id = d.product_id
            WHERE {$where}
            ORDER BY d.damage_date DESC, d.id DESC
            LIMIT 400";
    $st = $db->prepare($sql);
    if ($st) {
        $st->bind_param($types, ...$params);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    }
    $posted = array_values(array_filter($rows, static fn($r) => (string) ($r['status'] ?? '') === 'posted'));
    $voided = array_values(array_filter($rows, static fn($r) => (string) ($r['status'] ?? '') !== 'posted'));
    $byReason = [];
    foreach ($reasons as $key => $label) {
        $byReason[$key] = ['key' => $key, 'label' => $label, 'n' => 0, 'qty' => 0.0, 'loss' => 0.0];
    }
    $qty = 0.0;
    $loss = 0.0;
    foreach ($posted as $row) {
        $key = (string) ($row['reason'] ?? 'other');
        if (!isset($byReason[$key])) {
            $byReason[$key] = ['key' => $key, 'label' => $key, 'n' => 0, 'qty' => 0.0, 'loss' => 0.0];
        }
        $byReason[$key]['n']++;
        $byReason[$key]['qty'] += (float) $row['qty'];
        $byReason[$key]['loss'] += (float) $row['loss_amount'];
        $qty += (float) $row['qty'];
        $loss += (float) $row['loss_amount'];
    }
    return [
        'rows' => $rows,
        'posted' => $posted,
        'voided' => $voided,
        'by_reason' => array_values($byReason),
        'n' => count($posted),
        'void_n' => count($voided),
        'qty' => $qty,
        'loss' => $loss,
        'reasons' => $reasons,
    ];
}
