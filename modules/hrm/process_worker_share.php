<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';
// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।
ensure_hrm_schema($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: worker_share.php');
    exit();
}

$session_token = $_SESSION['csrf_token'] ?? '';
$posted_token = $_POST['csrf_token'] ?? '';
if ($session_token === '' || !hash_equals($session_token, $posted_token)) {
    header('Location: worker_share.php?err=csrf');
    exit();
}

$legacy_employee_id = (int)($_POST['employee_id'] ?? 0);
$_SESSION['user_flash'] = 'ডাবল বেতন বন্ধ রাখতে পুরোনো সার্ভিস-ভাগ পেমেন্ট বন্ধ করা হয়েছে। কর্মী হাবের বেতন অপশন ব্যবহার করুন।';
$_SESSION['user_flash_ok'] = 0;
header('Location: employee_list.php?open=salary&emp=' . $legacy_employee_id);
exit();

$employee_id = (int)($_POST['employee_id'] ?? 0);
$account_id = (int)($_POST['account_id'] ?? 0);
$payout_date = trim((string)($_POST['payout_date'] ?? date('Y-m-d')));
$note = clean_input($_POST['note'] ?? '');
$month = trim((string)($_POST['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

if ($employee_id <= 0) {
    header('Location: worker_share.php?month=' . urlencode($month) . '&err=worker');
    exit();
}
if ($account_id <= 0) {
    header('Location: worker_share.php?month=' . urlencode($month) . '&err=account');
    exit();
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payout_date)) {
    $payout_date = date('Y-m-d');
}

$emp_chk = $db->prepare("SELECT id, name FROM employees WHERE id = ?");
$emp_chk->bind_param("i", $employee_id);
$emp_chk->execute();
$emp = $emp_chk->get_result()->fetch_assoc();
$emp_chk->close();
if (!$emp) {
    header('Location: worker_share.php?month=' . urlencode($month) . '&err=worker');
    exit();
}

$due = get_pending_commission($db, $employee_id);
if ($due <= 0) {
    header('Location: worker_share.php?month=' . urlencode($month) . '&err=due');
    exit();
}

$created_by = (int)($_SESSION['user_id'] ?? 0);
$pay_note = $note !== '' ? $note : ('সার্ভিস ভাগ: ' . $emp['name']);

$db->begin_transaction();
try {
    $ins = $db->prepare(
        "INSERT INTO worker_payouts (employee_id, account_id, amount, payout_date, note, created_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $ins->bind_param("iidssi", $employee_id, $account_id, $due, $payout_date, $pay_note, $created_by);
    $ins->execute();
    $payout_id = (int)$db->insert_id;
    $ins->close();

    account_debit(
        $account_id,
        $due,
        'worker_share',
        $payout_id,
        'কর্মী সার্ভিস ভাগ: ' . $emp['name'] . ' — ' . number_format($due, 2) . ' ৳'
    );

    mark_commissions_paid($db, $employee_id, $payout_id);
    $db->commit();
    log_activity('Worker share paid', 'HRM', $emp['name'] . ' — ' . number_format($due, 2));
    header('Location: worker_statement.php?id=' . $employee_id . '&month=' . urlencode($month) . '&ok=paid');
    exit();
} catch (Throwable $e) {
    $db->rollback();
    $msg = $e->getMessage();
    $err = (strpos($msg, 'অপর্যাপ্ত') !== false) ? 'balance' : 'save';
    error_log('worker share payout failed: ' . $msg);
    header('Location: worker_share.php?month=' . urlencode($month) . '&err=' . $err);
    exit();
}
