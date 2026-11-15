<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/purchase_return_functions.php';
admin_only();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$kind = (string)($_GET['kind'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || !in_array($kind, ['supplier', 'product'], true)) {
    echo json_encode(['ok' => true, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $q . '%';

function ame_pr_bind(mysqli_stmt $stmt, string $types, array &$vals): void
{
    $refs = [];
    $refs[] = &$types;
    foreach ($vals as $k => $v) {
        $refs[] = &$vals[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function ame_pr_cols(mysqli $db, string $table): array
{
    $cols = [];
    $res = @$db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[(string)$row['Field']] = true;
        }
    }
    return $cols;
}

try {
    if ($kind === 'supplier') {
        $cols = ame_pr_cols($db, 'suppliers');
        $sel = ['id', 'name'];
        foreach (['phone', 'alt_phone', 'address', 'party_code', 'total_due'] as $col) {
            if (!empty($cols[$col])) {
                $sel[] = $col;
            }
        }
        $where = ['name LIKE ?'];
        $types = 's';
        $vals = [$like];
        foreach (['phone', 'alt_phone', 'party_code'] as $col) {
            if (!empty($cols[$col])) {
                $where[] = $col . ' LIKE ?';
                $types .= 's';
                $vals[] = $like;
            }
        }
        if (ctype_digit($q)) {
            $where[] = 'id = ?';
            $types .= 'i';
            $vals[] = (int)$q;
        }
        $live = '';
        if (!function_exists('ame_supplier_live_sql')) {
            require_once dirname(__DIR__, 4) . '/includes/supplier_functions.php';
        }
        if (function_exists('ame_supplier_live_sql')) {
            $live = ' AND ' . ame_supplier_live_sql($db);
        }
        $status = !empty($cols['status']) ? " AND status = 'active'" : '';
        $sql = 'SELECT ' . implode(', ', $sel) . ' FROM suppliers WHERE (' . implode(' OR ', $where) . ')' . $status . $live . ' ORDER BY name ASC LIMIT 40';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('supplier search');
        }
        ame_pr_bind($stmt, $types, $vals);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => trim((string)($row['name'] ?? '')) !== '' ? (string)$row['name'] : 'নাম নেই',
                'code' => (string)($row['party_code'] ?? ''),
                'phone' => (string)($row['phone'] ?? ''),
                'alt' => (string)($row['alt_phone'] ?? ''),
                'address' => (string)($row['address'] ?? ''),
                'balance' => round((float)($row['total_due'] ?? 0), 2),
            ];
        }
        $stmt->close();
        echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cols = ame_pr_cols($db, 'products');
    $sel = ['id', 'name'];
    foreach (['sku', 'barcode', 'product_code', 'purchase_price', 'sale_price', 'wholesale_price', 'dealer_price', 'stock', 'unit'] as $col) {
        if (!empty($cols[$col])) {
            $sel[] = $col;
        }
    }
    $where = ['name LIKE ?'];
    $types = 's';
    $vals = [$like];
    foreach (['sku', 'barcode', 'product_code', 'oem_code'] as $col) {
        if (!empty($cols[$col])) {
            $where[] = $col . ' LIKE ?';
            $types .= 's';
            $vals[] = $like;
        }
    }
    if (ctype_digit($q)) {
        $where[] = 'id = ?';
        $types .= 'i';
        $vals[] = (int)$q;
    }
    $status = !empty($cols['status']) ? ' AND ' . ame_product_option_sql('status') : '';
    $sql = 'SELECT ' . implode(', ', $sel) . ' FROM products WHERE (' . implode(' OR ', $where) . ')' . $status . ' ORDER BY name ASC LIMIT 40';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('product search');
    }
    ame_pr_bind($stmt, $types, $vals);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $code = (string)($row['sku'] ?? '');
        if ($code === '' && !empty($row['barcode'])) {
            $code = (string)$row['barcode'];
        }
        if ($code === '' && !empty($row['product_code'])) {
            $code = (string)$row['product_code'];
        }
        $rows[] = [
            'id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? ''),
            'code' => $code,
            'barcode' => (string)($row['barcode'] ?? ''),
            'purchase_price' => round((float)($row['purchase_price'] ?? 0), 2),
            'sale_price' => round((float)($row['sale_price'] ?? 0), 2),
            'wholesale_price' => round((float)($row['wholesale_price'] ?? 0), 2),
            'dealer_price' => round((float)($row['dealer_price'] ?? 0), 2),
            'stock' => round((float)($row['stock'] ?? 0), 2),
            'unit' => (string)($row['unit'] ?? ''),
        ];
    }
    $stmt->close();
    echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('purchase return lookup: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'rows' => []], JSON_UNESCAPED_UNICODE);
}
