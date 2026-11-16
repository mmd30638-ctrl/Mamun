<?php
require_once __DIR__ . '/../../../includes/config.php';
admin_only();

header('Content-Type: application/json; charset=UTF-8');

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    echo '[]';
    exit;
}

$cols = [];
$colRes = @$db->query('SHOW COLUMNS FROM products');
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        $cols[] = (string)($c['Field'] ?? '');
    }
}
$has = static function (string $name) use ($cols): bool {
    return in_array($name, $cols, true);
};

$select = ['id', 'name'];
foreach (['sku', 'oem_code', 'supplier_code', 'barcode', 'purchase_price', 'wholesale_price', 'dealer_price', 'sale_price', 'stock'] as $col) {
    if ($has($col)) {
        $select[] = $col;
    }
}

$where = ['name LIKE ?'];
$types = 's';
$like = '%' . $q . '%';
$params = [$like];
foreach (['sku', 'oem_code', 'supplier_code', 'barcode'] as $col) {
    if ($has($col)) {
        $where[] = $col . ' LIKE ?';
        $types .= 's';
        $params[] = $like;
    }
}
$where[] = 'CAST(id AS CHAR) = ?';
$types .= 's';
$params[] = $q;

$statusSql = $has('status')
    ? ' AND ' . ame_product_option_sql('status')
    : '';

$orderParts = [];
foreach (['sku', 'oem_code', 'supplier_code', 'barcode'] as $col) {
    if ($has($col)) {
        $orderParts[] = $col . ' = ?';
        $types .= 's';
        $params[] = $q;
    }
}
$orderParts[] = 'CAST(id AS CHAR) = ?';
$types .= 's';
$params[] = $q;

$case = 'CASE';
foreach ($orderParts as $i => $expr) {
    $case .= ' WHEN ' . $expr . ' THEN ' . $i;
}
$case .= ' ELSE 90 END, name ASC';

$sql = 'SELECT ' . implode(', ', $select)
    . ' FROM products WHERE (' . implode(' OR ', $where) . ')'
    . $statusSql
    . ' ORDER BY ' . $case
    . ' LIMIT 2000';

$stmt = $db->prepare($sql);
if (!$stmt) {
    echo '[]';
    exit;
}

$bind = [];
$bind[] = &$types;
foreach ($params as $i => $value) {
    $bind[] = &$params[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bind);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'sku' => (string)($row['sku'] ?? ''),
            'oem_code' => (string)($row['oem_code'] ?? ''),
            'supplier_code' => (string)($row['supplier_code'] ?? ''),
            'barcode' => (string)($row['barcode'] ?? ''),
            'purchase_price' => (float)($row['purchase_price'] ?? 0),
            'wholesale_price' => (float)($row['wholesale_price'] ?? 0),
            'dealer_price' => (float)($row['dealer_price'] ?? 0),
            'sale_price' => (float)($row['sale_price'] ?? 0),
            'stock' => $row['stock'] ?? 0,
        ];
    }
}
$stmt->close();

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
