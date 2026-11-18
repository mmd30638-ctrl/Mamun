<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
require_once '../../../includes/account_functions.php';
ame_oloan_require_access($db);
require_post();
csrf_verify();

$loan_id = (int)($_POST['loan_id'] ?? 0);
$loan = ame_oloan_loan_fetch($db, $loan_id);
if (!$loan) {
    die('❌ ' . ame_l('ঋণ খুঁজে পাওয়া যায়নি।', 'Loan not found.'));
}

$disbursement_date = clean_input($_POST['disbursement_date'] ?? date('Y-m-d'));
$disbursed_amount = round((float)($_POST['disbursed_amount'] ?? 0), 2);
$processing_fee = round((float)($_POST['processing_fee'] ?? 0), 2);
$other_charges = round((float)($_POST['other_charges'] ?? 0), 2);
$net_amount_received = round($disbursed_amount - $processing_fee - $other_charges, 2);
$bank_ref_no = clean_input($_POST['bank_ref_no'] ?? '');
$payment_method = clean_input($_POST['payment_method'] ?? 'cash');
$account_id = (int)($_POST['account_id'] ?? 0);
$remarks = clean_input($_POST['remarks'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($disbursed_amount <= 0 || $account_id <= 0) {
    redirect('disburse_form.php?loan_id=' . $loan_id . '&err=' . rawurlencode(ame_l('পরিমাণ ও অ্যাকাউন্ট আবশ্যক।', 'Amount and account are required.')));
}

$db->begin_transaction();
$in_txn = true;
try {
    $ins = $db->prepare("INSERT INTO owner_loan_disbursements
        (loan_id, owner_id, bank_id, disbursement_date, approved_amount, disbursed_amount, processing_fee, other_charges, net_amount_received, bank_ref_no, payment_method, account_id, remarks, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    // টাইপ: i(loan) i(owner) i(bank) s(date) d(approved) d(disbursed) d(fee) d(other) d(net) s(ref) s(method) i(account) s(remarks) i(created_by) = "iiisdddddssisi" (১৪টি)
    $bank_id = $loan['bank_id'];
    $approved_amount = (float)$loan['approved_amount'];
    $ins->bind_param('iiisdddddssisi', $loan_id, $loan['owner_id'], $bank_id, $disbursement_date, $approved_amount, $disbursed_amount, $processing_fee, $other_charges, $net_amount_received, $bank_ref_no, $payment_method, $account_id, $remarks, $user_id);
    $ins->execute();
    $disb_id = $db->insert_id;
    $ins->close();

    // দোকানের অ্যাকাউন্ট থেকে টাকা বের হলো (ঋণ হিসেবে মালিকের হাতে গেল)
    account_debit($account_id, $disbursed_amount, 'owner_loan_disbursement', $disb_id, 'ঋণ বিতরণ — ' . $loan['owner_name'] . ' (ঋণ #' . $loan_id . ')');

    // লোনের স্ট্যাটাস active করা ও disbursement_date সেট করা
    $upd = $db->prepare("UPDATE owner_loans SET status = 'active', disbursement_date = ? WHERE id = ?");
    $upd->bind_param('si', $disbursement_date, $loan_id);
    $upd->execute();
    $upd->close();

    // মূল লেজারে এন্ট্রি — কনভেনশন: disbursement = credit (মালিকের দায় বাড়ল)
    ame_owner_ledger_add($db, (int)$loan['owner_id'], $loan_id, null, $disbursement_date, 'loan_disbursement',
        'ঋণ বিতরণ #' . $loan_id, 0.0, $disbursed_amount, $bank_ref_no ?: null, $user_id);

    $db->commit();
    $in_txn = false;

    log_activity('Owner Loan Disbursed', 'owner_loans', "ঋণ #$loan_id বিতরণ করা হলো — পরিমাণ: {$disbursed_amount}, নিট: {$net_amount_received}");
    redirect('loan_view.php?id=' . $loan_id . '&saved=1');
} catch (Throwable $e) {
    if ($in_txn) { $db->rollback(); }
    error_log('Owner loan disburse failed: ' . $e->getMessage());
    redirect('disburse_form.php?loan_id=' . $loan_id . '&err=' . rawurlencode(ame_l('বিতরণ সম্পন্ন করা যায়নি: ', 'Disbursement failed: ') . $e->getMessage()));
}
