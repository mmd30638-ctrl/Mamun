<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';
ame_earnings_boot($db, 'pay');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: earnings_pay.php');
    exit();
}
$session_token = $_SESSION['csrf_token'] ?? '';
if ($session_token === '' || !hash_equals($session_token, (string)($_POST['csrf_token'] ?? ''))) {
    header('Location: earnings_pay.php?err=csrf');
    exit();
}
$id = (int)($_POST['employee_id'] ?? 0);
$_SESSION['user_flash'] = 'ডাবল বেতন বন্ধ রাখতে পুরোনো আর্নিংস পেমেন্ট বন্ধ করা হয়েছে। কর্মী হাবের বেতন অপশন ব্যবহার করুন।';
$_SESSION['user_flash_ok'] = 0;
header('Location: employee_list.php?open=salary&emp=' . $id);
exit();

try {
    $db->begin_transaction();
    ame_earnings_post_payment($db, $_POST);
    $db->commit();
    header('Location: earnings_pay.php?id=' . $id . '&ok=paid');
} catch (Throwable $e) {
    $db->rollback();
    $msg = $e->getMessage();
    $err = in_array($msg, ['amount', 'over', 'worker'], true) ? $msg : ((strpos($msg, 'অপর্যাপ্ত') !== false) ? 'balance' : 'save');
    header('Location: earnings_pay.php?id=' . $id . '&err=' . $err);
}
exit();
