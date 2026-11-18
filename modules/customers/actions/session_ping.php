<?php
require_once __DIR__ . '/../../../includes/config.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

echo json_encode([
    'status' => 'success',
    'csrf' => csrf_token(),
], JSON_UNESCAPED_UNICODE);
