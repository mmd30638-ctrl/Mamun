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

if ($customerId <= 0 && $q !== '') {
    $like = '%' . $q . '%';
    $sql = 'SELECT id FROM customers WHERE is_customer = 1 AND (name LIKE ? OR phone LIKE ? OR customer_code LIKE ?)';
    $types = 'sss';
    $vals = [$like, $like, $like];
    if (ctype_digit($q)) {
        $sql .= ' OR id = ?';
        $types .= 'i';
        $vals[] = (int)$q;
    }
    $sql .= ' ORDER BY name ASC LIMIT 1';
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $customerId = (int)($row['id'] ?? 0);
    }
}

if ($customerId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'রেজিস্টার ক্রেতা নির্বাচন করুন বা নাম দিয়ে খুঁজুন।',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = ame_return_v2_list_customer_memos($db, $customerId);
if ($data['customer'] === []) {
    echo json_encode(['status' => 'error', 'message' => 'ক্রেতা পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'status' => 'success',
    'customer' => $data['customer'],
    'balance' => $data['balance'],
    'memos' => $data['memos'],
], JSON_UNESCAPED_UNICODE);