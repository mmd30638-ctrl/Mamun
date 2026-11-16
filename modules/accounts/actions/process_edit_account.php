<?php
require_once '../../../includes/config.php';
require_once '../../../includes/account_functions.php';

require_post();
csrf_verify();
ensure_accounts_schema($db);

$id = (int) ($_POST['id'] ?? 0);
$name = clean_input($_POST['account_name'] ?? '');
$type = (string) ($_POST['account_type'] ?? 'Cash');
if (!in_array($type, ['Cash', 'Bank', 'Mobile'], true)) {
    $type = 'Cash';
}
$threshold_raw = trim((string) ($_POST['low_balance_threshold'] ?? ''));
$threshold = $threshold_raw !== '' ? (float) $threshold_raw : null;
$alert_phone_raw = trim((string) ($_POST['alert_phone'] ?? ''));
$alert_phone = $alert_phone_raw !== '' ? $alert_phone_raw : null;
$opening_raw = trim((string) ($_POST['opening_balance'] ?? ''));
$wanted_opening = $opening_raw !== '' ? round((float) $opening_raw, 2) : 0.0;

if ($id <= 0 || $name === '') {
    die('❌ ভুল ইনপুট। <a href="../list.php">ফিরে যান</a>');
}

$db->begin_transaction();
try {
    $stmt = $db->prepare('UPDATE accounts SET account_name = ?, account_type = ?, low_balance_threshold = ?, alert_phone = ? WHERE id = ?');
    $stmt->bind_param('ssdsi', $name, $type, $threshold, $alert_phone, $id);
    if (!$stmt->execute()) {
        throw new RuntimeException('অ্যাকাউন্ট আপডেট হয়নি।');
    }
    $stmt->close();

    $have = ame_account_opening_sum($db, $id);
    $delta = round($wanted_opening - $have, 2);
    if (abs($delta) >= 0.01) {
        if ($delta > 0) {
            account_credit($id, $delta, 'opening_balance', $id, 'ওপেনিং ব্যালেন্স');
        } else {
            account_debit($id, abs($delta), 'opening_balance', $id, 'ওপেনিং ব্যালেন্স সমন্বয়', true);
        }
    }

    $db->commit();
    if (function_exists('log_activity')) {
        log_activity('Account Updated', 'Accounts', $name . ' #' . $id, [
            'action_type' => 'edit',
            'entity_type' => 'account',
            'entity_id' => (string)$id,
            'reference' => $name,
        ]);
    }
} catch (Throwable $e) {
    $db->rollback();
    die('❌ ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' <a href="../edit_account.php?id=' . $id . '">ফিরে যান</a>');
}

echo "<script>alert('অ্যাকাউন্ট তথ্য ও ওপেনিং ব্যালেন্স হালনাগাদ হয়েছে'); window.location='../list.php';</script>";
