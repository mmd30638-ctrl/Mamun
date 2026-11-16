<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
require_once __DIR__ . '/../../../includes/sms_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request method'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'লগইন করতে হবে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$session_token = (string)($_SESSION['csrf_token'] ?? '');
$posted_token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    echo json_encode(['ok' => false, 'message' => 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

ensure_customer_tables($db);

$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [$ids];
}
$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
if ($ids === []) {
    echo json_encode(['ok' => false, 'message' => 'কোনো গ্রাহক নির্বাচন হয়নি।'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (count($ids) > 80) {
    echo json_encode(['ok' => false, 'message' => 'একবারে সর্বোচ্চ ৮০ জনকে SMS যাবে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tpl = trim((string)($_POST['message'] ?? ''));
if ($tpl === '') {
    echo json_encode(['ok' => false, 'message' => 'মেসেজ খালি।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$in = implode(',', $ids);
$res = $db->query('SELECT id, name, phone, next_due_date, receivable_balance FROM customers WHERE id IN (' . $in . ')');
if (!$res) {
    echo json_encode(['ok' => false, 'message' => 'গ্রাহক তালিকা আসেনি।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sent = 0;
$failed = 0;
while ($row = $res->fetch_assoc()) {
    $phone = trim((string)($row['phone'] ?? ''));
    if ($phone === '') {
        $failed++;
        continue;
    }
    $due = (float)($row['receivable_balance'] ?? 0);
    $date = trim((string)($row['next_due_date'] ?? ''));
    $dateShow = $date;
    $t = $date !== '' ? strtotime($date) : false;
    if ($t) {
        $dateShow = date('d/m/Y', $t);
    }
    $msg = strtr($tpl, [
        '{name}' => (string)($row['name'] ?? ''),
        '{due}' => '৳' . number_format($due, 2),
        '{date}' => $dateShow !== '' ? $dateShow : '—',
        '{phone}' => $phone,
    ]);
    $ok = send_sms($phone, $msg, 'due_reminder');
    if ($ok) {
        $sent++;
    } else {
        $failed++;
    }
}

echo json_encode([
    'ok' => $sent > 0,
    'sent' => $sent,
    'failed' => $failed,
    'message' => $sent > 0
        ? ($sent . ' জনকে SMS গেছে' . ($failed > 0 ? ', ' . $failed . ' জন যায়নি' : '') . '।')
        : 'কোনো SMS যায়নি। গেটওয়ে/সিম সেটিংস দেখুন।',
], JSON_UNESCAPED_UNICODE);
