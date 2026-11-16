<?php
require_once '../../../includes/config.php';

require_post();
csrf_verify();

$ledger_id  = (int)($_POST['ledger_id'] ?? 0);
$account_id = (int)($_POST['account_id'] ?? 0);
$start_date = $_POST['start_date'] ?? '';
$end_date   = $_POST['end_date'] ?? '';

if ($ledger_id <= 0 || $account_id <= 0) {
    die('❌ ভুল রিকোয়েস্ট। <a href="../list.php">ফিরে যান</a>');
}

$stmt = $db->prepare(
    "UPDATE account_ledger SET is_reconciled = NOT is_reconciled WHERE id = ? AND account_id = ?"
);
$stmt->bind_param("ii", $ledger_id, $account_id);
$stmt->execute();

$redirect = '../ledger.php?id=' . $account_id . '&reconciled=1';
if ($start_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) { $redirect .= '&start_date=' . urlencode($start_date); }
if ($end_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) { $redirect .= '&end_date=' . urlencode($end_date); }

header('Location: ' . $redirect);
exit;
?>
