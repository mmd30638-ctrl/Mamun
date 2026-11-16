<?php
require_once '../../includes/config.php';
$q = (string)($_SERVER['QUERY_STRING'] ?? '');
header('Location: /modules/reports/tax_vat_summary.php' . ($q !== '' ? '?' . $q : ''), true, 302);
exit;
