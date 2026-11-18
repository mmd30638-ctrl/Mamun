<?php
require_once '../../../includes/config.php';
require_once '../../../includes/account_functions.php';
require_post();
csrf_verify();
admin_only();

$ledger_id  = (int)($_POST['ledger_id'] ?? 0);
$account_id = (int)($_POST['account_id'] ?? 0);

if ($ledger_id <= 0 || $account_id <= 0) {
    die('❌ ভুল রিকোয়েস্ট। <a href="../list.php">ফিরে যান</a>');
}

$db->begin_transaction();
try {
    reverse_ledger_entry($ledger_id, 'ম্যানুয়াল রিভার্সাল (অ্যাডমিন প্যানেল থেকে)');
    $db->commit();
    header('Location: ../ledger.php?id=' . $account_id . '&reversed=1');
    exit;
} catch (Exception $e) {
    $db->rollback();
    die('❌ রিভার্স করা যায়নি: ' . htmlspecialchars($e->getMessage()) . ' <a href="../ledger.php?id=' . $account_id . '">ফিরে যান</a>');
}
?>
