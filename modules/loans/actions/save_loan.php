<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/loan_functions.php';
admin_only();
ensure_bank_loan_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../add.php');
    exit;
}
csrf_verify();

$bank_name = trim($_POST['bank_name'] ?? '');
$loan_name = trim($_POST['loan_name'] ?? '');
$loan_type = trim($_POST['loan_type'] ?? 'ব্যবসায়িক লোন');
$principal_amount = round((float)($_POST['principal_amount'] ?? 0), 2);
$loan_date = trim($_POST['loan_date'] ?? date('Y-m-d'));
$account_id = (int)($_POST['account_id'] ?? 0);
$interest_rate = round((float)($_POST['interest_rate'] ?? 0), 2);
$tenure_months = (int)($_POST['tenure_months'] ?? 0);
$installment_count = (int)($_POST['installment_count'] ?? 0);
$installment_amount = round((float)($_POST['installment_amount'] ?? 0), 2);
$first_installment_date = trim($_POST['first_installment_date'] ?? '') ?: null;
$last_installment_date = trim($_POST['last_installment_date'] ?? '') ?: null;
$processing_fee = round((float)($_POST['processing_fee'] ?? 0), 2);
$other_charges = round((float)($_POST['other_charges'] ?? 0), 2);
$notes = substr(trim($_POST['notes'] ?? ''), 0, 500);

if ($bank_name === '' || $loan_name === '') {
    $_SESSION['error'] = '⚠️ ব্যাংকের নাম ও লোনের নাম দিন।';
    header('Location: ../add.php');
    exit;
}
if ($principal_amount <= 0) {
    $_SESSION['error'] = '⚠️ লোনের পরিমাণ সঠিকভাবে লিখুন।';
    header('Location: ../add.php');
    exit;
}
if ($account_id <= 0) {
    $_SESSION['error'] = '⚠️ যে অ্যাকাউন্টে টাকা এসেছে তা নির্বাচন করুন।';
    header('Location: ../add.php');
    exit;
}
if ($interest_rate < 0 || $interest_rate > 100) {
    $_SESSION['error'] = '⚠️ সুদের হার সঠিক নয়।';
    header('Location: ../add.php');
    exit;
}

$transaction_started = false;

try {
    $db->begin_transaction();
    $transaction_started = true;

    $user_id = (int)($_SESSION['user_id'] ?? 0);

    $stmt = $db->prepare(
        "INSERT INTO bank_loans (loan_name, bank_name, loan_type, principal_amount, interest_rate, loan_date, account_id, tenure_months, installment_count, installment_amount, first_installment_date, last_installment_date, processing_fee, other_charges, notes, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)"
    );
    // টাইপ: s(loan_name) s(bank_name) s(loan_type) d(principal_amount) d(interest_rate) s(loan_date) i(account_id) i(tenure_months) i(installment_count) d(installment_amount) s(first_date) s(last_date) d(processing_fee) d(other_charges) s(notes) i(created_by) = ১৬টি
    $stmt->bind_param(
        'sssddsiiidssddsi',
        $loan_name, $bank_name, $loan_type, $principal_amount, $interest_rate, $loan_date, $account_id, $tenure_months, $installment_count, $installment_amount, $first_installment_date, $last_installment_date, $processing_fee, $other_charges, $notes, $user_id
    );
    $stmt->execute();
    $loan_id = $stmt->insert_id;
    $stmt->close();

    // ================= ব্যাংক অ্যাকাউন্টে টাকা যোগ (স্বয়ংক্রিয় সংযোগ — মূল বৈশিষ্ট্য) =================
    account_credit($account_id, $principal_amount, 'loan_disbursement', $loan_id, 'লোন গ্রহণ — ' . $bank_name . ' (' . $loan_name . ')');

    // ================= প্রসেসিং ফি/অন্যান্য চার্জ থাকলে সেই অ্যাকাউন্ট থেকেই কেটে নেওয়া =================
    $total_charges = $processing_fee + $other_charges;
    if ($total_charges > 0) {
        account_debit($account_id, $total_charges, 'loan_charge', $loan_id, 'লোনের প্রসেসিং ফি/চার্জ — ' . $bank_name, true);
    }

    // ================= লেনদেনের ইতিহাসে "লোন গ্রহণ" রেকর্ড =================
    $txn_stmt = $db->prepare(
        "INSERT INTO loan_transactions (loan_id, txn_type, txn_date, total_amount, principal_part, account_id, note, created_by)
         VALUES (?, 'disbursement', ?, ?, ?, ?, ?, ?)"
    );
    $note = 'লোন গ্রহণ করা হয়েছে';
    $txn_stmt->bind_param('isddisi', $loan_id, $loan_date, $principal_amount, $principal_amount, $account_id, $note, $user_id);
    $txn_stmt->execute();

    // ================= কিস্তির সময়সূচি তৈরি =================
    if ($installment_count > 0 && $installment_amount > 0 && $first_installment_date) {
        generate_installment_schedule($db, $loan_id, $installment_count, $installment_amount, $first_installment_date);
    }

    log_activity('Loan Added', 'Loans', $loan_name . ' — ' . $bank_name . ' — ৳' . $principal_amount);

    $db->commit();
    $transaction_started = false;

    $_SESSION['success'] = 'লোন সফলভাবে সংরক্ষণ করা হয়েছে — ব্যাংক অ্যাকাউন্টে ৳' . number_format($principal_amount, 2) . ' যোগ হয়েছে।';
    header('Location: ../view.php?id=' . $loan_id);
    exit;
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Loan save failed: ' . $e->getMessage());
    $_SESSION['error'] = '⚠️ লোন সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../add.php');
    exit;
}
