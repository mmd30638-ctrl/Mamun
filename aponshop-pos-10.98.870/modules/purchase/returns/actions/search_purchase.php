<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/purchase_return_functions.php';
admin_only();
ensure_purchase_return_schema($db);

header('Content-Type: application/json; charset=UTF-8');

$memo_no = trim($_GET['memo_no'] ?? '');

if ($memo_no === '') {
    echo json_encode(['status' => 'error', 'message' => 'মেমো নম্বর দিন।']);
    exit;
}

// প্রথমে এই মেমো নম্বরের সাপ্লায়ার বের করা (একই মেমো নম্বর তত্ত্বগতভাবে ভিন্ন সাপ্লায়ারে থাকতে পারে,
// তাই প্রথম মিলে যাওয়া সাপ্লায়ারটাই ব্যবহার করা হচ্ছে)
$sup_stmt = $db->prepare(
    "SELECT p.supplier_id, s.name as supplier_name, s.phone as supplier_phone, s.total_due as supplier_due
     FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id
     WHERE p.memo_no = ? LIMIT 1"
);
$sup_stmt->bind_param('s', $memo_no);
$sup_stmt->execute();
$sup = $sup_stmt->get_result()->fetch_assoc();

if (!$sup) {
    echo json_encode(['status' => 'error', 'message' => 'এই মেমো নম্বরে কোনো ক্রয় পাওয়া যায়নি।']);
    exit;
}

$supplier_id = $sup['supplier_id'];

$items_stmt = $db->prepare(
    "SELECT pu.product_id, p.name as product_name, pu.qty as purchased_qty, pu.purchase_price as unit_price,
            p.purchase_price, p.sale_price, p.wholesale_price, p.dealer_price
     FROM purchases pu
     JOIN products p ON pu.product_id = p.id
     WHERE pu.memo_no = ? AND pu.supplier_id <=> ?"
);
$items_stmt->bind_param('si', $memo_no, $supplier_id);
$items_stmt->execute();
$item_rows = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$items = [];
foreach ($item_rows as $it) {
    $already_returned = get_already_purchase_returned_qty($db, $memo_no, (int)$supplier_id, (int)$it['product_id']);
    $items[] = [
        'product_id' => (int)$it['product_id'],
        'product_name' => $it['product_name'],
        'purchased_qty' => (float)$it['purchased_qty'],
        'already_returned' => $already_returned,
        'unit_price' => (float)$it['unit_price'],
        'purchase_price' => (float)($it['purchase_price'] ?? $it['unit_price']),
        'sale_price' => (float)($it['sale_price'] ?? 0),
        'wholesale_price' => (float)($it['wholesale_price'] ?? 0),
        'dealer_price' => (float)($it['dealer_price'] ?? 0),
    ];
}

echo json_encode([
    'status' => 'success',
    'memo_no' => $memo_no,
    'supplier_id' => $supplier_id ? (int)$supplier_id : null,
    'supplier_name' => $sup['supplier_name'] ?: 'অজানা সাপ্লায়ার',
    'supplier_phone' => (string)($sup['supplier_phone'] ?? ''),
    'supplier_due' => round((float)($sup['supplier_due'] ?? 0), 2),
    'items' => $items,
], JSON_UNESCAPED_UNICODE);
