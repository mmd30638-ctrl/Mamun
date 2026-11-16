<?php
/* 10.98.693 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
require_once __DIR__ . '/../customer_identity.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$fail = static function (string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $fail('শুধু ফর্ম থেকে সই বাতিল করা যায়।', 405);
}
if (empty($_SESSION['user_id'])) {
    $fail('আবার লগইন করুন।', 401);
}
$posted = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || $posted === '' || !hash_equals((string)$_SESSION['csrf_token'], $posted)) {
    $fail('নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।', 403);
}

ensure_customer_tables($db);
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $fail('কাস্টমার পাওয়া যায়নি।');
}

$find = $db->prepare('SELECT agreement_signature FROM customers WHERE id = ? LIMIT 1');
$find->bind_param('i', $id);
$find->execute();
$row = $find->get_result()->fetch_assoc();
$find->close();
if (!$row) {
    $fail('কাস্টমার পাওয়া যায়নি।', 404);
}

ame_customer_drop_signature_file($row['agreement_signature'] ?? '');
$up = $db->prepare('UPDATE customers SET agreement_signature = NULL, agreement_signed_at = NULL WHERE id = ?');
$up->bind_param('i', $id);
$up->execute();
$up->close();

echo json_encode(['ok' => true, 'message' => 'পূর্বের সই বাতিল হয়েছে। এখন নতুন সই দিতে পারেন।'], JSON_UNESCAPED_UNICODE);
