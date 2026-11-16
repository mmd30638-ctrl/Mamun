<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
require_once __DIR__ . '/../customer_identity.php';
ensure_customer_tables($db);

$code       = trim($_GET['code'] ?? '');
$phone      = trim($_GET['phone'] ?? '');
$nid        = trim($_GET['nid'] ?? '');
$exclude_id = (int)($_GET['exclude_id'] ?? 0);

$flags = ame_customer_identity_flags($db, $code, $phone, $nid, $exclude_id);
if (!empty($flags['error'])) {
    echo json_encode([
        'code_exists' => $code !== '',
        'phone_exists' => $phone !== '',
        'nid_exists' => $nid !== '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'code_exists' => !empty($flags['code']),
    'phone_exists' => !empty($flags['phone']),
    'nid_exists' => !empty($flags['nid']),
], JSON_UNESCAPED_UNICODE);
