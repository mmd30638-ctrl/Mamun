<?php
/**
 * অ্যাডমিন: ame_ledger_seal মনিটরিং বেসলাইন = বর্তমান DB মোট (পার্টি ব্যালেন্স টাচ করে না)।
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

header('Content-Type: application/json; charset=UTF-8');

$respond = static function (bool $ok, string $message, array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
};

if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
    http_response_code(403);
    $respond(false, 'শুধু অ্যাডমিন।');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $respond(false, 'POST লাগবে।');
}

if (!($db instanceof mysqli)) {
    http_response_code(500);
    $respond(false, 'ডাটাবেজ সংযোগ নেই।');
}

$token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || $token === '' || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    $respond(false, 'CSRF যাচাই ব্যর্থ — পেজ রিফ্রেশ করুন।');
}

if (!function_exists('ame_ledger_seal_refresh_baseline')) {
    require_once dirname(__DIR__, 3) . '/includes/ledger_lock.php';
}

$note = trim((string)($_POST['note'] ?? ''));
$result = ame_ledger_seal_refresh_baseline($db, $note);

if (!$result['ok']) {
    http_response_code(500);
}

$respond($result['ok'], $result['message'], [
    'supplier_sum' => $result['supplier'],
    'customer_sum' => $result['customer'],
]);