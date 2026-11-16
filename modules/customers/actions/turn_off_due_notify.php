<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);
ame_ensure_due_watch_columns($db);

$wants_json = ((string)($_POST['ajax'] ?? '') === '1')
    || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

$fail = static function (string $message) use ($wants_json): void {
    if ($wants_json) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    die('❌ ' . $message);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $fail('Invalid request method');
}

$session_token = (string)($_SESSION['csrf_token'] ?? (function_exists('csrf_token') ? csrf_token() : ''));
$posted_token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    $fail('নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
}

$customer_id = (int)($_POST['customer_id'] ?? 0);
$payment_id = (int)($_POST['payment_id'] ?? 0);
if ($customer_id <= 0) {
    $fail('কাস্টমার সিলেক্ট করা নেই।');
}

$chk = $db->prepare('SELECT id FROM customers WHERE id = ? LIMIT 1');
if (!$chk) {
    $fail('কাস্টমার যাচাই করা যায়নি।');
}
$chk->bind_param('i', $customer_id);
$chk->execute();
$found = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$found) {
    $fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
}

if (!ame_customer_notify_off($db, $customer_id)) {
    $fail('নোটিফিকেশন বন্ধ করা যায়নি।');
}

if ($wants_json) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'message' => 'অনুমতি না দেওয়া পর্যন্ত এই কাস্টমার আর বকেয়া নোটিফিকেশনে আসবেন না।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$back = '../receipt.php';
if ($payment_id > 0) {
    $back .= '?payment_id=' . $payment_id . '&notify=off';
} else {
    $back = '../profile.php?id=' . $customer_id;
}
header('Location: ' . $back);
exit;
