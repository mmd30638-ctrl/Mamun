<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

function vx_app(): array
{
    static $app;
    if ($app === null) {
        $app = require VX_APP_FILE;
    }
    return $app;
}

function vx_feature(string $key): bool
{
    return in_array($key, vx_app()['features'] ?? [], true);
}

function vx_prefix(): string
{
    $p = (string) (vx_app()['prefix'] ?? '');
    if (!preg_match('/^[a-z][a-z0-9_]{1,12}$/', $p)) {
        throw new RuntimeException('Bad table prefix');
    }
    return $p;
}

function vx_t(string $table): string
{
    if (!preg_match('/^[a-z_]+$/', $table)) {
        throw new RuntimeException('Bad table');
    }
    return '`' . vx_prefix() . $table . '`';
}

function vx_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function vx_money(float $n): string
{
    return number_format($n, 2);
}

function vx_load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($k === '') {
            continue;
        }
        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
    }
}

function vx_env(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? getenv($key);
    return $v === false || $v === null || $v === '' ? $default : (string) $v;
}

function vx_db(): mysqli
{
    static $db;
    if ($db instanceof mysqli) {
        return $db;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli(
        vx_env('DB_HOST', 'localhost'),
        vx_env('DB_USER'),
        vx_env('DB_PASS'),
        vx_env('DB_NAME'),
        (int) vx_env('DB_PORT', '3306')
    );
    $db->set_charset('utf8mb4');
    return $db;
}

function vx_all(string $sql, string $types = '', array $params = []): array
{
    $st = vx_db()->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $st->close();
    return $rows;
}

function vx_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = vx_all($sql, $types, $params);
    return $rows[0] ?? null;
}

function vx_insert(string $sql, string $types, array $params): int
{
    $st = vx_db()->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $id = (int) $st->insert_id;
    $st->close();
    return $id;
}

function vx_exec(string $sql, string $types = '', array $params = []): int
{
    $st = vx_db()->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $n = (int) $st->affected_rows;
    $st->close();
    return $n;
}

