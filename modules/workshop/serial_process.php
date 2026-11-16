<?php
require_once __DIR__ . '/_boot.php';

require_post();
csrf_verify();

$serial = trim((string)($_POST['serial_no'] ?? ''));
if ($serial === '') {
    header('Location: serials.php?err=' . rawurlencode('সিরিয়াল নম্বর লাগবে।'));
    exit;
}

$product_id = (int)($_POST['product_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? 'in_stock'));
$allowed = ['in_stock', 'sold', 'on_job', 'returned', 'scrapped'];
if (!in_array($status, $allowed, true)) {
    $status = 'in_stock';
}
$months = max(0, (int)($_POST['warranty_months'] ?? 0));
$notes = trim((string)($_POST['notes'] ?? ''));
$expiry = null;
if ($months > 0) {
    $expiry = date('Y-m-d', strtotime('+' . $months . ' months'));
}
$store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;

$dup = $db->prepare('SELECT id FROM product_serials WHERE serial_no = ?');
$dup->bind_param('s', $serial);
$dup->execute();
if ($dup->get_result()->num_rows > 0) {
    $dup->close();
    header('Location: serials.php?err=' . rawurlencode('এই সিরিয়াল আগে থেকেই খাতায় আছে।'));
    exit;
}
$dup->close();

ame_workshop_touch_serial($db, $serial, [
    'product_id' => $product_id,
    'status' => $status,
    'warranty_months' => $months,
    'warranty_expiry' => $expiry,
    'notes' => $notes,
    'store_id' => $store_id,
]);

header('Location: serials.php?ok=1');
exit;
