<?php
require_once __DIR__ . '/../../../../includes/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$kind = (string)($_GET['kind'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || !in_array($kind, ['customer', 'product'], true)) {
    echo json_encode(['ok' => true, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $q . '%';

function ame_ret_bind(mysqli_stmt $stmt, string $types, array &$vals): void
{
    $refs = [];
    $refs[] = &$types;
    foreach ($vals as $k => $v) {
        $refs[] = &$vals[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function ame_ret_cols(mysqli $db, string $table): array
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
    if ($kind === 'customer') {
        $cols = ame_ret_cols($db, 'customers');
        $sel = ['id', 'name'];
        foreach (['customer_code', 'phone', 'alt_phone', 'address', 'receivable_balance'] as $col) {
            if (!empty($cols[$col])) {
                $sel[] = $col;
            }
        }
        $where = ['name LIKE ?'];
        $types = 's';
        $vals = [$like];
        foreach (['customer_code', 'phone', 'alt_phone', 'nid'] as $col) {
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
        $cust = !empty($cols['is_customer']) ? ' AND is_customer = 1' : '';
        $lim = min(80, max(15, (int)($_GET['limit'] ?? 40)));
        $sql = 'SELECT ' . implode(', ', $sel) . ' FROM customers WHERE (' . implode(' OR ', $where) . ')' . $cust . ' ORDER BY name ASC LIMIT ' . $lim;
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('customer search');
        }
        ame_ret_bind($stmt, $types, $vals);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $bal = round((float)($row['receivable_balance'] ?? 0), 2);
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => trim((string)($row['name'] ?? '')) !== '' ? (string)$row['name'] : 'নাম নেই',
                'code' => (string)($row['customer_code'] ?? ''),
                'phone' => (string)($row['phone'] ?? ''),
                'alt' => (string)($row['alt_phone'] ?? ''),
                'address' => (string)($row['address'] ?? ''),
                'balance' => $bal,
            ];
        }
        $stmt->close();
        echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cols = ame_ret_cols($db, 'products');
    $sel = ['id', 'name'];
    foreach (['sku', 'barcode', 'product_code', 'sale_price', 'wholesale_price', 'dealer_price', 'stock', 'unit'] as $col) {
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
    $lim = min(80, max(15, (int)($_GET['limit'] ?? 40)));
    $sql = 'SELECT ' . implode(', ', $sel) . ' FROM products WHERE (' . implode(' OR ', $where) . ')' . $status . ' ORDER BY name ASC LIMIT ' . $lim;
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('product search');
    }
    ame_ret_bind($stmt, $types, $vals);
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
    error_log('return lookup: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'rows' => []], JSON_UNESCAPED_UNICODE);
}
