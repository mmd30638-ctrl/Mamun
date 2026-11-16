<?php
require_once '../../includes/config.php';
admin_only();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit();
}

csrf_verify();

$_SESSION['danger_tools_unlocked'] = time();
$_SESSION['danger_tools_unlocked_until'] = time() + 1800;

echo json_encode(['ok' => true, 'message' => 'অ্যাডভান্সড টুলস আনলক হয়েছে।']);
