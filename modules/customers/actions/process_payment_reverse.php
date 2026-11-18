<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../customer_schema.php';

require_post();
csrf_verify();
ensure_customer_tables($db);

$id = (int) ($_POST['payment_id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));
$back = '../receipt.php?payment_id=' . $id;

try {
    if ($id <= 0) {
        throw new RuntimeException('পেমেন্ট খুঁজে পাওয়া যায়নি।');
    }
    if (function_exists('ame_pay_sec_verify')) {
        ame_pay_sec_verify('customer_reverse', $id, ame_pay_sec_posted());
    }
    ame_lock($db, 'cust_pay_rev_' . $id);
    $db->begin_transaction();
    ame_customer_reverse_payment($db, $id, $reason);
    $db->commit();
    $_SESSION['cpay_ok'] = 'রসিদ বাতিল হয়েছে। টাকা অ্যাকাউন্ট থেকে কাটা হয়েছে, কাস্টমারের বাকি ফিরেছে।';
    header('Location: ' . $back . '#cpayReverse');
    exit;
} catch (Throwable $e) {
    if ($db instanceof mysqli) {
        @$db->rollback();
    }
    $_SESSION['cpay_error'] = $e->getMessage();
    header('Location: ' . ($id > 0 ? $back . '#cpayReverse' : '../payment-receive.php'));
    exit;
}
