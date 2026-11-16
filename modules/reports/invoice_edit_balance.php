<?php
/* 10.98.703 — ইনভয়েস এডিট: ক্রেতার সাবেক বকেয়া (শুধু পড়া) */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/sales/sale_common.php';
require_once __DIR__ . '/../../includes/sales/sale_admin_edit.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id']) || !ame_sale_admin_is_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$sale_id = (int)($_GET['sale_id'] ?? 0);
$customer_id = (int)($_GET['customer_id'] ?? 0);
$sale = [];
if ($sale_id > 0) {
    $stmt = $db->prepare('SELECT id, customer_id, previous_customer_balance FROM sales WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $sale_id);
        $stmt->execute();
        $sale = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    }
}
echo json_encode(ame_sale_edit_balance_preview($db, $sale, $customer_id), JSON_UNESCAPED_UNICODE);