function vx_tx(callable $fn): mixed
{
    $db = vx_db();
    $db->begin_transaction();
    try {
        $out = $fn();
        $db->commit();
        return $out;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function vx_installed(): bool
{
    try {
        $row = vx_one('SELECT id FROM ' . vx_t('settings') . ' WHERE id=1');
        return $row !== null;
    } catch (Throwable) {
        return false;
    }
}

function vx_csrf(): string
{
    if (empty($_SESSION['vx_csrf'])) {
        $_SESSION['vx_csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['vx_csrf'];
}

function vx_csrf_check(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    $real = (string) ($_SESSION['vx_csrf'] ?? '');
    if ($real === '' || !hash_equals($real, $sent)) {
        throw new RuntimeException(t('csrf_failed'));
    }
}

function vx_audit(string $action, string $entity, int $entityId, string $detail = ''): void
{
    try {
        vx_insert(
            'INSERT INTO ' . vx_t('audit_log') . ' (user_id, action, entity, entity_id, detail, ip) VALUES (?,?,?,?,?,?)',
            'ississ',
            [
                (int) ($_SESSION['vx_uid'] ?? 0),
                $action,
                $entity,
                $entityId,
                mb_substr($detail, 0, 240),
                mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]
        );
    } catch (Throwable) {
    }
}

function vx_user(): ?array
{
    static $user;
    if ($user !== null) {
        return $user ?: null;
    }
    $id = (int) ($_SESSION['vx_uid'] ?? 0);
    if ($id <= 0) {
        $user = false;
        return null;
    }
    $user = vx_one('SELECT * FROM ' . vx_t('users') . ' WHERE id=? AND status=1', 'i', [$id]) ?: false;
    return $user ?: null;
}

function vx_can(string $perm): bool
{
    $u = vx_user();
    if (!$u) {
        return false;
    }
    if (($u['role_slug'] ?? '') === 'admin') {
        return true;
    }
    $row = vx_one(
        'SELECT id FROM ' . vx_t('role_permissions') . ' WHERE role_slug=? AND perm_key=? LIMIT 1',
        'ss',
        [(string) $u['role_slug'], $perm]
    );
    return $row !== null;
}

function vx_require(string $perm): void
{
    if (!vx_user()) {
        header('Location: index.php?r=login');
        exit;
    }
    if (!vx_can($perm)) {
        http_response_code(403);
        vx_layout('forbidden', '<p class="alert bad">' . vx_e(t('no_access')) . '</p>');
        exit;
    }
}

function vx_next_memo(string $prefix): string
{
    $lock = vx_prefix() . 'memo';
    vx_db()->query("SELECT GET_LOCK('" . vx_db()->real_escape_string($lock) . "', 5)");
    $like = $prefix . date('ymd') . '-%';
    $row = vx_one(
        'SELECT memo_no FROM ' . vx_t('memos') . ' WHERE memo_no LIKE ? ORDER BY id DESC LIMIT 1',
        's',
        [$like]
    );
    $n = 1;
    if ($row && preg_match('/-(\d+)$/', (string) $row['memo_no'], $m)) {
        $n = ((int) $m[1]) + 1;
    }
    $memo = $prefix . date('ymd') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    vx_insert('INSERT INTO ' . vx_t('memos') . ' (memo_no) VALUES (?)', 's', [$memo]);
    vx_db()->query("SELECT RELEASE_LOCK('" . vx_db()->real_escape_string($lock) . "')");
    return $memo;
}

function vx_cash(int $accountId, string $direction, float $amount, string $refType, int $refId, string $note): void
{
    $amount = round($amount, 2);
    if ($accountId <= 0 || $amount <= 0) {
        return;
    }
    if (!in_array($direction, ['in', 'out'], true)) {
        throw new RuntimeException('Bad cash direction');
    }
    $acc = vx_one('SELECT id, balance FROM ' . vx_t('accounts') . ' WHERE id=? AND status=1 FOR UPDATE', 'i', [$accountId]);
    if (!$acc) {
        throw new RuntimeException(t('need_account'));
    }
    $balance = round((float) $acc['balance'], 2);
    if ($direction === 'out' && $balance + 0.001 < $amount) {
        throw new RuntimeException(t('low_balance'));
    }
    $next = $direction === 'in' ? $balance + $amount : $balance - $amount;
    vx_exec('UPDATE ' . vx_t('accounts') . ' SET balance=? WHERE id=?', 'di', [round($next, 2), $accountId]);
    vx_insert(
        'INSERT INTO ' . vx_t('ledger') . ' (account_id, direction, amount, balance_after, ref_type, ref_id, note, created_by) VALUES (?,?,?,?,?,?,?,?)',
        'isddsisi',
        [$accountId, $direction, $amount, round($next, 2), $refType, $refId, mb_substr($note, 0, 180), (int) ($_SESSION['vx_uid'] ?? 0)]
    );
}

function vx_loc_add(int $productId, int $locationId, float $delta): void
{
    if ($locationId <= 0) {
        $locationId = 1;
    }
    $row = vx_one(
        'SELECT id, qty FROM ' . vx_t('loc_stock') . ' WHERE product_id=? AND location_id=? FOR UPDATE',
        'ii',
        [$productId, $locationId]
    );
    $next = round(((float) ($row['qty'] ?? 0)) + $delta, 3);
    if ($next < -0.0001) {
        throw new RuntimeException(t('low_stock'));
    }
    if ($row) {
        vx_exec('UPDATE ' . vx_t('loc_stock') . ' SET qty=? WHERE id=?', 'di', [$next, (int) $row['id']]);
    } else {
        vx_insert(
            'INSERT INTO ' . vx_t('loc_stock') . ' (product_id, location_id, qty) VALUES (?,?,?)',
            'iid',
            [$productId, $locationId, $next]
        );
    }
}

function vx_move_stock(int $productId, float $delta, string $reason, string $refType, int $refId, ?int $batchId, int $locationId): void
{
    $p = vx_one('SELECT id, stock, kind, track_batch FROM ' . vx_t('products') . ' WHERE id=? FOR UPDATE', 'i', [$productId]);
    if (!$p) {
        throw new RuntimeException(t('not_found'));
    }
    if (($p['kind'] ?? '') === 'service') {
        return;
    }
    $next = round((float) $p['stock'] + $delta, 3);
    if ($next < -0.0001) {
        throw new RuntimeException(t('low_stock'));
    }
    vx_exec('UPDATE ' . vx_t('products') . ' SET stock=? WHERE id=?', 'di', [$next, $productId]);
    if ($batchId) {
        $b = vx_one('SELECT id, qty FROM ' . vx_t('batches') . ' WHERE id=? AND product_id=? FOR UPDATE', 'ii', [$batchId, $productId]);
        if (!$b) {
            throw new RuntimeException(t('need_batch'));
        }
        $bq = round((float) $b['qty'] + $delta, 3);
        if ($bq < -0.0001) {
            throw new RuntimeException(t('low_stock'));
        }
        vx_exec('UPDATE ' . vx_t('batches') . ' SET qty=? WHERE id=?', 'di', [$bq, $batchId]);
    }
    vx_loc_add($productId, $locationId, $delta);
    vx_insert(
        'INSERT INTO ' . vx_t('stock_moves') . ' (product_id, batch_id, location_id, qty_delta, reason, ref_type, ref_id, created_by) VALUES (?,?,?,?,?,?,?,?)',
        'iiidssii',
        [$productId, (int) $batchId, $locationId, $delta, $reason, $refType, $refId, (int) ($_SESSION['vx_uid'] ?? 0)]
    );
}

function vx_allocate_fefo(int $productId, float $baseQty): array
{
    $rows = vx_all(
        'SELECT id, qty, purchase_price FROM ' . vx_t('batches') . ' WHERE product_id=? AND qty>0 AND (expiry_date IS NULL OR expiry_date>=CURDATE()) ORDER BY expiry_date IS NULL, expiry_date ASC, id ASC FOR UPDATE',
        'i',
        [$productId]
    );
    $left = round($baseQty, 3);
    $out = [];
    foreach ($rows as $row) {
        if ($left <= 0) {
            break;
        }
        $take = min($left, (float) $row['qty']);
        $out[] = ['id' => (int) $row['id'], 'qty' => round($take, 3), 'cost' => (float) $row['purchase_price']];
        $left = round($left - $take, 3);
    }
    if ($left > 0.0001) {
        throw new RuntimeException(t('low_stock'));
    }
    return $out;
}

function vx_unit_factor(int $unitId): float
{
    if ($unitId <= 0) {
        return 1.0;
    }
    $u = vx_one('SELECT factor FROM ' . vx_t('units') . ' WHERE id=?', 'i', [$unitId]);
    $f = (float) ($u['factor'] ?? 1);
    return $f > 0 ? $f : 1.0;
}

function vx_upsert_batch(int $productId, string $batchNo, ?string $mfg, ?string $exp, float $cost, float $retail): int
{
    $batchNo = trim($batchNo) !== '' ? trim($batchNo) : ('B' . date('ymdHis'));
    $row = vx_one(
        'SELECT id FROM ' . vx_t('batches') . ' WHERE product_id=? AND batch_no=? FOR UPDATE',
        'is',
        [$productId, $batchNo]
    );
    if ($row) {
        vx_exec(
            'UPDATE ' . vx_t('batches') . ' SET purchase_price=?, retail_price=?, mfg_date=COALESCE(?, mfg_date), expiry_date=COALESCE(?, expiry_date) WHERE id=?',
            'ddssi',
            [$cost, $retail, $mfg, $exp, (int) $row['id']]
        );
        return (int) $row['id'];
    }
    return vx_insert(
        'INSERT INTO ' . vx_t('batches') . ' (product_id, batch_no, mfg_date, expiry_date, qty, purchase_price, retail_price) VALUES (?,?,?,?,0,?,?)',
        'isssdd',
        [$productId, $batchNo, $mfg, $exp, $cost, $retail]
    );
}

function vx_post_purchase(array $in): int
{
    return (int) vx_tx(function () use ($in) {
        $lines = $in['lines'] ?? [];
        if ($lines === []) {
            throw new RuntimeException(t('need_lines'));
        }
        $supplier = (int) ($in['party_id'] ?? 0);
        $account = (int) ($in['account_id'] ?? 0);
        $paid = round((float) ($in['paid'] ?? 0), 2);
        $note = trim((string) ($in['note'] ?? ''));
        $loc = (int) ($in['location_id'] ?? 1);
        $total = 0.0;
        $clean = [];
        foreach ($lines as $line) {
            $pid = (int) ($line['product_id'] ?? 0);
            $qty = round((float) ($line['qty'] ?? 0), 3);
            $price = round((float) ($line['price'] ?? 0), 2);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $factor = vx_unit_factor((int) ($line['unit_id'] ?? 0));
            $base = round($qty * $factor, 3);
            $lineTotal = round($qty * $price, 2);
            $total += $lineTotal;
            $clean[] = $line + ['factor' => $factor, 'base' => $base, 'line_total' => $lineTotal];
        }
        if ($clean === []) {
            throw new RuntimeException(t('need_lines'));
        }
        $total = round($total, 2);
        if ($paid < 0 || $paid - $total > 0.001) {
            throw new RuntimeException(t('bad_paid'));
        }
        $due = round($total - $paid, 2);
        $memo = vx_next_memo('P');
        $id = vx_insert(
            'INSERT INTO ' . vx_t('purchases') . ' (memo_no, party_id, account_id, location_id, total, paid, due, note, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
            'siiidddsi',
            [$memo, $supplier, $account, $loc, $total, $paid, $due, $note, (int) ($_SESSION['vx_uid'] ?? 0)]
        );
        foreach ($clean as $line) {
            $product = vx_one('SELECT id, track_batch, kind FROM ' . vx_t('products') . ' WHERE id=? AND deleted_at IS NULL', 'i', [(int) $line['product_id']]);
            if (!$product) {
                throw new RuntimeException(t('not_found'));
            }
            $batchId = 0;
            if ((int) $product['track_batch'] === 1 && ($product['kind'] ?? '') !== 'service') {
                $batchId = vx_upsert_batch(
                    (int) $product['id'],
                    (string) ($line['batch_no'] ?? ''),
                    ($line['mfg_date'] ?? '') !== '' ? (string) $line['mfg_date'] : null,
                    ($line['expiry_date'] ?? '') !== '' ? (string) $line['expiry_date'] : null,
                    round(((float) $line['price']) / (float) $line['factor'], 4),
                    round((float) ($line['retail'] ?? $line['price']) / (float) $line['factor'], 4)
                );
            }
            vx_insert(
                'INSERT INTO ' . vx_t('purchase_items') . ' (purchase_id, product_id, batch_id, unit_id, qty, factor, base_qty, price, line_total) VALUES (?,?,?,?,?,?,?,?,?)',
                'iiiiddddd',
                [$id, (int) $line['product_id'], $batchId, (int) ($line['unit_id'] ?? 0), (float) $line['qty'], (float) $line['factor'], (float) $line['base'], (float) $line['price'], (float) $line['line_total']]
            );
            if (($product['kind'] ?? '') !== 'service') {
                vx_move_stock((int) $product['id'], (float) $line['base'], 'purchase', 'purchase', $id, $batchId ?: null, $loc);
                $pieceCost = round(((float) $line['price']) / (float) $line['factor'], 4);
                vx_exec('UPDATE ' . vx_t('products') . ' SET purchase_price=? WHERE id=?', 'di', [$pieceCost, (int) $product['id']]);
            }
        }
        if ($due > 0 && $supplier > 0) {
            vx_exec('UPDATE ' . vx_t('parties') . ' SET payable=payable+? WHERE id=?', 'di', [$due, $supplier]);
        }
        vx_cash($account, 'out', $paid, 'purchase', $id, $memo);
        vx_audit('purchase', 'purchases', $id, $memo);
        return $id;
    });
}

function vx_post_sale(array $in): int
{
    return (int) vx_tx(function () use ($in) {
        $linesIn = $in['lines'] ?? [];
        $clean = [];
        foreach ($linesIn as $line) {
            $pid = (int) ($line['product_id'] ?? 0);
            $qty = round((float) ($line['qty'] ?? 0), 3);
            $price = round((float) ($line['price'] ?? 0), 2);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $factor = vx_unit_factor((int) ($line['unit_id'] ?? 0));
            $clean[] = [
                'product_id' => $pid,
                'variant_id' => (int) ($line['variant_id'] ?? 0),
                'unit_id' => (int) ($line['unit_id'] ?? 0),
                'qty' => $qty,
                'price' => $price,
                'factor' => $factor,
                'base' => round($qty * $factor, 3),
                'line_total' => round($qty * $price, 2),
            ];
        }
        if ($clean === []) {
            throw new RuntimeException(t('need_lines'));
        }
        $gross = round(array_sum(array_column($clean, 'line_total')), 2);
        $discount = round((float) ($in['discount'] ?? 0), 2);
        if ($discount < 0 || $discount - $gross > 0.001) {
            throw new RuntimeException(t('bad_paid'));
        }
        $net = round($gross - $discount, 2);
        $paid = round((float) ($in['paid'] ?? 0), 2);
        if ($paid < 0 || $paid - $net > 0.001) {
            throw new RuntimeException(t('bad_paid'));
        }
        $due = round($net - $paid, 2);
        $party = (int) ($in['party_id'] ?? 0);
        if ($due > 0 && $party <= 0) {
            throw new RuntimeException(t('need_party'));
        }
        $account = (int) ($in['account_id'] ?? 0);
        $loc = (int) ($in['location_id'] ?? 1);
        $memo = vx_next_memo('S');
        $ratio = $gross > 0 ? ($net / $gross) : 1;
        $saleId = vx_insert(
            'INSERT INTO ' . vx_t('sales') . ' (memo_no, party_id, account_id, location_id, channel, booking_id, gross, discount, net, paid, due, note, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            'siiisidddddsi',
            [
                $memo,
                $party,
                $account,
                $loc,
                (string) ($in['channel'] ?? 'pos'),
                (int) ($in['booking_id'] ?? 0),
                $gross,
                $discount,
                $net,
                $paid,
                $due,
                trim((string) ($in['note'] ?? '')),
                (int) ($_SESSION['vx_uid'] ?? 0),
            ]
        );
        foreach ($clean as $line) {
            $product = vx_one('SELECT * FROM ' . vx_t('products') . ' WHERE id=? AND deleted_at IS NULL FOR UPDATE', 'i', [$line['product_id']]);
            if (!$product) {
                throw new RuntimeException(t('not_found'));
            }
            $alloc = [['id' => 0, 'qty' => $line['base'], 'cost' => (float) $product['purchase_price']]];
            if (($product['kind'] ?? '') !== 'service' && (int) $product['track_batch'] === 1) {
                $alloc = vx_allocate_fefo((int) $product['id'], (float) $line['base']);
            }
            $share = $line['line_total'] > 0 ? $line['line_total'] / $gross : 0;
            $first = true;
            foreach ($alloc as $part) {
                $partQty = (float) $part['qty'];
                $partUnits = $line['factor'] > 0 ? round($partQty / $line['factor'], 3) : $partQty;
                $partTotal = round($line['line_total'] * ($line['base'] > 0 ? $partQty / $line['base'] : 1) * ($first || true ? 1 : 1), 2);
                if (!$first) {
                    $partTotal = round($line['line_total'] * ($line['base'] > 0 ? $partQty / $line['base'] : 1), 2);
                }
                $partTotal = round($line['line_total'] * ($line['base'] > 0 ? $partQty / $line['base'] : 1), 2);
                vx_insert(
                    'INSERT INTO ' . vx_t('sale_items') . ' (sale_id, product_id, variant_id, batch_id, unit_id, qty, factor, base_qty, price, cost_price, line_total) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    'iiiiidddddd',
                    [
                        $saleId,
                        (int) $product['id'],
                        $line['variant_id'],
                        (int) $part['id'],
                        $line['unit_id'],
                        $partUnits,
                        $line['factor'],
                        $partQty,
                        $line['price'],
                        (float) $part['cost'],
                        $partTotal,
                    ]
                );
                if (($product['kind'] ?? '') !== 'service') {
                    vx_move_stock((int) $product['id'], -$partQty, 'sale', 'sale', $saleId, ((int) $part['id']) ?: null, $loc);
                }
                if ($line['variant_id'] > 0) {
                    vx_exec('UPDATE ' . vx_t('variants') . ' SET stock=stock-? WHERE id=? AND product_id=?', 'dii', [$partQty, $line['variant_id'], (int) $product['id']]);
                }
                $first = false;
            }
            unset($share, $ratio);
        }
        if ($due > 0) {
            vx_exec('UPDATE ' . vx_t('parties') . ' SET receivable=receivable+? WHERE id=?', 'di', [$due, $party]);
        }
        vx_cash($account, 'in', $paid, 'sale', $saleId, $memo);
        if (!empty($in['doctor_id']) || trim((string) ($in['rx_note'] ?? '')) !== '') {
            vx_insert(
                'INSERT INTO ' . vx_t('prescriptions') . ' (sale_id, doctor_id, patient_name, note) VALUES (?,?,?,?)',
                'iiss',
                [$saleId, (int) ($in['doctor_id'] ?? 0), trim((string) ($in['patient_name'] ?? '')), trim((string) ($in['rx_note'] ?? ''))]
            );
        }
        if (!empty($in['employee_id']) && vx_feature('commission')) {
            $rate = (float) ($in['commission_rate'] ?? 0);
            if ($rate > 0) {
                vx_insert(
                    'INSERT INTO ' . vx_t('commissions') . ' (employee_id, sale_id, amount) VALUES (?,?,?)',
                    'iid',
                    [(int) $in['employee_id'], $saleId, round($net * $rate / 100, 2)]
                );
            }
        }
        vx_audit('sale', 'sales', $saleId, $memo);
        return $saleId;
    });
}

function vx_post_sale_return(int $saleId, array $qtys): int
{
    return (int) vx_tx(function () use ($saleId, $qtys) {
        $sale = vx_one('SELECT * FROM ' . vx_t('sales') . ' WHERE id=? FOR UPDATE', 'i', [$saleId]);
        if (!$sale || ($sale['status'] ?? '') === 'void') {
            throw new RuntimeException(t('not_found'));
        }
        $items = vx_all('SELECT * FROM ' . vx_t('sale_items') . ' WHERE sale_id=?', 'i', [$saleId]);
        $total = 0.0;
        $picked = [];
        foreach ($items as $item) {
            $rq = round((float) ($qtys[(int) $item['id']] ?? 0), 3);
            $left = round((float) $item['qty'] - (float) $item['returned_qty'], 3);
            if ($rq <= 0) {
                continue;
            }
            if ($rq - $left > 0.0001) {
                throw new RuntimeException(t('bad_qty'));
            }
            $value = round($rq * (float) $item['price'], 2);
            $total += $value;
            $picked[] = ['item' => $item, 'qty' => $rq, 'value' => $value];
        }
        if ($picked === []) {
            throw new RuntimeException(t('need_lines'));
        }
        $total = round($total, 2);
        $memo = vx_next_memo('SR');
        $rid = vx_insert(
            'INSERT INTO ' . vx_t('sale_returns') . ' (memo_no, sale_id, total, created_by) VALUES (?,?,?,?)',
            'sidi',
            [$memo, $saleId, $total, (int) ($_SESSION['vx_uid'] ?? 0)]
        );
        foreach ($picked as $p) {
            $item = $p['item'];
            $base = round((float) $p['qty'] * (float) $item['factor'], 3);
            vx_insert(
                'INSERT INTO ' . vx_t('sale_return_items') . ' (return_id, sale_item_id, qty, base_qty, line_total) VALUES (?,?,?,?,?)',
                'iiddd',
                [$rid, (int) $item['id'], (float) $p['qty'], $base, (float) $p['value']]
            );
            vx_exec('UPDATE ' . vx_t('sale_items') . ' SET returned_qty=returned_qty+? WHERE id=?', 'di', [(float) $p['qty'], (int) $item['id']]);
            $product = vx_one('SELECT kind FROM ' . vx_t('products') . ' WHERE id=?', 'i', [(int) $item['product_id']]);
            if (($product['kind'] ?? '') !== 'service') {
                vx_move_stock((int) $item['product_id'], $base, 'sale_return', 'sale_return', $rid, ((int) $item['batch_id']) ?: null, (int) $sale['location_id']);
            }
        }
        $dueCut = min((float) $sale['due'], $total);
        $refund = round($total - $dueCut, 2);
        if ($dueCut > 0) {
            vx_exec('UPDATE ' . vx_t('sales') . ' SET due=due-? WHERE id=?', 'di', [$dueCut, $saleId]);
            if ((int) $sale['party_id'] > 0) {
                vx_exec('UPDATE ' . vx_t('parties') . ' SET receivable=GREATEST(receivable-?,0) WHERE id=?', 'di', [$dueCut, (int) $sale['party_id']]);
            }
        }
        vx_cash((int) $sale['account_id'], 'out', $refund, 'sale_return', $rid, $memo);
        vx_audit('sale_return', 'sale_returns', $rid, $memo);
        return $rid;
    });
}

function vx_post_purchase_return(int $purchaseId, array $qtys): int
{
    return (int) vx_tx(function () use ($purchaseId, $qtys) {
        $purchase = vx_one('SELECT * FROM ' . vx_t('purchases') . ' WHERE id=? FOR UPDATE', 'i', [$purchaseId]);
        if (!$purchase) {
            throw new RuntimeException(t('not_found'));
        }
        $items = vx_all('SELECT * FROM ' . vx_t('purchase_items') . ' WHERE purchase_id=?', 'i', [$purchaseId]);
        $total = 0.0;
        $picked = [];
        foreach ($items as $item) {
            $rq = round((float) ($qtys[(int) $item['id']] ?? 0), 3);
            $left = round((float) $item['qty'] - (float) $item['returned_qty'], 3);
            if ($rq <= 0) {
                continue;
            }
            if ($rq - $left > 0.0001) {
                throw new RuntimeException(t('bad_qty'));
            }
            $value = round($rq * (float) $item['price'], 2);
            $total += $value;
            $picked[] = ['item' => $item, 'qty' => $rq, 'value' => $value];
        }
        if ($picked === []) {
            throw new RuntimeException(t('need_lines'));
        }
        $total = round($total, 2);
        $memo = vx_next_memo('PR');
        $rid = vx_insert(
            'INSERT INTO ' . vx_t('purchase_returns') . ' (memo_no, purchase_id, total, created_by) VALUES (?,?,?,?)',
            'sidi',
            [$memo, $purchaseId, $total, (int) ($_SESSION['vx_uid'] ?? 0)]
        );
        foreach ($picked as $p) {
            $item = $p['item'];
            $base = round((float) $p['qty'] * (float) $item['factor'], 3);
            vx_insert(
                'INSERT INTO ' . vx_t('purchase_return_items') . ' (return_id, purchase_item_id, qty, base_qty, line_total) VALUES (?,?,?,?,?)',
                'iiddd',
                [$rid, (int) $item['id'], (float) $p['qty'], $base, (float) $p['value']]
            );
            vx_exec('UPDATE ' . vx_t('purchase_items') . ' SET returned_qty=returned_qty+? WHERE id=?', 'di', [(float) $p['qty'], (int) $item['id']]);
            vx_move_stock((int) $item['product_id'], -$base, 'purchase_return', 'purchase_return', $rid, ((int) $item['batch_id']) ?: null, (int) $purchase['location_id']);
        }
        $dueCut = min((float) $purchase['due'], $total);
        $refund = round($total - $dueCut, 2);
        if ($dueCut > 0) {
            vx_exec('UPDATE ' . vx_t('purchases') . ' SET due=due-? WHERE id=?', 'di', [$dueCut, $purchaseId]);
            if ((int) $purchase['party_id'] > 0) {
                vx_exec('UPDATE ' . vx_t('parties') . ' SET payable=GREATEST(payable-?,0) WHERE id=?', 'di', [$dueCut, (int) $purchase['party_id']]);
            }
        }
        vx_cash((int) $purchase['account_id'], 'in', $refund, 'purchase_return', $rid, $memo);
        vx_audit('purchase_return', 'purchase_returns', $rid, $memo);
        return $rid;
    });
}

function vx_party_payment(int $partyId, int $accountId, float $amount, string $direction): void
{
    vx_tx(function () use ($partyId, $accountId, $amount, $direction) {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException(t('bad_paid'));
        }
        $party = vx_one('SELECT * FROM ' . vx_t('parties') . ' WHERE id=? AND deleted_at IS NULL FOR UPDATE', 'i', [$partyId]);
        if (!$party) {
            throw new RuntimeException(t('not_found'));
        }
        if ($direction === 'receive') {
            if ((float) $party['receivable'] + 0.001 < $amount) {
                throw new RuntimeException(t('bad_paid'));
            }
            vx_exec('UPDATE ' . vx_t('parties') . ' SET receivable=receivable-? WHERE id=?', 'di', [$amount, $partyId]);
            vx_cash($accountId, 'in', $amount, 'receipt', $partyId, 'due');
        } else {
            if ((float) $party['payable'] + 0.001 < $amount) {
                throw new RuntimeException(t('bad_paid'));
            }
            vx_exec('UPDATE ' . vx_t('parties') . ' SET payable=payable-? WHERE id=?', 'di', [$amount, $partyId]);
            vx_cash($accountId, 'out', $amount, 'payment', $partyId, 'due');
        }
        vx_audit($direction, 'parties', $partyId, (string) $amount);
    });
}

function vx_schema_sql(): array
{
    $p = vx_prefix();
    $e = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $sql = [];
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}users` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(60) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(120) NOT NULL,
        role_slug VARCHAR(40) NOT NULL DEFAULT 'cashier',
        phone VARCHAR(30) NOT NULL DEFAULT '',
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user (username),
        KEY idx_role (role_slug)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}role_permissions` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_slug VARCHAR(40) NOT NULL,
        perm_key VARCHAR(60) NOT NULL,
        UNIQUE KEY uq_perm (role_slug, perm_key)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}settings` (
        id TINYINT PRIMARY KEY,
        business_name VARCHAR(160) NOT NULL,
        address VARCHAR(255) NOT NULL DEFAULT '',
        phone VARCHAR(40) NOT NULL DEFAULT '',
        logo VARCHAR(180) NOT NULL DEFAULT '',
        invoice_note VARCHAR(255) NOT NULL DEFAULT '',
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}memos` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        memo_no VARCHAR(40) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_memo (memo_no)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}accounts` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        kind VARCHAR(20) NOT NULL DEFAULT 'cash',
        balance DECIMAL(14,2) NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_kind (kind)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}ledger` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        direction VARCHAR(8) NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        balance_after DECIMAL(14,2) NOT NULL DEFAULT 0,
        ref_type VARCHAR(40) NOT NULL DEFAULT '',
        ref_id INT NOT NULL DEFAULT 0,
        note VARCHAR(180) NOT NULL DEFAULT '',
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_acc (account_id),
        KEY idx_ref (ref_type, ref_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}parties` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(20) NOT NULL DEFAULT 'customer',
        name VARCHAR(160) NOT NULL,
        phone VARCHAR(40) NOT NULL DEFAULT '',
        address VARCHAR(255) NOT NULL DEFAULT '',
        receivable DECIMAL(14,2) NOT NULL DEFAULT 0,
        payable DECIMAL(14,2) NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        KEY idx_kind (kind),
        KEY idx_phone (phone)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}employees` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        phone VARCHAR(40) NOT NULL DEFAULT '',
        job_title VARCHAR(80) NOT NULL DEFAULT '',
        salary DECIMAL(12,2) NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}salary_payments` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        month_key CHAR(7) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        account_id INT NOT NULL,
        note VARCHAR(180) NOT NULL DEFAULT '',
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_salary (employee_id, month_key),
        KEY idx_emp (employee_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}categories` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        UNIQUE KEY uq_cat (name)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}brands` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        UNIQUE KEY uq_brand (name)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}units` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(60) NOT NULL,
        factor DECIMAL(12,4) NOT NULL DEFAULT 1,
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_unit (name)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}locations` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_loc (name)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}products` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sku VARCHAR(60) NOT NULL,
        barcode VARCHAR(60) NOT NULL DEFAULT '',
        name VARCHAR(180) NOT NULL,
        generic_name VARCHAR(180) NOT NULL DEFAULT '',
        category_id INT NOT NULL DEFAULT 0,
        brand_id INT NOT NULL DEFAULT 0,
        unit_id INT NOT NULL DEFAULT 0,
        kind VARCHAR(20) NOT NULL DEFAULT 'product',
        purchase_price DECIMAL(12,4) NOT NULL DEFAULT 0,
        retail_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        dealer_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        stock DECIMAL(14,3) NOT NULL DEFAULT 0,
        min_stock DECIMAL(14,3) NOT NULL DEFAULT 0,
        track_batch TINYINT NOT NULL DEFAULT 0,
        image VARCHAR(180) NOT NULL DEFAULT '',
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        UNIQUE KEY uq_sku (sku),
        KEY idx_barcode (barcode),
        KEY idx_name (name),
        KEY idx_cat (category_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}batches` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        batch_no VARCHAR(60) NOT NULL,
        mfg_date DATE NULL,
        expiry_date DATE NULL,
        qty DECIMAL(14,3) NOT NULL DEFAULT 0,
        purchase_price DECIMAL(12,4) NOT NULL DEFAULT 0,
        retail_price DECIMAL(12,4) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_batch (product_id, batch_no),
        KEY idx_exp (expiry_date),
        KEY idx_prod (product_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}variants` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        name VARCHAR(120) NOT NULL,
        sku VARCHAR(60) NOT NULL,
        barcode VARCHAR(60) NOT NULL DEFAULT '',
        retail_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        stock DECIMAL(14,3) NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_vsku (sku),
        KEY idx_prod (product_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}loc_stock` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        location_id INT NOT NULL,
        qty DECIMAL(14,3) NOT NULL DEFAULT 0,
        UNIQUE KEY uq_loc_prod (product_id, location_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}stock_moves` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        batch_id INT NOT NULL DEFAULT 0,
        location_id INT NOT NULL DEFAULT 1,
        qty_delta DECIMAL(14,3) NOT NULL,
        reason VARCHAR(40) NOT NULL,
        ref_type VARCHAR(40) NOT NULL DEFAULT '',
        ref_id INT NOT NULL DEFAULT 0,
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_prod (product_id),
        KEY idx_ref (ref_type, ref_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}purchases` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        memo_no VARCHAR(40) NOT NULL,
        party_id INT NOT NULL DEFAULT 0,
        account_id INT NOT NULL DEFAULT 0,
        location_id INT NOT NULL DEFAULT 1,
        total DECIMAL(14,2) NOT NULL DEFAULT 0,
        paid DECIMAL(14,2) NOT NULL DEFAULT 0,
        due DECIMAL(14,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'posted',
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pmemo (memo_no),
        KEY idx_party (party_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}purchase_items` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        product_id INT NOT NULL,
        batch_id INT NOT NULL DEFAULT 0,
        unit_id INT NOT NULL DEFAULT 0,
        qty DECIMAL(14,3) NOT NULL,
        factor DECIMAL(12,4) NOT NULL DEFAULT 1,
        base_qty DECIMAL(14,3) NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        line_total DECIMAL(14,2) NOT NULL,
        returned_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
        KEY idx_pur (purchase_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}purchase_returns` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        memo_no VARCHAR(40) NOT NULL,
        purchase_id INT NOT NULL,
        total DECIMAL(14,2) NOT NULL,
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pr (memo_no)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}purchase_return_items` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        purchase_item_id INT NOT NULL,
        qty DECIMAL(14,3) NOT NULL,
        base_qty DECIMAL(14,3) NOT NULL,
        line_total DECIMAL(14,2) NOT NULL,
        KEY idx_ret (return_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}sales` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        memo_no VARCHAR(40) NOT NULL,
        party_id INT NOT NULL DEFAULT 0,
        account_id INT NOT NULL DEFAULT 0,
        location_id INT NOT NULL DEFAULT 1,
        channel VARCHAR(30) NOT NULL DEFAULT 'pos',
        booking_id INT NOT NULL DEFAULT 0,
        gross DECIMAL(14,2) NOT NULL DEFAULT 0,
        discount DECIMAL(14,2) NOT NULL DEFAULT 0,
        net DECIMAL(14,2) NOT NULL DEFAULT 0,
        paid DECIMAL(14,2) NOT NULL DEFAULT 0,
        due DECIMAL(14,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'posted',
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_smemo (memo_no),
        KEY idx_party (party_id),
        KEY idx_date (created_at)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}sale_items` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        variant_id INT NOT NULL DEFAULT 0,
        batch_id INT NOT NULL DEFAULT 0,
        unit_id INT NOT NULL DEFAULT 0,
        qty DECIMAL(14,3) NOT NULL,
        factor DECIMAL(12,4) NOT NULL DEFAULT 1,
        base_qty DECIMAL(14,3) NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        cost_price DECIMAL(12,4) NOT NULL DEFAULT 0,
        line_total DECIMAL(14,2) NOT NULL,
        returned_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
        KEY idx_sale (sale_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}sale_returns` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        memo_no VARCHAR(40) NOT NULL,
        sale_id INT NOT NULL,
        total DECIMAL(14,2) NOT NULL,
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_sr (memo_no)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}sale_return_items` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        sale_item_id INT NOT NULL,
        qty DECIMAL(14,3) NOT NULL,
        base_qty DECIMAL(14,3) NOT NULL,
        line_total DECIMAL(14,2) NOT NULL,
        KEY idx_ret (return_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}expenses` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        account_id INT NOT NULL,
        spent_on DATE NOT NULL,
        note VARCHAR(255) NOT NULL DEFAULT '',
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_date (spent_on)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}incomes` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        account_id INT NOT NULL,
        received_on DATE NOT NULL,
        note VARCHAR(255) NOT NULL DEFAULT '',
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_date (received_on)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}transfers` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        from_location_id INT NOT NULL,
        to_location_id INT NOT NULL,
        qty DECIMAL(14,3) NOT NULL,
        note VARCHAR(180) NOT NULL DEFAULT '',
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_prod (product_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}commissions` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        sale_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_emp (employee_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}files` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folder VARCHAR(40) NOT NULL,
        stored_name VARCHAR(80) NOT NULL,
        original_name VARCHAR(180) NOT NULL,
        mime VARCHAR(80) NOT NULL,
        size_bytes INT NOT NULL DEFAULT 0,
        entity VARCHAR(40) NOT NULL DEFAULT '',
        entity_id INT NOT NULL DEFAULT 0,
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        KEY idx_folder (folder)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}audit_log` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 0,
        action VARCHAR(40) NOT NULL,
        entity VARCHAR(40) NOT NULL DEFAULT '',
        entity_id INT NOT NULL DEFAULT 0,
        detail VARCHAR(240) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_action (action),
        KEY idx_created (created_at)
    ) $e";
    if (vx_feature('prescription')) {
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}doctors` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        phone VARCHAR(40) NOT NULL DEFAULT '',
        specialty VARCHAR(80) NOT NULL DEFAULT '',
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}prescriptions` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        doctor_id INT NOT NULL DEFAULT 0,
        patient_name VARCHAR(160) NOT NULL DEFAULT '',
        note VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sale (sale_id)
    ) $e";
    }
    if (vx_feature('rooms')) {
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}room_types` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        base_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}rooms` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_no VARCHAR(20) NOT NULL,
        room_type_id INT NOT NULL,
        floor_no VARCHAR(20) NOT NULL DEFAULT '',
        rate DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'vacant',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        UNIQUE KEY uq_room (room_no)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}bookings` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) NOT NULL,
        party_id INT NOT NULL,
        room_id INT NOT NULL,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        nights INT NOT NULL DEFAULT 1,
        rate DECIMAL(12,2) NOT NULL DEFAULT 0,
        total DECIMAL(14,2) NOT NULL DEFAULT 0,
        paid DECIMAL(14,2) NOT NULL DEFAULT 0,
        due DECIMAL(14,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'booked',
        sale_id INT NOT NULL DEFAULT 0,
        note VARCHAR(255) NOT NULL DEFAULT '',
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_code (code),
        KEY idx_room (room_id),
        KEY idx_party (party_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}housekeeping` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        task VARCHAR(120) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        note VARCHAR(180) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_room (room_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}rest_tables` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(40) NOT NULL,
        seats INT NOT NULL DEFAULT 2,
        status VARCHAR(20) NOT NULL DEFAULT 'free',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_table (name)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}kots` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        table_id INT NOT NULL DEFAULT 0,
        booking_id INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        sale_id INT NOT NULL DEFAULT 0,
        note VARCHAR(180) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}kot_items` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kot_id INT NOT NULL,
        product_id INT NOT NULL,
        qty DECIMAL(12,3) NOT NULL,
        price DECIMAL(12,2) NOT NULL,
        KEY idx_kot (kot_id)
    ) $e";
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}laundry` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        party_id INT NOT NULL DEFAULT 0,
        room_id INT NOT NULL DEFAULT 0,
        item_name VARCHAR(120) NOT NULL,
        qty DECIMAL(12,3) NOT NULL DEFAULT 1,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        sale_id INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) $e";
    }
    return $sql;
}

