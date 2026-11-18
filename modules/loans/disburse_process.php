<?php
require_once '../../includes/config.php';
require_once '../../includes/loan_schema.php';
require_once '../../includes/account_functions.php';
admin_only();
csrf_verify();
ensure_loan_tables($db);

function disburse_fail($application_id, $msg) {
    header("Location: disburse.php?application_id=" . (int)$application_id . "&error=" . urlencode($msg));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: applications_list.php");
    exit();
}

$application_id = (int)($_POST['application_id'] ?? 0);
$disburse_amount = (float)($_POST['disburse_amount'] ?? 0);
$term_months = (int)($_POST['term_months'] ?? 0);
$disburse_date = $_POST['disburse_date'] ?? date('Y-m-d');
$account_id = (int)($_POST['account_id'] ?? 0);
$disbursed_by = $_SESSION['user_id'] ?? null;

if ($disburse_amount <= 0 || $term_months <= 0 || $account_id <= 0) {
    disburse_fail($application_id, 'সব আবশ্যক তথ্য সঠিকভাবে পূরণ করুন।');
}

$stmt = $db->prepare("
    SELECT la.*, c.name AS customer_name, lp.interest_rate, lp.interest_type, lp.processing_fee_percent
    FROM loan_applications la
    JOIN customers c ON c.id = la.customer_id
    JOIN loan_products lp ON lp.id = la.product_id
    WHERE la.id = ? AND la.status = 'approved'
");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    disburse_fail($application_id, 'আবেদনটি ছাড়ের জন্য প্রস্তুত নয়।');
}

$db->begin_transaction();
try {
    // ১. loans টেবিলে মূল রেকর্ড তৈরি — 'given' মানে দোকান থেকে গ্রাহককে ধার দেওয়া হচ্ছে
    $ins = $db->prepare("INSERT INTO loans
        (person_name, type, amount, paid_amount, status, customer_id, product_id, application_id,
         interest_rate, interest_type, term_months, disbursed_at, disbursed_by, disbursement_account_id, next_due_date)
        VALUES (?, 'given', ?, 0, 'open', ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL 1 MONTH))");
    $disbursed_at = date('Y-m-d H:i:s');
    $ins->bind_param(
        "sdiiidsisiis",
        $app['customer_name'], $disburse_amount, $app['customer_id'], $app['product_id'], $application_id,
        $app['interest_rate'], $app['interest_type'], $term_months, $disbursed_at, $disbursed_by, $account_id, $disburse_date
    );
    $ins->execute();
    $loan_id = $db->insert_id;

    // ২. প্রসেসিং ফি হিসাব — গ্রাহক পুরো disburse_amount-ই ঋণ হিসেবে ফেরত
    //    দেবে, কিন্তু ফি বাদ দিয়ে বাকিটাই হাতে নগদ পাবে (ফি দোকানের সাথে সাথে
    //    আয় হয়ে যায়, তাই অ্যাকাউন্ট থেকে ফি বাদে বাকি টাকাই কাটা হচ্ছে)
    $fee_percent = (float)($app['processing_fee_percent'] ?? 0);
    $processing_fee = $fee_percent > 0 ? round($disburse_amount * $fee_percent / 100, 2) : 0;
    $net_cash_out = $disburse_amount - $processing_fee;

    // ৩. অ্যাকাউন্ট থেকে টাকা কাটা (নগদ বের হচ্ছে) — ফি বাদ দিয়ে
    if ($net_cash_out > 0) {
        account_debit($account_id, $net_cash_out, 'loan_disbursement', $loan_id, 'লোন ছাড়: ' . $app['customer_name']);
    }

    if ($processing_fee > 0) {
        $fee_ins = $db->prepare("INSERT INTO loan_fees (loan_id, fee_type, amount, note, charged_by) VALUES (?, 'processing', ?, ?, ?)");
        $fee_note = 'ছাড়ের সময় প্রসেসিং ফি (' . $fee_percent . '%)';
        $fee_ins->bind_param("idsi", $loan_id, $processing_fee, $fee_note, $disbursed_by);
        $fee_ins->execute();
    }

    // ৪. রিপেমেন্ট শিডিউল তৈরি
    generate_flat_repayment_schedule($db, $loan_id, $disburse_amount, (float)$app['interest_rate'], $term_months, $disburse_date);

    // ৫. আবেদনের স্ট্যাটাস আপডেট
    $upd = $db->prepare("UPDATE loan_applications SET status = 'disbursed', resulting_loan_id = ? WHERE id = ?");
    $upd->bind_param("ii", $loan_id, $application_id);
    $upd->execute();

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    disburse_fail($application_id, 'ছাড় ব্যর্থ হয়েছে: ' . $e->getMessage());
}

log_activity('loan_disbursement', 'loans', 'লোন ছাড় করা হয়েছে: ' . $app['customer_name'] . ' — ' . $disburse_amount . ' ৳ (লোন #' . $loan_id . ')');

header("Location: pay.php?id=" . $loan_id . "&disbursed=1");
exit();
