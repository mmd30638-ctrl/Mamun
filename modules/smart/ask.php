<?php
require_once __DIR__ . '/_boot.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'text' => 'শুধু POST চলবে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'text' => 'নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করুন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : $_POST;
$q = trim((string)($data['q'] ?? ''));
$mode = (string)($data['mode'] ?? 'ask');

if ($mode === 'voice') {
    echo json_encode(ame_smart_resolve_voice($db, $q), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$ans = ame_smart_ask($db, $q);
$ans['ok'] = true;
echo json_encode($ans, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
