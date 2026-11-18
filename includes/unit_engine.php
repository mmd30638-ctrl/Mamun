<?php
/**
 * Product-wise units. Existing products.stock stays the base quantity.
 * A line with no unit is posted exactly as before.
 */

function ame_unit_ensure(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $db->query("CREATE TABLE IF NOT EXISTS product_unit_map (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        unit_id INT NOT NULL,
        can_purchase TINYINT(1) NOT NULL DEFAULT 1,
        can_sell TINYINT(1) NOT NULL DEFAULT 1,
        is_base TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_product_unit (product_id, unit_id),
        KEY idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS product_unit_factors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        from_unit_id INT NOT NULL,
        to_unit_id INT NOT NULL,
        factor DECIMAL(18,6) NOT NULL,
        UNIQUE KEY uniq_factor (product_id, from_unit_id, to_unit_id),
        KEY idx_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS product_unit_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        unit_id INT NOT NULL,
        purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        sale_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        wholesale_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        dealer_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_price (product_id, unit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS unit_stock_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        txn_date DATE NOT NULL,
        kind VARCHAR(30) NOT NULL,
        entered_qty DECIMAL(18,6) NOT NULL DEFAULT 0,
        unit_id INT NOT NULL DEFAULT 0,
        base_qty DECIMAL(18,6) NOT NULL DEFAULT 0,
        balance_base DECIMAL(18,6) NOT NULL DEFAULT 0,
        ref_type VARCHAR(40) NULL,
        ref_id INT NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_product (product_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([
        'sale_items' => [
            'unit_id' => 'INT NULL',
            'base_qty' => 'DECIMAL(18,6) NULL',
        ],
        'purchases' => [
            'unit_id' => 'INT NULL',
            'base_qty' => 'DECIMAL(18,6) NULL',
        ],
        'sale_return_items' => ['unit_id' => 'INT NULL'],
        'purchase_return_items' => ['unit_id' => 'INT NULL'],
    ] as $table => $cols) {
        $exists = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
        if (!$exists || $exists->num_rows === 0) {
            continue;
        }
        foreach ($cols as $col => $def) {
            $has = $db->query("SHOW COLUMNS FROM `$table` LIKE '" . $db->real_escape_string($col) . "'");
            if ($has && $has->num_rows === 0) {
                $db->query("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            }
        }
    }
}

function ame_unit_num(string $value, int $scale = 6): string
{
    if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
        $value = '0';
    }
    if (function_exists('bcadd')) {
        return bcadd($value, '0', $scale);
    }
    return number_format((float)$value, $scale, '.', '');
}

function ame_unit_mul(string $a, string $b, int $scale = 6): string
{
    if (function_exists('bcmul')) {
        return bcadd(bcmul($a, $b, $scale + 2), '0', $scale);
    }
    return number_format(((float)$a) * ((float)$b), $scale, '.', '');
}

function ame_unit_div(string $a, string $b, int $scale = 6): string
{
    if ((float)$b == 0.0) {
        return '0';
    }
    if (function_exists('bcdiv')) {
        return bcadd(bcdiv($a, $b, $scale + 2), '0', $scale);
    }
    return number_format(((float)$a) / ((float)$b), $scale, '.', '');
}

function ame_unit_base_id(mysqli $db, int $productId): int
{
    $stmt = $db->prepare('SELECT unit_id FROM products WHERE id=?');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['unit_id'] ?? 0);
}

function ame_unit_graph(mysqli $db, int $productId): array
{
    $stmt = $db->prepare('SELECT from_unit_id, to_unit_id, factor FROM product_unit_factors WHERE product_id=?');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $res = $stmt->get_result();
    $edges = [];
    while ($row = $res->fetch_assoc()) {
        $from = (int)$row['from_unit_id'];
        $to = (int)$row['to_unit_id'];
        $factor = ame_unit_num((string)$row['factor']);
        if ((float)$factor <= 0) {
            continue;
        }
        $edges[$from][$to] = $factor;
        $edges[$to][$from] = ame_unit_div('1', $factor);
    }
    $stmt->close();
    return $edges;
}

function ame_unit_convert(array $edges, int $from, int $to, string $qty): ?string
{
    $qty = ame_unit_num($qty);
    if ($from === $to || $from <= 0 || $to <= 0) {
        return $qty;
    }
    $queue = [[$from, '1']];
    $seen = [$from => true];
    while ($queue) {
        [$node, $factor] = array_shift($queue);
        foreach ($edges[$node] ?? [] as $next => $step) {
            if (isset($seen[$next])) {
                continue;
            }
            $nextFactor = ame_unit_mul($factor, (string)$step);
            if ($next === $to) {
                return ame_unit_mul($qty, $nextFactor);
            }
            $seen[$next] = true;
            $queue[] = [$next, $nextFactor];
        }
    }
    return null;
}

function ame_unit_to_base(mysqli $db, int $productId, $qty, int $unitId): string
{
    ame_unit_ensure($db);
    $amount = ame_unit_num((string)$qty);
    $base = ame_unit_base_id($db, $productId);
    if ($unitId <= 0 || $base <= 0 || $unitId === $base) {
        return $amount;
    }
    $converted = ame_unit_convert(ame_unit_graph($db, $productId), $unitId, $base, $amount);
    if ($converted === null) {
        throw new RuntimeException(function_exists('ame_l') ? ame_l('এই ইউনিটের রূপান্তর নেই।', 'This unit has no conversion.') : 'এই ইউনিটের রূপান্তর নেই।');
    }
    return $converted;
}

function ame_unit_name(mysqli $db, int $unitId): string
{
    if ($unitId <= 0) {
        return '';
    }
    $stmt = $db->prepare('SELECT unit_name FROM product_units WHERE id=?');
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('i', $unitId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (string)($row['unit_name'] ?? '');
}

function ame_unit_hint(mysqli $db, int $productId): string
{
    $stmt = $db->prepare('SELECT from_unit_id, to_unit_id, factor FROM product_unit_factors WHERE product_id=? ORDER BY id');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $res = $stmt->get_result();
    $parts = [];
    while ($row = $res->fetch_assoc()) {
        $parts[] = '1 ' . ame_unit_name($db, (int)$row['from_unit_id']) . ' = ' . rtrim(rtrim(ame_unit_num((string)$row['factor']), '0'), '.') . ' ' . ame_unit_name($db, (int)$row['to_unit_id']);
    }
    $stmt->close();
    return implode(' · ', $parts);
}

function ame_unit_stock_base(mysqli $db, int $productId): string
{
    if (function_exists('ame_store_stock_qty') && function_exists('current_store_id') && current_store_id() > 0) {
        return ame_unit_num((string)ame_store_stock_qty($db, current_store_id(), $productId));
    }
    $stmt = $db->prepare('SELECT stock FROM products WHERE id=?');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ame_unit_num((string)($row['stock'] ?? '0'));
}

function ame_unit_move(mysqli $db, int $productId, string $baseDelta, array $meta, bool $requireEnough, bool $touchStock = true): void
{
    ame_unit_ensure($db);
    if (!function_exists('ame_tx_open')) {
        require_once __DIR__ . '/stock_lock.php';
    }
    $ownTx = ame_tx_open($db);
    try {
        ame_lock_product_row($db, $productId);
        ame_unit_move_locked($db, $productId, $baseDelta, $meta, $requireEnough, $touchStock);
        if ($ownTx) {
            $db->commit();
        }
    } catch (Throwable $error) {
        if ($ownTx) {
            $db->rollback();
        }
        throw $error;
    }
}

function ame_unit_move_locked(mysqli $db, int $productId, string $baseDelta, array $meta, bool $requireEnough, bool $touchStock = true): void
{
    $delta = ame_unit_num($baseDelta);
    if ($touchStock) {
        $current = ame_unit_stock_base($db, $productId);
        $next = function_exists('bcadd') ? bcadd($current, $delta, 6) : number_format(((float)$current) + ((float)$delta), 6, '.', '');
        if ($requireEnough && (float)$next < -0.0000001) {
            throw new RuntimeException(function_exists('ame_l') ? ame_l('পর্যাপ্ত স্টক নেই।', 'Not enough stock.') : 'পর্যাপ্ত স্টক নেই।');
        }
        $store = function_exists('current_store_id') ? (int)current_store_id() : 0;
        $floatDelta = (float)$delta;
        if ($store > 0 && function_exists('ame_store_stock_change')) {
            if (!ame_store_stock_change($db, $store, $productId, $floatDelta, $requireEnough)) {
                throw new RuntimeException(function_exists('ame_l') ? ame_l('স্টক আপডেট হয়নি।', 'Stock was not updated.') : 'স্টক আপডেট হয়নি।');
            }
        } else {
            if ($requireEnough && $floatDelta < 0) {
                $need = abs($floatDelta);
                $upd = $db->prepare('UPDATE products SET stock = stock + ? WHERE id=? AND stock >= ?');
                $upd->bind_param('did', $floatDelta, $productId, $need);
            } else {
                $upd = $db->prepare('UPDATE products SET stock = stock + ? WHERE id=?');
                $upd->bind_param('di', $floatDelta, $productId);
            }
            $upd->execute();
            if ($requireEnough && $upd->affected_rows !== 1) {
                $upd->close();
                throw new RuntimeException(function_exists('ame_l') ? ame_l('পর্যাপ্ত স্টক নেই।', 'Not enough stock.') : 'পর্যাপ্ত স্টক নেই।');
            }
            $upd->close();
        }
    }
    $balance = ame_unit_stock_base($db, $productId);
    $kind = (string)($meta['kind'] ?? 'adjust');
    $entered = ame_unit_num((string)($meta['entered_qty'] ?? $delta));
    $unitId = (int)($meta['unit_id'] ?? 0);
    $refType = (string)($meta['ref_type'] ?? '');
    $refId = (int)($meta['ref_id'] ?? 0);
    $note = (string)($meta['note'] ?? '');
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $today = date('Y-m-d');
    $ins = $db->prepare('INSERT INTO unit_stock_ledger (product_id, txn_date, kind, entered_qty, unit_id, base_qty, balance_base, ref_type, ref_id, note, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $ins->bind_param('issdiddsisi', $productId, $today, $kind, $entered, $unitId, $delta, $balance, $refType, $refId, $note, $userId);
    $ins->execute();
    $ins->close();
}

function ame_unit_options(mysqli $db, int $productId, string $role): array
{
    ame_unit_ensure($db);
    $base = ame_unit_base_id($db, $productId);
    $col = $role === 'purchase' ? 'can_purchase' : 'can_sell';
    $stmt = $db->prepare("SELECT m.unit_id, u.unit_name, m.is_base, p.purchase_price, p.sale_price, p.wholesale_price, p.dealer_price
        FROM product_unit_map m
        JOIN product_units u ON u.id = m.unit_id
        LEFT JOIN product_unit_prices p ON p.product_id = m.product_id AND p.unit_id = m.unit_id
        WHERE m.product_id=? AND m.$col=1
        ORDER BY m.is_base DESC, u.unit_name");
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if ($rows === [] && $base > 0) {
        $rows[] = [
            'unit_id' => $base,
            'unit_name' => ame_unit_name($db, $base),
            'is_base' => 1,
            'purchase_price' => null,
            'sale_price' => null,
            'wholesale_price' => null,
            'dealer_price' => null,
        ];
    }
    return $rows;
}
