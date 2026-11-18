<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/loan_functions.php';
admin_only();
ensure_bank_loan_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../list.php');
    exit;
}
csrf_verify();

$loan_id = (int)($_POST['loan_id'] ?? 0);
$installment_id = !empty($_POST['installment_id']) ? (int)$_POST['installment_id'] : null;
$total_amount = round((float)($_POST['total_amount'] ?? 0), 2);
$principal_part = round((float)($_POST['principal_part'] ?? 0), 2);
$interest_part = round((float)($_POST['interest_part'] ?? 0), 2);
$payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
$account_id = (int)($_POST['account_id'] ?? 0);
$reference_no = substr(trim($_POST['reference_no'] ?? ''), 0, 100);
$note = substr(trim($_POST['note'] ?? ''), 0, 255);

if ($loan_id <= 0) {
    header('Location: ../list.php');
    exit;
}
if ($total_amount <= 0) {
    $_SESSION['error'] = '⚠️ পরিশোধের পরিমাণ সঠিকভাবে লিখুন।';
    header('Location: ../pay.php?id=' . $loan_id);
    exit;
}
if ($account_id <= 0) {
    $_SESSION['error'] = '⚠️ কোন অ্যাকাউন্ট থেকে টাকা দেওয়া হয়েছে তা নির্বাচন করুন।';
    header('Location: ../pay.php?id=' . $loan_id);
    exit;
}
// আসল + সুদ = মোট পরিমাণের সাথে না মিললে (সামান্য রাউন্ডিং সহনীয়) সংশোধন
if (abs(($principal_part + $interest_part) - $total_amount) > 0.02) {
    $principal_part = $total_amount - $interest_part;
    if ($principal_part < 0) {
        $_SESSION['error'] = '⚠️ আসল ও সুদের অংশ মোট পরিমাণের সাথে মিলছে না।';
        header('Location: ../pay.php?id=' . $loan_id);
        exit;
    }
}

$transaction_started = false;

try {
    $stmt = $db->prepare("SELECT * FROM bank_loans WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $loan_id);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();

    if (!$loan) {
        $_SESSION['error'] = 'লোন খুঁজে পাওয়া যায়নি।';
        header('Location: ../list.php');
        exit;
    }

    $remaining = (float)$loan['principal_amount'] - (float)$loan['paid_principal'];
    if ($principal_part > $remaining + 0.02) {
        $_SESSION['error'] = '⚠️ আসলের অংশ অবশিষ্ট বকেয়ার (৳' . number_format($remaining, 2) . ') চেয়ে বেশি হতে পারবে না।';
        header('Location: ../pay.php?id=' . $loan_id);
        exit;
    }

    $db->begin_transaction();
    $transaction_started = true;

    $user_id = (int)($_SESSION['user_id'] ?? 0);

    // ================= ব্যাংক অ্যাকাউন্ট থেকে টাকা কাটা (স্বয়ংক্রিয় সংযোগ) =================
    account_debit($account_id, $total_amount, 'loan_installment', $loan_id, 'লোন কিস্তি পরিশোধ — ' . $loan['bank_name'] . ' (' . $loan['loan_name'] . ')');

    // ================= লোনের বকেয়া আপডেট =================
    $upd = $db->prepare("UPDATE bank_loans SET paid_principal = paid_principal + ?, updated_at = NOW() WHERE id = ?");
    $upd->bind_param('di', $principal_part, $loan_id);
    $upd->execute();

    // ================= লেনদেনের ইতিহাস =================
    $txn_stmt = $db->prepare(
        "INSERT INTO loan_transactions (loan_id, txn_type, txn_date, total_amount, principal_part, interest_part, account_id, reference_no, note, created_by)
         VALUES (?, 'installment', ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    // টাইপ: i s d d d i s s i = "isdddissi" (৯টি)
    $txn_stmt->bind_param('isdddissi', $loan_id, $payment_date, $total_amount, $principal_part, $interest_part, $account_id, $reference_no, $note, $user_id);
    $txn_stmt->execute();

    // ================= কিস্তি "পরিশোধিত" চিহ্নিত =================
    if ($installment_id) {
        $inst_upd = $db->prepare("UPDATE loan_installments SET status = 'paid', paid_date = ? WHERE id = ? AND loan_id = ?");
        $inst_upd->bind_param('sii', $payment_date, $installment_id, $loan_id);
        $inst_upd->execute();
    }

    // ================= লোন সম্পূর্ণ পরিশোধ হয়ে গেলে স্বয়ংক্রিয়ভাবে বন্ধ =================
    $new_paid = (float)$loan['paid_principal'] + $principal_part;
    if ($new_paid >= (float)$loan['principal_amount'] - 0.02) {
        $close_stmt = $db->prepare("UPDATE bank_loans SET status = 'completed', closed_date = ? WHERE id = ?");
        $close_stmt->bind_param('si', $payment_date, $loan_id);
        $close_stmt->execute();
    }

    log_activity('Loan Installment Paid', 'Loans', $loan['loan_name'] . ' — ৳' . $total_amount);

    $db->commit();
    $transaction_started = false;

    $_SESSION['success'] = 'কিস্তি সফলভাবে পরিশোধ হয়েছে — ৳' . number_format($total_amount, 2);
    header('Location: ../view.php?id=' . $loan_id);
    exit;
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Loan payment failed: ' . $e->getMessage());
    $_SESSION['error'] = '⚠️ পরিশোধে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../pay.php?id=' . $loan_id);
    exit;
}