function vx_perm_keys(): array
{
    return [
        'dashboard', 'parties', 'products', 'purchase', 'purchase_return', 'sale', 'sale_return',
        'stock', 'alerts', 'expenses', 'incomes', 'accounts', 'employees', 'reports', 'memo',
        'files', 'users', 'settings', 'audit', 'barcode', 'rooms', 'bookings', 'housekeeping',
        'laundry', 'restaurant', 'doctors', 'transfers',
    ];
}

function vx_install(string $businessName, string $adminPass): void
{
    foreach (vx_schema_sql() as $statement) {
        vx_db()->query($statement);
    }
    if (vx_one('SELECT id FROM ' . vx_t('settings') . ' WHERE id=1')) {
        return;
    }
    vx_insert(
        'INSERT INTO ' . vx_t('settings') . ' (id, business_name) VALUES (1,?)',
        's',
        [$businessName !== '' ? $businessName : (string) (vx_app()['name']['bn'] ?? 'Business')]
    );
    vx_insert('INSERT INTO ' . vx_t('accounts') . ' (name, kind, balance) VALUES (?,?,0)', 'ss', ['ক্যাশ', 'cash']);
    vx_insert('INSERT INTO ' . vx_t('accounts') . ' (name, kind, balance) VALUES (?,?,0)', 'ss', ['ব্যাংক', 'bank']);
    vx_insert('INSERT INTO ' . vx_t('locations') . ' (name) VALUES (?)', 's', ['দোকান']);
    vx_insert('INSERT INTO ' . vx_t('units') . ' (name, factor) VALUES (?,?)', 'sd', ['পিস', 1]);
    if (vx_feature('pack')) {
        vx_insert('INSERT INTO ' . vx_t('units') . ' (name, factor) VALUES (?,?)', 'sd', ['স্ট্রিপ', 10]);
        vx_insert('INSERT INTO ' . vx_t('units') . ' (name, factor) VALUES (?,?)', 'sd', ['বক্স', 100]);
    }
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    vx_insert(
        'INSERT INTO ' . vx_t('users') . ' (username, password_hash, name, role_slug) VALUES (?,?,?,?)',
        'ssss',
        ['admin', $hash, 'Admin', 'admin']
    );
    $manager = ['dashboard', 'parties', 'products', 'purchase', 'purchase_return', 'sale', 'sale_return', 'stock', 'alerts', 'expenses', 'incomes', 'accounts', 'employees', 'reports', 'memo', 'files', 'settings', 'audit', 'barcode', 'rooms', 'bookings', 'housekeeping', 'laundry', 'restaurant', 'doctors', 'transfers'];
    $cashier = ['dashboard', 'parties', 'sale', 'sale_return', 'memo', 'alerts', 'restaurant'];
    $store = ['dashboard', 'products', 'purchase', 'purchase_return', 'stock', 'alerts', 'parties', 'barcode', 'transfers'];
    foreach (['manager' => $manager, 'cashier' => $cashier, 'storekeeper' => $store] as $role => $keys) {
        foreach ($keys as $key) {
            vx_insert('INSERT IGNORE INTO ' . vx_t('role_permissions') . ' (role_slug, perm_key) VALUES (?,?)', 'ss', [$role, $key]);
        }
    }
    if (vx_feature('rooms')) {
        vx_insert(
            'INSERT INTO ' . vx_t('products') . ' (sku, name, kind, track_batch, retail_price) VALUES (?,?,?,?,0)',
            'sssi',
            ['ROOM', 'রুম ভাড়া', 'service', 0]
        );
        vx_insert(
            'INSERT INTO ' . vx_t('products') . ' (sku, name, kind, track_batch, retail_price) VALUES (?,?,?,?,0)',
            'sssi',
            ['LAUNDRY', 'লন্ড্রি', 'service', 0]
        );
        $p = vx_prefix();
        vx_ensure_fk('fk_room_type', "ALTER TABLE `{$p}rooms` ADD CONSTRAINT `{$p}fk_room_type` FOREIGN KEY (room_type_id) REFERENCES `{$p}room_types` (id)");
        vx_ensure_fk('fk_book_room', "ALTER TABLE `{$p}bookings` ADD CONSTRAINT `{$p}fk_book_room` FOREIGN KEY (room_id) REFERENCES `{$p}rooms` (id)");
        vx_ensure_fk('fk_book_party', "ALTER TABLE `{$p}bookings` ADD CONSTRAINT `{$p}fk_book_party` FOREIGN KEY (party_id) REFERENCES `{$p}parties` (id)");
        vx_ensure_fk('fk_kot_item', "ALTER TABLE `{$p}kot_items` ADD CONSTRAINT `{$p}fk_kot_item` FOREIGN KEY (kot_id) REFERENCES `{$p}kots` (id)");
    }
    $p = vx_prefix();
    vx_ensure_fk('fk_pi_pur', "ALTER TABLE `{$p}purchase_items` ADD CONSTRAINT `{$p}fk_pi_pur` FOREIGN KEY (purchase_id) REFERENCES `{$p}purchases` (id)");
    vx_ensure_fk('fk_si_sale', "ALTER TABLE `{$p}sale_items` ADD CONSTRAINT `{$p}fk_si_sale` FOREIGN KEY (sale_id) REFERENCES `{$p}sales` (id)");
    vx_ensure_fk('fk_sri', "ALTER TABLE `{$p}sale_return_items` ADD CONSTRAINT `{$p}fk_sri` FOREIGN KEY (return_id) REFERENCES `{$p}sale_returns` (id)");
    vx_ensure_fk('fk_pri', "ALTER TABLE `{$p}purchase_return_items` ADD CONSTRAINT `{$p}fk_pri` FOREIGN KEY (return_id) REFERENCES `{$p}purchase_returns` (id)");
    vx_audit('install', 'settings', 1, vx_app()['id'] ?? '');
}

function vx_ensure_fk(string $name, string $alter): void
{
    $full = vx_prefix() . $name;
    $row = vx_one(
        'SELECT CONSTRAINT_NAME AS c FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?',
        's',
        [$full]
    );
    if ($row) {
        return;
    }
    vx_db()->query($alter);
}
