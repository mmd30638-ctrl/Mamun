<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$now = time();
$window = (int)($_SESSION['ame_client_error_window'] ?? 0);
$count = (int)($_SESSION['ame_client_error_count'] ?? 0);
if ($window < ($now - 60)) {
    $window = $now;
    $count = 0;
}
if ($count >= 12) {
    http_response_code(429);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}
$_SESSION['ame_client_error_window'] = $window;
$_SESSION['ame_client_error_count'] = $count + 1;

$raw = (string)file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = preg_replace('/[^A-Z0-9_-]/i', '', (string)($data['error_id'] ?? ''));
if ($id === '') {
    $id = ame_error_request_id();
}
require_once __DIR__ . '/includes/qa_tracker.php';
$type = ame_qa_event_type((string)($data['type'] ?? 'JAVASCRIPT'));
if (!in_array($type, ['JAVASCRIPT', 'API'], true)) {
    $type = 'JAVASCRIPT';
}
$id = ame_qa_record([
    'type' => $type,
    'message' => (string)($data['message'] ?? 'JavaScript error'),
    'file' => (string)($data['source'] ?? ''),
    'line' => (int)($data['line'] ?? 0),
    'page' => (string)($data['page'] ?? ''),
    'request_id' => $id,
    'status' => (int)($data['status'] ?? 0),
    'module' => ame_error_module(),
    'user_id' => (int)$_SESSION['user_id'],
]);

echo json_encode(['ok' => true, 'error_id' => $id], JSON_UNESCAPED_UNICODE);
