<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

header('Content-Type: application/json; charset=UTF-8');

$invoice_no = trim($_GET['invoice_no'] ?? '');

if ($invoice_no === '') {
    echo json_encode(['status' => 'error', 'message' => 'ইনভয়েস বা মেমো নম্বর দিন।']);
    exit;
}

$sale = ame_return_v2_resolve_sale_by_reference($db, $invoice_no);

if (!$sale) {
    echo json_encode([
        'status' => 'error',
        'message' => 'এই নম্বরে কোনো সম্পন্ন বিক্রয় মেমো পাওয়া যায়নি। (মেমো #, বিক্রয় নম্বর বা INV-... দিতে পারেন)',
    ]);
    exit;
}

$items_stmt = $db->prepare(
    "SELECT si.product_id, COALESCE(NULLIF(TRIM(si.item_name), ''), p.name) AS product_name,
            p.sale_price AS retail_price, p.wholesale_price, p.dealer_price,
            si.qty AS sold_qty, si.sale_price AS unit_price
     FROM sale_items si
     LEFT JOIN products p ON si.product_id = p.id
     WHERE si.sale_id = ?"
);
$items_stmt->bind_param('i', $sale['id']);
$items_stmt->execute();
$item_rows = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$items = [];
foreach ($item_rows as $it) {
    $productId = (int)($it['product_id'] ?? 0);
    if ($productId <= 0) {
        continue;
    }
    $already_returned = get_already_returned_qty($db, (int)$sale['id'], $productId);
    $items[] = [
        'product_id' => $productId,
        'product_name' => (string)($it['product_name'] ?: 'পণ্য #' . $productId),
        'sold_qty' => (float)$it['sold_qty'],
        'already_returned' => $already_returned,
        'unit_price' => (float)$it['unit_price'],
        'retail_price' => (float)($it['retail_price'] ?? $it['unit_price']),
        'wholesale_price' => (float)($it['wholesale_price'] ?? 0),
        'dealer_price' => (float)($it['dealer_price'] ?? 0),
    ];
}

if ($items === []) {
    echo json_encode([
        'status' => 'error',
        'message' => 'এই মেমোতে ফেরতযোগ্য স্টক-যুক্ত পণ্য পাওয়া যায়নি।',
    ]);
    exit;
}

$displayInvoice = trim((string)($sale['invoice_number'] ?? ''));
if ($displayInvoice === '') {
    $displayInvoice = 'INV-' . (int)$sale['id'];
}

echo json_encode([
    'status' => 'success',
    'sale' => [
        'id' => (int)$sale['id'],
        'invoice_number' => $displayInvoice,
        'sale_date' => date('d-m-Y', strtotime($sale['sale_date'] ?? 'now')),
        'customer_id' => $sale['customer_id'] ? (int)$sale['customer_id'] : null,
        'customer_name' => $sale['customer_name'] ?: 'Retail Customer',
        'customer_code' => $sale['customer_code'] ?? '',
        'customer_phone' => $sale['customer_phone'] ?? '',
        'customer_balance' => (float)($sale['receivable_balance'] ?? 0),
        'sales_type' => (strtolower((string)$sale['customer_type']) === 'wholesale') ? 'wholesale' : 'retail',
    ],
    'items' => $items,
], JSON_UNESCAPED_UNICODE);