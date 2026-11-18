<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/pay_mark.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: list.php');
    exit;
}

csrf_verify();
$id = (int)($_POST['account_id'] ?? 0);
ame_pay_mark_set($id);
header('Location: list.php');
exit;
