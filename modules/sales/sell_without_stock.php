<?php
require_once '../../includes/config.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$submitted = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($submitted === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $submitted)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$on = (string)($_POST['on'] ?? '') === '1';
$_SESSION['ame_sell_without_stock'] = $on ? 1 : 0;
echo json_encode(['ok' => true, 'on' => $on]);
