<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/shop_rent.php';

$id = (int)($_GET['id'] ?? 0);
$row = shop_rent_file_path($db, $id);
if (!$row || !is_file($row['path'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'কাগজটি পাওয়া যায়নি।';
    exit;
}
$mime = (string)$row['mime'];
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
    http_response_code(403);
    exit;
}
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . (string)filesize($row['path']));
header('Cache-Control: private, max-age=3600');
readfile($row['path']);
