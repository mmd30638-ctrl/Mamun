<?php
/**
 * স্পেক সেকশন ৭+১৪+২৫ — কোর অ্যাকাউন্টিং রুল।
 * একই ফর্ম থেকে দুটো স্বাধীন অংশ প্রসেস হয় (একই DB ট্রানজেকশনে):
 *   ১) ঋণ কিস্তি (loan payment) — owner_loans.outstanding_amount শুধু
 *      principal_paid দিয়ে কমে (interest/penalty দিয়ে কমে না)।
 *   ২) সঞ্চয় জমা (savings deposit) — সম্পূর্ণ আলাদা owner_savings_accounts
 *      হিসাব, ঋণের হিসাবকে কোনোভাবে স্পর্শ করে না।
 * দুটোর জন্য owner_transaction_ledger-এ দুটো আলাদা সারি তৈরি হয়।
 * দোকানের accounts.balance-এ বাস্তবে একটাই ক্যাশ রিসিপ্ট হওয়ায়,
 * account_credit() *একবার* গ্র্যান্ড টোটাল দিয়ে কল করা হয় (নিচে দেখুন)।
 */
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
require_once '../../../includes/account_functions.php';
ame_oloan_require_access($db);
require_post();
csrf_verify();

$owner_id = (int)($_POST['owner_id'] ?? 0);
$loan_id = (int)($_POST['loan_id'] ?? 0);
$installment_id = (int)($_POST['installment_id'] ?? 0);
$principal_paid = round((float)($_POST['principal_paid'] ?? 0), 2);
$interest_paid = round((float)($_POST['interest_paid'] ?? 0), 2);
$penalty_charge = round((float)($_POST['penalty_charge'] ?? 0), 2);
$savings_deposit = round((float)($_POST['savings_deposit'] ?? 0), 2);
$payment_method = clean_input($_POST['payment_method'] ?? 'cash');
$account_id = (int)($_POST['account_id'] ?? 0);
$reference_no = clean_input($_POST['reference_no'] ?? '');
$remarks = clean_input($_POST['remarks'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);
$today = date('Y-m-d');

$loan_total = round($principal_paid + $interest_paid + $penalty_charge, 2);
$grand_total = round($loan_total + $savings_deposit, 2);

$owner = ame_oloan_owner_fetch($db, $owner_id);
if (!$owner) {
    redirect('payment_entry.php?err=' . rawurlencode(ame_l('মালিক খুঁজে পাওয়া যায়নি।', 'Owner not found.')));
}
if ($grand_total <= 0) {
    redirect('payment_entry.php?owner_id=' . $owner_id . '&loan_id=' . $loan_id . '&err=' . rawurlencode(ame_l('আদায়ের পরিমাণ শূন্য হতে পারে না।', 'Collection amount cannot be zero.')));
}
if ($account_id <= 0) {
    redirect('payment_entry.php?owner_id=' . $owner_id . '&loan_id=' . $loan_id . '&err=' . rawurlencode(ame_l('অ্যাকাউন্ট নির্বাচন করুন।', 'Please select an account.')));
}

$db->begin_transaction();
$in_txn = true;
try {
    // ---------- ১) ঋণ কিস্তি অংশ ----------
    if ($loan_total > 0 && $loan_id > 0) {
        $loan = ame_oloan_loan_fetch($db, $loan_id);
        if (!$loan) {
            throw new RuntimeException(ame_l('ঋণ খুঁজে পাওয়া যায়নি।', 'Loan not found.'));
        }

        $pay = $db->prepare("INSERT INTO owner_loan_payments
            (loan_id, owner_id, installment_id, payment_date, principal_paid, interest_paid, penalty_charge, total_payment, payment_method, account_id, reference_no, collected_by, remarks)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        // টাইপ: i(loan) i(owner) i(installment) s(date) d(principal) d(interest) d(penalty) d(total) s(method) i(account) s(ref) i(collector) s(remarks) = "iiisddddsisis" (১৩টি)
        $inst_id_param = $installment_id > 0 ? $installment_id : null;
        $pay->bind_param('iiisddddsisis', $loan_id, $owner_id, $inst_id_param, $today, $principal_paid, $interest_paid, $penalty_charge, $loan_total, $payment_method, $account_id, $reference_no, $user_id, $remarks);
        $pay->execute();
        $payment_id = $db->insert_id;
        $pay->close();

        // কিস্তির paid_amount/remaining_amount/status/payment_date আপডেট
        if ($installment_id > 0) {
            $inst = $db->prepare("SELECT * FROM owner_loan_installments WHERE id = ? FOR UPDATE");
            $inst->bind_param('i', $installment_id);
            $inst->execute();
            $inst_row = $inst->get_result()->fetch_assoc();
            $inst->close();
            if ($inst_row) {
                $new_paid = round((float)$inst_row['paid_amount'] + $loan_total, 2);
                $new_remaining = round((float)$inst_row['total_installment'] - $new_paid, 2);
                if ($new_remaining < 0) { $new_remaining = 0.0; }
                $new_status = $new_remaining <= 0.005 ? 'paid' : ($new_paid > 0 ? 'partial' : 'upcoming');
                $u = $db->prepare("UPDATE owner_loan_installments SET paid_amount = ?, remaining_amount = ?, status = ?, payment_date = ? WHERE id = ?");
                $u->bind_param('ddssi', $new_paid, $new_remaining, $new_status, $today, $installment_id);
                $u->execute();
                $u->close();
            }
        }

        // owner_loans.paid_amount বাড়ে (মোট আদায় ট্র্যাক করতে); outstanding_amount শুধু principal_paid দিয়ে কমে
        $lock = $db->prepare("SELECT outstanding_amount FROM owner_loans WHERE id = ? FOR UPDATE");
        $lock->bind_param('i', $loan_id);
        $lock->execute();
        $lrow = $lock->get_result()->fetch_assoc();
        $lock->close();
        $cur_outstanding = $lrow ? (float)$lrow['outstanding_amount'] : 0.0;
        $new_outstanding = round($cur_outstanding - $principal_paid, 2);
        if ($new_outstanding < 0) { $new_outstanding = 0.0; }
        $new_loan_status = $new_outstanding <= 0.005 ? 'completed' : null;

        $ul = $db->prepare("UPDATE owner_loans SET paid_amount = paid_amount + ?, outstanding_amount = ? WHERE id = ?");
        $ul->bind_param('ddi', $loan_total, $new_outstanding, $loan_id);
        $ul->execute();
        $ul->close();
        if ($new_loan_status) {
            $us = $db->prepare("UPDATE owner_loans SET status = ? WHERE id = ?");
            $us->bind_param('si', $new_loan_status, $loan_id);
            $us->execute();
            $us->close();
        }

        // লোন লেজার — payment = debit (দায় কমল)
        ame_owner_ledger_add($db, $owner_id, $loan_id, null, $today, 'loan_payment',
            'ঋণ কিস্তি আদায় — ঋণ #' . $loan_id . ' (আসল ৳' . number_format($principal_paid, 2) . ' + সুদ ৳' . number_format($interest_paid, 2) . ' + জরিমানা ৳' . number_format($penalty_charge, 2) . ')',
            $loan_total, 0.0, $reference_no ?: null, $user_id);

        log_activity('Owner Loan Payment', 'owner_loans', "ঋণ #$loan_id — কিস্তি আদায়: আসল {$principal_paid}, সুদ {$interest_paid}, জরিমানা {$penalty_charge}, মোট {$loan_total}");
    }

    // ---------- ২) সঞ্চয় জমা অংশ (সম্পূর্ণ আলাদা, ঋণের হিসাব স্পর্শ করে না) ----------
    if ($savings_deposit > 0) {
        $sav_acc = ame_oloan_find_or_create_savings_account($db, $owner_id);
        $sav_acc_id = (int)$sav_acc['id'];

        $lockS = $db->prepare("SELECT current_balance FROM owner_savings_accounts WHERE id = ? FOR UPDATE");
        $lockS->bind_param('i', $sav_acc_id);
        $lockS->execute();
        $srow = $lockS->get_result()->fetch_assoc();
        $lockS->close();
        $prev_balance = $srow ? (float)$srow['current_balance'] : 0.0;
        $new_balance = round($prev_balance + $savings_deposit, 2);

        $st = $db->prepare("INSERT INTO owner_savings_transactions
            (savings_account_id, owner_id, txn_date, txn_type, amount, previous_balance, new_balance, payment_method, reference_no, collected_by, remarks)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        // টাইপ: i(acc) i(owner) s(date) s(type) d(amount) d(prev) d(new) s(method) s(ref) i(collector) s(remarks) = "iissdddssis" (১১টি)
        $txn_type = 'deposit';
        $st->bind_param('iissdddssis', $sav_acc_id, $owner_id, $today, $txn_type, $savings_deposit, $prev_balance, $new_balance, $payment_method, $reference_no, $user_id, $remarks);
        $st->execute();
        $st->close();

        $ua = $db->prepare("UPDATE owner_savings_accounts SET current_balance = ? WHERE id = ?");
        $ua->bind_param('di', $new_balance, $sav_acc_id);
        $ua->execute();
        $ua->close();

        // সঞ্চয় লেজার — deposit = credit (মালিকের জমা বাড়ল)
        ame_owner_ledger_add($db, $owner_id, null, $sav_acc_id, $today, 'savings_deposit',
            'সঞ্চয় জমা', 0.0, $savings_deposit, $reference_no ?: null, $user_id);

        log_activity('Owner Savings Deposit', 'owner_loans', "মালিক #$owner_id — সঞ্চয় জমা: {$savings_deposit}");
    }

    // ---------- দোকানের ক্যাশ/ব্যাংক অ্যাকাউন্টে মোট (গ্র্যান্ড টোটাল) একবারে জমা ----------
    // নোট: একই ফিজিক্যাল রিসিপ্টে ঋণ কিস্তি + সঞ্চয় জমা একসাথে নেওয়া হলেও এটি
    // দোকানের হিসাবে একটাই বাস্তব ক্যাশ-ইন — তাই account_credit() *একবার* মোট
    // পরিমাণ দিয়ে কল করা হচ্ছে (দুইবার আলাদা কল করলে accounts.balance ভুলভাবে
    // ডাবল-কাউন্ট হতো না ঠিকই, কিন্তু লজিক্যালি একটাই রিসিপ্ট বোঝাতে একবার কল-ই সঠিক)।
    account_credit($account_id, $grand_total, 'owner_loan_collection', $loan_id ?: $owner_id, 'কিস্তি/সঞ্চয় আদায় — ' . $owner['owner_name'] . ' (মোট ৳' . number_format($grand_total, 2) . ')');

    $db->commit();
    $in_txn = false;

    redirect('payment_entry.php?owner_id=' . $owner_id . '&loan_id=' . $loan_id . '&saved=1');
} catch (Throwable $e) {
    if ($in_txn) { $db->rollback(); }
    error_log('Owner payment collection failed: ' . $e->getMessage());
    redirect('payment_entry.php?owner_id=' . $owner_id . '&loan_id=' . $loan_id . '&err=' . rawurlencode(ame_l('আদায় সম্পন্ন করা যায়নি: ', 'Collection failed: ') . $e->getMessage()));
}
