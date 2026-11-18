<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/pay_mark.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: cash_bank_summary.php');
    exit;
}

csrf_verify();
$id = (int)($_POST['account_id'] ?? 0);
ame_pay_mark_set($id);

$back = (string)($_POST['redirect'] ?? 'cash_bank_summary.php');
if (!preg_match('/^cash_bank_summary\.php(?:\?start_date=\d{4}-\d{2}-\d{2}&end_date=\d{4}-\d{2}-\d{2})?$/', $back)) {
    $back = 'cash_bank_summary.php';
}
header('Location: ' . $back);
exit;
