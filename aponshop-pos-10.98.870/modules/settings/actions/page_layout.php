<?php
/**
 * ame-page-fit.js — পেজ সাজানো স্থানীয় + সার্ভার সিঙ্ক।
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/page_layout.php';

header('Content-Type: application/json; charset=UTF-8');

$respond = static function (bool $ok, string $message, array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
};

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    $respond(false, 'লগইন লাগবে।');
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $path = (string) ($_GET['path'] ?? $_SERVER['SCRIPT_NAME'] ?? '/');
    $items = ame_page_fit_items_for_path($path);
    $respond(true, 'ok', ['items' => $items, 'path' => ame_page_fit_normalize_path($path)]);
}

if ($method !== 'POST') {
    http_response_code(405);
    $respond(false, 'GET বা POST লাগবে।');
}

$db = $GLOBALS['db'] ?? null;
if (!ame_page_layout_can_edit($db instanceof mysqli ? $db : null)) {
    http_response_code(403);
    $respond(false, 'পেজ সাজানোর অনুমতি নেই।');
}

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || $token === '' || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    $respond(false, 'CSRF যাচাই ব্যর্থ — পেজ রিফ্রেশ করুন।');
}

$raw = file_get_contents('php://input');
$payload = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($payload)) {
    http_response_code(400);
    $respond(false, 'ভুল JSON।');
}

$path = (string) ($payload['path'] ?? '');
if ($path === '') {
    http_response_code(400);
    $respond(false, 'path লাগবে।');
}

if (!empty($payload['reset'])) {
    $ok = ame_page_fit_clear_path($path);
    $respond($ok, $ok ? 'রিসেট হয়েছে।' : 'রিসেট ব্যর্থ।', ['items' => []]);
}

$items = $payload['items'] ?? [];
if (!is_array($items)) {
    $items = [];
}

$ok = ame_page_fit_save_path($path, $items);
if (!$ok) {
    http_response_code(500);
    $respond(false, 'সংরক্ষণ ব্যর্থ — storage/page_fit.json লেখার অনুমতি চেক করুন।');
}

$respond(true, !empty($payload['complete']) ? 'ডিজাইন সংরক্ষণ সম্পন্ন।' : 'সংরক্ষিত।', [
    'items' => ame_page_fit_items_for_path($path),
]);