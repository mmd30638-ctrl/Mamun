<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';
ame_earnings_boot($db, 'reverse');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: earnings_pay.php');
    exit();
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    header('Location: earnings_pay.php?err=csrf');
    exit();
}
$id = (int)($_POST['id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));
$emp = 0;
$p = $db->query('SELECT employee_id FROM worker_payments WHERE id = ' . $id);
if ($p && ($pr = $p->fetch_assoc())) {
    $emp = (int)$pr['employee_id'];
}
try {
    $db->begin_transaction();
    ame_earnings_reverse_payment($db, $id, $reason);
    $db->commit();
    header('Location: earnings_pay.php?id=' . $emp . '&ok=reversed');
} catch (Throwable $e) {
    $db->rollback();
    $err = $e->getMessage() === 'reason' ? 'reason' : 'payment';
    header('Location: earnings_pay.php?id=' . $emp . '&err=' . $err);
}
exit();
