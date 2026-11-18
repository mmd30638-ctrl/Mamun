<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/return_v2_functions.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'লগইন প্রয়োজন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

ensure_return_v2_schema($db);

$customerId = (int)($_GET['customer_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

if ($customerId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'আগে রেজিস্টার ক্রেতা বেছে নিন (নাম/ফোন দিয়ে খুঁজুন)।',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$custStmt = $db->prepare('SELECT id, name, phone FROM customers WHERE id = ? AND is_customer = 1 LIMIT 1');
if (!$custStmt) {
    echo json_encode(['status' => 'error', 'message' => 'ডাটাবেস ত্রুটি।'], JSON_UNESCAPED_UNICODE);
    exit;
}
$custStmt->bind_param('i', $customerId);
$custStmt->execute();
$cust = $custStmt->get_result()->fetch_assoc();
$custStmt->close();
if (!$cust) {
    echo json_encode(['status' => 'error', 'message' => 'ক্রেতা পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lines = ame_return_v2_customer_product_lines($db, $customerId, $q, 80);

echo json_encode([
    'status' => 'success',
    'customer' => [
        'id' => (int)$cust['id'],
        'name' => (string)$cust['name'],
        'phone' => (string)($cust['phone'] ?? ''),
    ],
    'balance' => ame_return_customer_due($db, $customerId),
    'lines' => $lines,
    'count' => count($lines),
], JSON_UNESCAPED_UNICODE);