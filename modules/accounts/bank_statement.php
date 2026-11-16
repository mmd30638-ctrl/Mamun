<?php
require_once '../../includes/config.php';
$q = (string)($_SERVER['QUERY_STRING'] ?? '');
header('Location: /modules/partners/bank_statement.php' . ($q !== '' ? '?' . $q : ''), true, 302);
exit;
