<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
require_once '../../../includes/account_functions.php';
ame_oloan_require_access($db);
require_post();
csrf_verify();

$owner_id = (int)($_POST['owner_id'] ?? 0);
$savings_account_id = (int)($_POST['savings_account_id'] ?? 0);
$amount = round((float)($_POST['amount'] ?? 0), 2);
$reason = clean_input($_POST['reason'] ?? '');
$approved_by = (int)($_POST['approved_by'] ?? ($_SESSION['user_id'] ?? 0));
$paid_by = (int)($_POST['paid_by'] ?? ($_SESSION['user_id'] ?? 0));
$payment_method = clean_input($_POST['payment_method'] ?? 'cash');
$account_id = (int)($_POST['account_id'] ?? 0);
$reference_no = clean_input($_POST['reference_no'] ?? '');
$remarks = clean_input($_POST['remarks'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);
$today = date('Y-m-d');

if ($amount <= 0 || $savings_account_id <= 0 || $account_id <= 0) {
    redirect('savings_withdraw.php?owner_id=' . $owner_id . '&err=' . rawurlencode(ame_l('পরিমাণ ও অ্যাকাউন্ট সঠিকভাবে দিন।', 'Please provide a valid amount and account.')));
}

$db->begin_transaction();
$in_txn = true;
try {
    $lock = $db->prepare("SELECT current_balance FROM owner_savings_accounts WHERE id = ? AND owner_id = ? FOR UPDATE");
    $lock->bind_param('ii', $savings_account_id, $owner_id);
    $lock->execute();
    $row = $lock->get_result()->fetch_assoc();
    $lock->close();
    if (!$row) {
        throw new RuntimeException(ame_l('সঞ্চয় হিসাব খুঁজে পাওয়া যায়নি।', 'Savings account not found.'));
    }
    $prev_balance = (float)$row['current_balance'];
    if ($amount > $prev_balance) {
        throw new RuntimeException(ame_l('পর্যাপ্ত সঞ্চয় ব্যালেন্স নেই।', 'Insufficient savings balance.'));
    }
    $remaining_balance = round($prev_balance - $amount, 2);

    $ins = $db->prepare("INSERT INTO owner_savings_withdrawals
        (savings_account_id, owner_id, withdrawal_date, amount, previous_balance, remaining_balance, reason, approved_by, paid_by, payment_method, reference_no, remarks)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    // টাইপ: i(acc) i(owner) s(date) d(amount) d(prev) d(remain) s(reason) i(approved) i(paid) s(method) s(ref) s(remarks) = "iisdddsiisss" (১২টি)
    $ins->bind_param('iisdddsiisss', $savings_account_id, $owner_id, $today, $amount, $prev_balance, $remaining_balance, $reason, $approved_by, $paid_by, $payment_method, $reference_no, $remarks);
    $ins->execute();
    $withdrawal_id = $db->insert_id;
    $ins->close();

    $txn_type = 'withdrawal';
    $st = $db->prepare("INSERT INTO owner_savings_transactions
        (savings_account_id, owner_id, txn_date, txn_type, amount, previous_balance, new_balance, payment_method, reference_no, collected_by, remarks)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $st->bind_param('iissdddssis', $savings_account_id, $owner_id, $today, $txn_type, $amount, $prev_balance, $remaining_balance, $payment_method, $reference_no, $user_id, $remarks);
    $st->execute();
    $st->close();

    $upd = $db->prepare("UPDATE owner_savings_accounts SET current_balance = ? WHERE id = ?");
    $upd->bind_param('di', $remaining_balance, $savings_account_id);
    $upd->execute();
    $upd->close();

    // দোকানের অ্যাকাউন্ট থেকে টাকা বের হলো (মালিককে সঞ্চয় ফেরত দেওয়া হলো)
    account_debit($account_id, $amount, 'owner_savings_withdrawal', $withdrawal_id, 'সঞ্চয় উত্তোলন — ' . ($reason ?: 'N/A'));

    // সঞ্চয় লেজার — withdrawal = debit (জমা কমল)
    ame_owner_ledger_add($db, $owner_id, null, $savings_account_id, $today, 'savings_withdrawal',
        'সঞ্চয় উত্তোলন' . ($reason !== '' ? ' — ' . $reason : ''), $amount, 0.0, $reference_no ?: null, $user_id);

    $db->commit();
    $in_txn = false;

    log_activity('Owner Savings Withdrawal', 'owner_loans', "মালিক #$owner_id — সঞ্চয় উত্তোলন: {$amount} (কারণ: {$reason})");
    redirect('savings_withdraw.php?owner_id=' . $owner_id . '&saved=1');
} catch (Throwable $e) {
    if ($in_txn) { $db->rollback(); }
    error_log('Owner savings withdrawal failed: ' . $e->getMessage());
    redirect('savings_withdraw.php?owner_id=' . $owner_id . '&err=' . rawurlencode(ame_l('উত্তোলন সম্পন্ন করা যায়নি: ', 'Withdrawal failed: ') . $e->getMessage()));
}
