<?php
require_once '../../includes/config.php';
admin_only();

$path = dirname(__DIR__, 2) . '/sql/aponshop_full_schema.sql';
if (!is_readable($path)) {
    http_response_code(404);
    echo 'স্কিমা SQL ফাইল পাওয়া যায়নি।';
    exit;
}

$ver = defined('AME_APP_VERSION') ? AME_APP_VERSION : '10.97.1';
$filename = 'aponshop_full_schema_' . preg_replace('/[^0-9.]+/', '', $ver) . '.sql';

header('Content-Type: application/sql; charset=utf-8');
header('Content-Transfer-Encoding: binary');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

readfile($path);
exit;
