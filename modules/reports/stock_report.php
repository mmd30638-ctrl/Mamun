<?php
require_once __DIR__ . '/../../includes/config.php';
$query = (string) ($_SERVER['QUERY_STRING'] ?? '');
$target = '/modules/inventory/stock_report.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 302);
exit;
