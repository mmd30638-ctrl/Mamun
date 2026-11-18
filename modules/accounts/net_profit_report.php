<?php
require_once '../../includes/config.php';
$q = (string)($_SERVER['QUERY_STRING'] ?? '');
header('Location: /modules/reports/profit_loss.php' . ($q !== '' ? '?' . $q : ''), true, 302);
exit;
