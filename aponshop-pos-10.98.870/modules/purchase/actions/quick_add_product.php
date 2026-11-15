<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/product_code_functions.php';
require_once __DIR__ . '/../../../includes/lang_helper.php';
admin_only();

header('Content-Type: application/json; charset=UTF-8');

$is_english = current_lang() === 'en';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => ame_l('অনুমোদিত অনুরোধ নয়।', 'Invalid request method.')], JSON_UNESCAPED_UNICODE);
    exit;
}
csrf_verify();

$name = trim($_POST['name'] ?? '');
$purchase_price = round((float)($_POST['purchase_price'] ?? 0), 2);
$wholesale_price = round((float)($_POST['wholesale_price'] ?? 0), 2);
$dealer_price = round((float)($_POST['dealer_price'] ?? 0), 2);
$sale_price = round((float)($_POST['sale_price'] ?? 0), 2);
$min_stock = round((float)($_POST['min_stock'] ?? 0), 2);

if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ পণ্যের নাম দিন।', '⚠️ Enter the product name.')], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($purchase_price <= 0) {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ ক্রয় মূল্য সঠিকভাবে লিখুন।', '⚠️ Enter a valid purchase price.')], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($wholesale_price < 0 || $dealer_price < 0 || $sale_price < 0 || $min_stock < 0) {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ মূল্য/সীমা শূন্যের কম হতে পারবে না।', '⚠️ Price/limit cannot be less than zero.')], JSON_UNESCAPED_UNICODE);
    exit;
}
foreach ([
    ($is_english ? 'Dealer' : 'ডিলার') => $dealer_price,
    ($is_english ? 'Wholesale' : 'পাইকারি') => $wholesale_price,
] as $label => $val) {
    if ($val > 0 && $val < $purchase_price) {
        echo json_encode([
            'status' => 'error',
            'message' => '⚠️ ' . $label . ame_l(' মূল্য ক্রয় মূল্যের চেয়ে কম হতে পারবে না।', ' price cannot be less than the purchase price.'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
if ($sale_price < $purchase_price) {
    echo json_encode([
        'status' => 'error',
        'message' => ame_l('⚠️ খুচরা মূল্য ক্রয় মূল্যের চেয়ে কম হতে পারবে না।', '⚠️ Retail price cannot be less than the purchase price.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// একই নামের পণ্য আগে থেকে থাকলে সতর্ক করা (ভুলবশত ডুপ্লিকেট তৈরি ঠেকাতে)
$check = $db->prepare("SELECT id FROM products WHERE name = ? AND status = 'active' LIMIT 1");
$check->bind_param('s', $name);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ এই নামে একটা পণ্য ইতিমধ্যে আছে। তালিকা থেকে সার্চ করে বেছে নিন।', '⚠️ A product already exists with this name. Search the list and pick it.')], JSON_UNESCAPED_UNICODE);
    exit;
}

$transaction_started = false;

try {
    $db->begin_transaction();
    $transaction_started = true;

    $temp_code = 'TMP-' . strtoupper(bin2hex(random_bytes(8)));

    $stmt = $db->prepare(
        "INSERT INTO products (sku, name, category_id, brand_id, unit_id, purchase_price, wholesale_price, dealer_price, sale_price, stock, min_stock, status)
         VALUES (?, ?, 0, 0, 0, ?, ?, ?, ?, 0, ?, 'active')"
    );
    // টাইপ: s(sku) s(name) d(purchase) d(wholesale) d(dealer) d(sale) d(min_stock) = "ssddddd" (৭টি)
    $stmt->bind_param('ssddddd', $temp_code, $name, $purchase_price, $wholesale_price, $dealer_price, $sale_price, $min_stock);
    $stmt->execute();
    $product_id = $stmt->insert_id;
    $stmt->close();

    $final_code = generate_product_code($db, $product_id);
    $upd = $db->prepare("UPDATE products SET sku = ? WHERE id = ?");
    $upd->bind_param('si', $final_code, $product_id);
    $upd->execute();

    log_activity('Quick Product Added', 'Products', $name . ' (ক্রয় পেজ থেকে)');

    $db->commit();
    $transaction_started = false;

    echo json_encode([
        'status' => 'success',
        'product' => [
            'id' => $product_id,
            'name' => $name,
            'purchase_price' => $purchase_price,
            'wholesale_price' => $wholesale_price,
            'dealer_price' => $dealer_price,
            'sale_price' => $sale_price,
            'stock' => 0,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Quick add product failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => ame_l('⚠️ পণ্য যোগ করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।', '⚠️ Could not add the product. Please try again.')], JSON_UNESCAPED_UNICODE);
}
