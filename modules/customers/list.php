<?php
require_once __DIR__ . '/../../includes/config.php';
$query = (string) ($_SERVER['QUERY_STRING'] ?? '');
$target = '/modules/customers/index.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 302);
exit;
