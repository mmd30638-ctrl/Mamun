<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';
ame_earnings_boot($db, 'approve');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: earnings_pay.php');
    exit();
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    header('Location: earnings_pay.php?err=csrf');
    exit();
}
$id = (int)($_POST['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM worker_payments WHERE id = ? AND status = 'pending_approval'");
$stmt->bind_param('i', $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$p) {
    header('Location: earnings_pay.php?err=payment');
    exit();
}
$actor = (int)($_SESSION['user_id'] ?? 0);
$now = date('Y-m-d H:i:s');
try {
    $db->begin_transaction();
    $up = $db->prepare("UPDATE worker_payments SET status='posted', approval_status='approved', approved_by=?, approved_at=? WHERE id=?");
    $up->bind_param('isi', $actor, $now, $id);
    $up->execute();
    $up->close();
    $account_id = (int)$p['account_id'];
    $amount = (float)$p['amount'];
    $emp = (int)$p['employee_id'];
    if ($account_id > 0 && function_exists('account_debit')) {
        account_debit($account_id, $amount, 'worker_earning', $id, 'Approved worker payment');
    }
    ame_earnings_allocate($db, $id, $emp, $amount);
    ame_earnings_mark_commissions_for_payment($db, $emp);
    ame_earnings_audit($db, 'payment_approved', 'worker_payments', $id, $emp, 'pending', 'posted');
    $db->commit();
    header('Location: earnings_pay.php?id=' . $emp . '&ok=approved');
} catch (Throwable $e) {
    $db->rollback();
    header('Location: earnings_pay.php?err=save');
}
exit();
