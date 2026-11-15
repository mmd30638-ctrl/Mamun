<?php
/**
 * কাস্টমার প্রোফাইল — «সবসময় পাইকারি» টগল।
 * শুধু customers.always_wholesale আপডেট করে; ব্যালেন্স/লেজার/পেমেন্ট স্পর্শ করে না।
 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'অবৈধ অনুরোধ।'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'লগইন করতে হবে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrf === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'সেশন মেয়াদ শেষ — পেজ রিফ্রেশ করুন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$is_admin = false;
if (function_exists('ame_is_super_admin') && ame_is_super_admin()) {
    $is_admin = true;
} elseif (function_exists('ame_is_admin_like') && ame_is_admin_like()) {
    $is_admin = true;
} elseif (!empty($_SESSION['is_super_admin'])) {
    $is_admin = true;
} else {
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if (in_array($role, ['admin', 'super_admin', 'superadmin', 'owner'], true)) {
        $is_admin = true;
    }
}

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'শুধু অ্যাডমিন এই সেটিং বদলাতে পারবেন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

ensure_customer_tables($db);

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'কাস্টমার আইডি প্রয়োজন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$want = isset($_POST['on']) ? ((string)$_POST['on'] === '1' || $_POST['on'] === true) : null;
if ($want === null) {
    $chk = $db->prepare('SELECT always_wholesale FROM customers WHERE id = ? LIMIT 1');
    if (!$chk) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'ডাটাবেস ত্রুটি।'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $chk->bind_param('i', $id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'কাস্টমার খুঁজে পাওয়া যায়নি।'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $want = empty($row['always_wholesale']);
}

$flag = $want ? 1 : 0;
$stmt = $db->prepare('UPDATE customers SET always_wholesale = ? WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'আপডেট প্রস্তুত করা যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param('ii', $flag, $id);
if (!$stmt->execute() || $stmt->affected_rows < 0) {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'সংরক্ষণ করা যায়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->close();

echo json_encode([
    'ok' => true,
    'always_wholesale' => $flag === 1,
    'message' => $flag ? 'এই কাস্টমারের বিক্রয় এখন সবসময় পাইকারি দামে হবে।' : 'পাইকারি দাম বন্ধ — সাধারণ POS দাম মোড ফিরে এসেছে।',
], JSON_UNESCAPED_UNICODE);