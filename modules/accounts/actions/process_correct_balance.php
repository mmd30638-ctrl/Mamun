<?php
require_once '../../../includes/config.php';
require_once '../../../includes/account_functions.php';

require_post();
csrf_verify();
ensure_accounts_schema($db);

$account_id = (int)($_POST['account_id'] ?? 0);
$correct_balance = (float)($_POST['correct_balance'] ?? null);
$note = clean_input($_POST['note'] ?? '');

if ($account_id <= 0 || $note === '') {
    die('❌ সব ঘর সঠিকভাবে পূরণ করুন। <a href="../correct_balance.php">ফিরে যান</a>');
}

$stmt = $db->prepare("SELECT balance FROM accounts WHERE id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$acc = $stmt->get_result()->fetch_assoc();

if (!$acc) {
    die('❌ অ্যাকাউন্ট খুঁজে পাওয়া যায়নি। <a href="../correct_balance.php">ফিরে যান</a>');
}

$current_balance = (float)$acc['balance'];
$difference = round($correct_balance - $current_balance, 2);

if (abs($difference) < 0.01) {
    echo "<script>alert('বর্তমান ব্যালেন্স ইতিমধ্যে সঠিক আছে, কোনো পরিবর্তন করা হয়নি।'); window.location='../list.php';</script>";
    exit;
}

$db->begin_transaction();
try {
    $full_note = 'ব্যালেন্স সংশোধন — ' . $note . ' (পুরনো: ৳' . number_format($current_balance, 2) . ' → নতুন: ৳' . number_format($correct_balance, 2) . ')';

    if ($difference > 0) {
        // প্রকৃত ব্যালেন্স সিস্টেমের চেয়ে বেশি — জমা (credit) এন্ট্রি
        account_credit($account_id, $difference, 'balance_correction', $account_id, $full_note);
    } else {
        // প্রকৃত ব্যালেন্স সিস্টেমের চেয়ে কম — খরচ (debit) এন্ট্রি, allow_negative=true যাতে সংশোধন কখনো আটকে না যায়
        account_debit($account_id, abs($difference), 'balance_correction', $account_id, $full_note, true);
    }

    $db->commit();
    if (function_exists('log_activity')) {
        log_activity('Account Balance Corrected', 'Accounts', $full_note, [
            'action_type' => 'edit',
            'entity_type' => 'account',
            'entity_id' => (string)$account_id,
            'old' => ['balance' => (string)$current_balance],
            'new' => ['balance' => (string)$correct_balance],
            'amount_total' => abs($difference),
        ]);
    }
    echo "<script>alert('ব্যালেন্স সফলভাবে সংশোধন করা হয়েছে।'); window.location='../list.php';</script>";
} catch (Exception $e) {
    $db->rollback();
    die('❌ সংশোধনে সমস্যা হয়েছে: ' . htmlspecialchars($e->getMessage()) . ' <a href="../correct_balance.php">ফিরে যান</a>');
}
?>
