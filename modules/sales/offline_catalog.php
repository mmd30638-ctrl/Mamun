<?php
/**
 * অফলাইন ক্যাটালগ — পণ্য, কাস্টমার, অ্যাকাউন্ট ফোনে সেভ করার জন্য।
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/account_functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, max-age=60');

$empty = static function () {
    echo json_encode([
        'status' => 'success',
        'products' => [],
        'customers' => [],
        'accounts' => [],
        'suppliers' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
};

if (!isset($db) || !($db instanceof mysqli)) {
    $empty();
}

$productCols = [];
$colRes = @$db->query('SHOW COLUMNS FROM products');
if ($colRes) {
    while ($colRow = $colRes->fetch_assoc()) {
        $productCols[(string)$colRow['Field']] = true;
    }
    $colRes->free();
}

$products = [];
$store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
$select = ['p.id', 'p.name'];
foreach (['sku', 'oem_code', 'supplier_code', 'barcode', 'sale_price', 'wholesale_price', 'dealer_price', 'purchase_price', 'stock', 'is_old_battery'] as $col) {
    if (!empty($productCols[$col])) {
        $select[] = 'p.' . $col;
    }
}
$where = '1=1';
if (!empty($productCols['status'])) {
    $where = ame_product_option_sql('p.status');
}
$on_hand_sql = !empty($productCols['stock']) ? 'p.stock' : '0';
$stock_join = '';
if ($store_id > 0 && !empty($productCols['stock'])) {
    $on_hand_sql = 'COALESCE(ss.qty, p.stock)';
    $stock_join = ' LEFT JOIN store_stock ss ON ss.product_id = p.id AND ss.store_id = ' . $store_id . ' ';
}
$sql = 'SELECT ' . implode(', ', $select) . ', ' . $on_hand_sql . ' AS ame_on_hand FROM products p' . $stock_join . ' WHERE ' . $where . ' ORDER BY p.name ASC LIMIT 4000';
$res = @$db->query($sql);
if (!$res && $stock_join !== '') {
    $fallback_on_hand = !empty($productCols['stock']) ? 'p.stock' : '0';
    $sql = 'SELECT ' . implode(', ', $select) . ', ' . $fallback_on_hand . ' AS ame_on_hand FROM products p WHERE ' . $where . ' ORDER BY p.name ASC LIMIT 4000';
    $res = @$db->query($sql);
}
$reserved_map = [];
if ($res && function_exists('ame_workshop_reserved_map')) {
    try {
        $reserved_map = ame_workshop_reserved_map($db, $store_id);
    } catch (Throwable $reserveError) {
        $reserved_map = [];
    }
}
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $on_hand = (float)($row['ame_on_hand'] ?? $row['stock'] ?? 0);
        $reserved = (float)($reserved_map[(int)$row['id']] ?? 0);
        $products[] = [
            'id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? ''),
            'sku' => (string)($row['sku'] ?? ''),
            'oem_code' => (string)($row['oem_code'] ?? ''),
            'supplier_code' => (string)($row['supplier_code'] ?? ''),
            'barcode' => (string)($row['barcode'] ?? ''),
            'sale_price' => (float)($row['sale_price'] ?? 0),
            'wholesale_price' => (float)($row['wholesale_price'] ?? 0),
            'dealer_price' => (float)($row['dealer_price'] ?? 0),
            'purchase_price' => (float)($row['purchase_price'] ?? 0),
            'stock' => ame_stock_sellable($on_hand, $reserved),
            'is_old_battery' => (int)($row['is_old_battery'] ?? 0) === 1,
            'unit' => '',
        ];
    }
}

$customers = [];
$off_pick = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1';
$cres = @$db->query("SELECT id, customer_code, name, phone, receivable_balance, next_due_date FROM customers WHERE ({$off_pick}) AND (is_customer = 1 OR is_customer IS NULL) ORDER BY name ASC LIMIT 1000");
if (!$cres) {
    $cres = @$db->query("SELECT id, name, phone FROM customers WHERE {$off_pick} ORDER BY name ASC LIMIT 1000");
}
if ($cres) {
    while ($row = $cres->fetch_assoc()) {
        $customers[] = [
            'id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? ''),
            'code' => (string)($row['customer_code'] ?? ''),
            'phone' => (string)($row['phone'] ?? ''),
            'balance' => (float)($row['receivable_balance'] ?? 0),
            'due' => (string)($row['next_due_date'] ?? ''),
        ];
    }
}

$accounts = [];
if (!function_exists('ame_active_accounts')) {
    require_once __DIR__ . '/../../includes/account_functions.php';
}
foreach (ame_active_accounts($db) as $row) {
    $accounts[] = [
        'id' => (int)$row['id'],
        'name' => (string)($row['account_name'] ?? ''),
        'balance' => ame_balance_number_masked($row['balance'] ?? 0),
    ];
}

$suppliers = [];
$sres = @$db->query("SELECT id, name, phone, total_due FROM suppliers WHERE purged_at IS NULL ORDER BY name ASC LIMIT 500");
if (!$sres) {
    $sres = @$db->query("SELECT id, name, phone, total_due FROM suppliers ORDER BY name ASC LIMIT 500");
}
if (!$sres) {
    $sres = @$db->query("SELECT id, name, phone FROM customers WHERE is_supplier = 1 ORDER BY name ASC LIMIT 500");
}
if ($sres) {
    while ($row = $sres->fetch_assoc()) {
        $suppliers[] = [
            'id' => (int)$row['id'],
            'name' => (string)($row['name'] ?? ''),
            'phone' => (string)($row['phone'] ?? ''),
            'due' => (float)($row['total_due'] ?? 0),
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'cached_at' => date('c'),
    'products' => $products,
    'customers' => $customers,
    'accounts' => $accounts,
    'suppliers' => $suppliers,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
