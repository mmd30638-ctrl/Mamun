<?php
/**
 * পুরনো ক্রয় মেমোর ভুল পরিশোধ বাতিল — পণ্য লাইন থাকে, paid শূন্য হয়।
 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/purchase_functions.php';
require_once __DIR__ . '/../../../includes/purchase_draft.php';
require_once __DIR__ . '/../../../includes/running_ledger.php';

header('Content-Type: application/json; charset=UTF-8');
ame_purchase_require_access($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'অনুমোদিত অনুরোধ নয়।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$submitted = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || $submitted === '' || !hash_equals((string)$_SESSION['csrf_token'], $submitted)) {
    echo json_encode(['status' => 'error', 'message' => 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!ame_running_is_admin()) {
    echo json_encode(['status' => 'error', 'message' => 'শুধু এডমিন পরিশোধ বাতিল করতে পারেন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$memo_no = ame_purchase_normalize_memo($_POST['memo_no'] ?? '');
$supplier_id = (int)($_POST['supplier_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));
$result = ame_running_void_memo_payment($db, $memo_no, $reason, $supplier_id);
if (empty($result['ok'])) {
    echo json_encode(['status' => 'error', 'message' => (string)($result['message'] ?? 'পরিশোধ বাতিল হয়নি।')], JSON_UNESCAPED_UNICODE);
    exit;
}

$_SESSION['success'] = (string)$result['message'];

echo json_encode([
    'status' => 'success',
    'message' => (string)$result['message'],
    'paid' => (float)($result['paid'] ?? 0),
    'running' => (float)($result['running'] ?? 0),
    'memo' => (string)($result['memo'] ?? $memo_no),
], JSON_UNESCAPED_UNICODE);
