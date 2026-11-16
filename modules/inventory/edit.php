<?php
require_once '../../includes/config.php';
require_once '../../includes/inventory_functions.php';
require_once '../../includes/lang_helper.php';

try {
    ensure_inventory_schema($db);
} catch (Throwable $e) {
    error_log('product edit schema: ' . $e->getMessage());
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$p) {
    include '../../partials/header.php';
    echo '<div class="card" style="margin:16px;padding:20px">পণ্য খুঁজে পাওয়া যায়নি। <a href="list.php">স্টক তালিকা</a></div>';
    include '../../partials/footer.php';
    exit;
}

if (function_exists('ame_store_stock_qty') && current_store_id() > 0) {
    $p['stock'] = ame_store_stock_qty($db, current_store_id(), $id);
}

$is_edit = true;
include __DIR__ . '/add_product_boot.php';
include '../../partials/header.php';
include __DIR__ . '/add_product_style.php';
include __DIR__ . '/add_product_form.php';
include __DIR__ . '/add_product_script.php';
include '../../partials/footer.php';
