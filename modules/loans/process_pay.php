<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/integrity_functions.php';
csrf_verify();
ensure_integrity_schema($db);

function collect_fail($loan_id, $msg) {
    header("Location: pay.php?id=" . (int)$loan_id . "&error=" . urlencode($msg));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: list.php");
    exit();
}

$loan_id = (int)($_POST['loan_id'] ?? 0);
$pay_amount = (float)($_POST['pay_amount'] ?? 0);
$acc_id = (int)($_POST['account_id'] ?? 0);

if ($pay_amount <= 0 || $acc_id <= 0) {
    collect_fail($loan_id, 'সঠিক পরিমাণ ও অ্যাকাউন্ট নির্বাচন করুন।');
}

$loan_token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);
ame_lock($db, 'loan_pay_' . $loan_id);
$loan_replay = 0;
if ($loan_token !== '' && !claim_idempotency_token($db, 'loan_pay', $loan_token, $loan_replay)) {
    collect_fail($loan_id, 'এই পেমেন্ট আগেই দেওয়া হয়েছে।');
}

$stmt = $db->prepare("SELECT * FROM loans WHERE id = ?");
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) {
    collect_fail($loan_id, 'লোনটি খুঁজে পাওয়া যায়নি।');
}

$db->begin_transaction();
try {
    // ১. লোন টেবিলে সামগ্রিক (aggregate) আপডেট — সব ধরনের লোনের জন্য (নতুন
    //    স্কিমা-ভিত্তিক ও পুরনো সাধারণ লোন, দুটোতেই কাজ করে)
    $upd = $db->prepare("UPDATE loans SET paid_amount = paid_amount + ? WHERE id = ?");
    $upd->bind_param("di", $pay_amount, $loan_id);
    $upd->execute();

    // ২. যদি এই লোনের একটা আনুষ্ঠানিক রিপেমেন্ট শিডিউল থাকে (product_id সেট করা
    //    থাকলে), তাহলে টাকাটা সবচেয়ে পুরনো বকেয়া কিস্তি থেকে শুরু করে ধারাবাহিকভাবে
    //    বণ্টন করা হয় — এটাই আসল "Collections" লজিক, আগে এটা করা হতোই না
    if (!empty($loan['product_id'])) {
        $remaining = $pay_amount;

        $sched_stmt = $db->prepare("
            SELECT id, due_amount, paid_amount FROM loan_repayment_schedule
            WHERE loan_id = ? AND status IN ('pending','partial')
            ORDER BY installment_no ASC
            FOR UPDATE
        ");
        $sched_stmt->bind_param("i", $loan_id);
        $sched_stmt->execute();
        $installments = $sched_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($installments as $inst) {
            if ($remaining <= 0.0001) break;

            $due_left = round((float)$inst['due_amount'] - (float)$inst['paid_amount'], 2);
            if ($due_left <= 0) continue;

            $applied = min($remaining, $due_left);
            $new_paid = round((float)$inst['paid_amount'] + $applied, 2);
            $new_status = ($new_paid >= (float)$inst['due_amount'] - 0.0001) ? 'paid' : 'partial';

            $inst_upd = $db->prepare("UPDATE loan_repayment_schedule SET paid_amount = ?, status = ? WHERE id = ?");
            $inst_upd->bind_param("dsi", $new_paid, $new_status, $inst['id']);
            $inst_upd->execute();

            $remaining = round($remaining - $applied, 2);
        }
        // বি.দ্র.: $remaining যদি এখনো বাকি থাকে (সব কিস্তির চেয়ে বেশি টাকা দেওয়া
        // হয়েছে), সেটা এই মুহূর্তে অগ্রিম হিসেবে শুধু loans.paid_amount-এ যোগ
        // হয়ে থাকে (উপরে ধাপ ১-এ) — ভবিষ্যতে চাইলে এটাকে পরের কিস্তির অগ্রিম
        // হিসেবে দেখানোর একটা আলাদা ফিচার যোগ করা যাবে।

        // পরবর্তী বকেয়া কিস্তির তারিখ দিয়ে next_due_date আপডেট
        $next_stmt = $db->prepare("SELECT MIN(due_date) AS nd FROM loan_repayment_schedule WHERE loan_id = ? AND status IN ('pending','partial')");
        $next_stmt->bind_param("i", $loan_id);
        $next_stmt->execute();
        $next_due = $next_stmt->get_result()->fetch_assoc()['nd'] ?? null;
        $due_upd = $db->prepare("UPDATE loans SET next_due_date = ? WHERE id = ?");
        $due_upd->bind_param("si", $next_due, $loan_id);
        $due_upd->execute();
    }

    // ৩. যদি লোন টাইপ 'taken' (দোকান ধার নিয়েছে), তবে ক্যাশ কমবে (Debit)
    //    যদি লোন টাইপ 'given' (গ্রাহককে ধার দেওয়া হয়েছিল), তবে ক্যাশ বাড়বে (Credit)
    if ($loan['type'] === 'taken') {
        account_debit($acc_id, $pay_amount, 'loan_pay', $loan_id, "লোন পরিশোধ: " . $loan['person_name']);
    } else {
        account_credit($acc_id, $pay_amount, 'loan_receive', $loan_id, "কিস্তি আদায়: " . $loan['person_name']);
    }

    $db->commit();
    if (!empty($loan_token)) {
        finish_idempotency_token($db, 'loan_pay', $loan_token, $loan_id);
    }
} catch (Throwable $e) {
    $db->rollback();
    collect_fail($loan_id, 'লেনদেন ব্যর্থ হয়েছে: ' . $e->getMessage());
}

log_activity('loan_collection', 'loans', 'কিস্তি/লেনদেন গ্রহণ করা হয়েছে: ' . $loan['person_name'] . ' — ' . number_format($pay_amount, 2) . ' ৳ (লোন #' . $loan_id . ')');

header("Location: pay.php?id=" . $loan_id . "&paid=1");
exit();
