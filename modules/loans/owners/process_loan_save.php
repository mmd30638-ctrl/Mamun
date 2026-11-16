<?php
require_once '../../../includes/config.php';
require_once '../../../includes/owner_loan_functions.php';
ame_oloan_require_access($db);
require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$owner_id = (int)($_POST['owner_id'] ?? 0);
$bank_id = isset($_POST['bank_id']) && $_POST['bank_id'] !== '' ? (int)$_POST['bank_id'] : null;
$loan_type = clean_input($_POST['loan_type'] ?? '');
$loan_purpose = clean_input($_POST['loan_purpose'] ?? '');
$approved_amount = round((float)($_POST['approved_amount'] ?? 0), 2);
$interest_rate = round((float)($_POST['interest_rate'] ?? 0), 2);
$loan_term_months = (int)($_POST['loan_term_months'] ?? 0);
$installment_count = max(1, (int)($_POST['installment_count'] ?? 0));
$installment_frequency = clean_input($_POST['installment_frequency'] ?? 'monthly');
if ($installment_frequency === '') { $installment_frequency = 'monthly'; }
$approval_date = clean_input($_POST['approval_date'] ?? date('Y-m-d'));
if ($approval_date === '') { $approval_date = date('Y-m-d'); }
$status = clean_input($_POST['status'] ?? 'pending');
$allowed_status = ['pending', 'approved', 'active', 'completed', 'overdue', 'cancelled'];
if (!in_array($status, $allowed_status, true)) { $status = 'pending'; }

if ($owner_id <= 0 || !ame_oloan_owner_fetch($db, $owner_id)) {
    redirect('loan_add.php?err=' . rawurlencode(ame_l('দোকান মালিক নির্বাচন করুন।', 'Please select a shop owner.')));
}
if ($approved_amount <= 0) {
    redirect('loan_add.php?' . ($id > 0 ? 'id=' . $id . '&' : '') . 'err=' . rawurlencode(ame_l('অনুমোদিত পরিমাণ সঠিক নয়।', 'Approved amount is invalid.')));
}

$calc = ame_owner_loan_calc($approved_amount, $interest_rate, $installment_count);
$total_payable = $calc['total_payable'];
$installment_amount = $calc['installment_amount'];

if ($id > 0) {
    $existing = ame_oloan_loan_fetch($db, $id);
    if (!$existing) {
        redirect('list.php?err=' . rawurlencode(ame_l('ঋণ খুঁজে পাওয়া যায়নি।', 'Loan not found.')));
    }
    $paid_amount = round((float)$existing['paid_amount'], 2);
    $outstanding_amount = round($total_payable - $paid_amount, 2);

    $stmt = $db->prepare("UPDATE owner_loans SET bank_id=?, loan_type=?, loan_purpose=?, approved_amount=?, interest_rate=?, loan_term_months=?, installment_count=?, installment_frequency=?, installment_amount=?, approval_date=?, total_payable=?, outstanding_amount=?, status=?, updated_at=NOW() WHERE id=?");
    // টাইপ: i(bank) s(type) s(purpose) d(amount) d(rate) i(term) i(count) s(freq) d(inst_amt) s(date) d(payable) d(outstanding) s(status) i(id) = "issddiisdsddsi" (১৪টি)
    $stmt->bind_param('issddiisdsddsi', $bank_id, $loan_type, $loan_purpose, $approved_amount, $interest_rate, $loan_term_months, $installment_count, $installment_frequency, $installment_amount, $approval_date, $total_payable, $outstanding_amount, $status, $id);
    $stmt->execute();
    $stmt->close();

    // স্ট্যাটাস pending/approved থেকে approved/active এ গেলে (এবং এখনো শিডিউল না থাকলে) শিডিউল রি-জেনারেট
    if (in_array($status, ['approved', 'active'], true)) {
        ame_owner_loan_generate_schedule($db, $id, $approved_amount, $interest_rate, $installment_count, $installment_frequency, $approval_date);
        ame_owner_loan_recalc_installment_statuses($db, $id);
    }

    log_activity('Owner Loan Updated', 'owner_loans', "ঋণ #$id আপডেট করা হলো — পরিমাণ: {$approved_amount}, স্ট্যাটাস: {$status}");
    redirect('loan_view.php?id=' . $id . '&saved=1');
} else {
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $outstanding_amount = $total_payable;

    $stmt = $db->prepare("INSERT INTO owner_loans
        (owner_id, bank_id, loan_type, loan_purpose, approved_amount, interest_rate, loan_term_months, installment_count, installment_frequency, installment_amount, approval_date, total_payable, paid_amount, outstanding_amount, status, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?)");
    // টাইপ: i(owner) i(bank) s(type) s(purpose) d(amount) d(rate) i(term) i(count) s(freq) d(inst_amt) s(date) d(payable) d(outstanding) s(status) i(created_by) = "iissddiisdsddsi" (১৫টি)
    $stmt->bind_param('iissddiisdsddsi', $owner_id, $bank_id, $loan_type, $loan_purpose, $approved_amount, $interest_rate, $loan_term_months, $installment_count, $installment_frequency, $installment_amount, $approval_date, $total_payable, $outstanding_amount, $status, $user_id);
    $stmt->execute();
    $new_id = $db->insert_id;
    $stmt->close();

    if (in_array($status, ['approved', 'active'], true)) {
        ame_owner_loan_generate_schedule($db, $new_id, $approved_amount, $interest_rate, $installment_count, $installment_frequency, $approval_date);
        ame_owner_loan_recalc_installment_statuses($db, $new_id);
    }

    log_activity('Owner Loan Created', 'owner_loans', "নতুন ঋণ তৈরি: #$new_id (মালিক #$owner_id, পরিমাণ: {$approved_amount})");
    redirect('loan_view.php?id=' . $new_id . '&saved=1');
}
