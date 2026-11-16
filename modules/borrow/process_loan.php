<?php
require_once '../../includes/config.php';
require_once '../../includes/borrow_functions.php';
require_once '../../includes/account_functions.php';
ame_borrow_ensure($db);
ame_borrow_require($db);
require_post();
csrf_verify();

$name = clean_input($_POST['provider_name'] ?? '');
$type = clean_input($_POST['provider_type'] ?? 'bank');
if (!in_array($type, ['bank', 'ngo', 'financial', 'individual'], true)) {
    $type = 'bank';
}
$style = $type === 'individual' ? 'service_fee' : 'installment';
$principal = round((float)($_POST['principal'] ?? 0), 2);
$rate = round((float)($_POST['interest_rate'] ?? 0), 2);
$fee = round((float)($_POST['processing_fee'] ?? 0), 2);
$count = max(0, (int)($_POST['installment_count'] ?? 0));
$frequency = ($_POST['frequency'] ?? '') === 'weekly' ? 'weekly' : 'monthly';
$monthly_fee = round((float)($_POST['monthly_fee'] ?? 0), 2);
$security = round((float)($_POST['security_amount'] ?? 0), 2);
$account_id = (int)($_POST['account_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($name === '' || $principal <= 0 || $account_id <= 0) {
    die('নাম, মূল টাকা ও অ্যাকাউন্ট লাগবে।');
}
$net = round($principal - $fee, 2);
if ($net <= 0) {
    die('প্রসেসিং ফি মূল টাকার চেয়ে কম হতে হবে।');
}

$db->begin_transaction();
try {
    $stmt = $db->prepare('INSERT INTO biz_loans (provider_name, provider_type, repayment_style, branch, phone, account_no, loan_ref, contact_person, agreement_date, start_date, maturity_date, notes, principal, interest_rate, processing_fee, frequency, installment_count, monthly_fee, first_payment_date, principal_outstanding, security_held, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"active",?)');
    $branch = clean_input($_POST['branch'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $account_no = clean_input($_POST['account_no'] ?? '');
    $loan_ref = clean_input($_POST['loan_ref'] ?? '');
    $contact = clean_input($_POST['contact_person'] ?? '');
    $agreement = clean_input($_POST['agreement_date'] ?? '');
    $start = clean_input($_POST['start_date'] ?? date('Y-m-d'));
    if ($start === '') {
        $start = date('Y-m-d');
    }
    if ($agreement === '') {
        $agreement = $start;
    }
    $maturity = clean_input($_POST['maturity_date'] ?? '');
    if ($maturity === '') {
        $maturity = $start;
    }
    $notes = clean_input($_POST['notes'] ?? '');
    $first = clean_input($_POST['first_payment_date'] ?? $start);
    $stmt->bind_param(
        'ssssssssssssdddsidsddi',
        $name, $type, $style, $branch, $phone, $account_no, $loan_ref, $contact, $agreement, $start, $maturity, $notes,
        $principal, $rate, $fee, $frequency, $count, $monthly_fee, $first, $principal, $security, $user_id
    );
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    if ($style === 'installment') {
        ame_borrow_schedule($db, $id, $principal, $rate, $count, $frequency, $first);
    }
    $txn = $db->prepare('INSERT INTO biz_loan_txns (loan_id, txn_date, kind, principal_part, total, method, account_id, notes, created_by) VALUES (?,?,"receive",?,?, "cash", ?, "ধার গ্রহণ", ?)');
    $today = date('Y-m-d');
    $txn->bind_param('isddii', $id, $today, $principal, $net, $account_id, $user_id);
    $txn->execute();
    $txn->close();
    account_credit($account_id, $net, 'biz_loan_receive', $id, 'ব্যবসার ধার: ' . $name);
    if ($security > 0.009) {
        account_debit($account_id, $security, 'biz_loan_security', $id, 'জামানত: ' . $name);
    }
    $db->commit();
    log_activity('Business loan received', 'borrow', 'Loan #' . $id . ' ' . $name . ' principal ' . $principal);
    header('Location: ledger.php?id=' . $id);
    exit;
} catch (Throwable $e) {
    $db->rollback();
    die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
