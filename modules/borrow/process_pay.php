<?php
require_once '../../includes/config.php';
require_once '../../includes/borrow_functions.php';
require_once '../../includes/account_functions.php';
ame_borrow_ensure($db);
ame_borrow_require($db);
require_post();
csrf_verify();

$loan_id = (int)($_POST['loan_id'] ?? 0);
$loan = ame_borrow_fetch($db, $loan_id);
if (!$loan) {
    header('Location: pay.php?err=' . rawurlencode('ধার পাওয়া যায়নি'));
    exit;
}
$kind = clean_input($_POST['kind'] ?? '');
$principal_part = round((float)($_POST['principal_part'] ?? 0), 2);
$interest_part = round((float)($_POST['interest_part'] ?? 0), 2);
$fee_part = round((float)($_POST['fee_part'] ?? 0), 2);
$savings_part = round((float)($_POST['savings_part'] ?? 0), 2);
$security_part = round((float)($_POST['security_part'] ?? 0), 2);
$account_id = (int)($_POST['account_id'] ?? 0);
$method = clean_input($_POST['method'] ?? 'cash');
$ref = clean_input($_POST['reference_no'] ?? '');
$notes = clean_input($_POST['notes'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($loan['repayment_style'] === 'service_fee' && $kind === 'fee') {
    $principal_part = 0;
}
if ($kind === 'installment') {
    $total = round($principal_part + $interest_part + $fee_part, 2);
} elseif ($kind === 'fee') {
    $total = $fee_part;
    $principal_part = 0;
    $interest_part = 0;
} elseif ($kind === 'principal_settlement') {
    $total = $principal_part;
} elseif ($kind === 'savings') {
    $total = $savings_part;
} elseif ($kind === 'security_in' || $kind === 'security_out') {
    $total = $security_part;
} else {
    header('Location: pay.php?loan_id=' . $loan_id . '&err=' . rawurlencode('পেমেন্টের ধরন সঠিক নয়'));
    exit;
}
if ($total <= 0 || $account_id <= 0) {
    header('Location: pay.php?loan_id=' . $loan_id . '&err=' . rawurlencode('টাকার পরিমাণ দিন'));
    exit;
}

$db->begin_transaction();
try {
    $outstanding = round((float)$loan['principal_outstanding'], 2);
    $repaid = round((float)$loan['principal_repaid'], 2);
    $interest_paid = round((float)$loan['interest_paid_total'] + $interest_part, 2);
    $fee_paid = round((float)$loan['fee_paid_total'] + ($kind === 'fee' || $kind === 'installment' ? $fee_part : 0), 2);
    $savings_paid = round((float)$loan['savings_paid_total'] + ($kind === 'savings' ? $savings_part : 0), 2);
    $security = round((float)$loan['security_held'], 2);
    if ($kind === 'security_in') {
        $security = round($security + $security_part, 2);
    }
    if ($kind === 'security_out') {
        if ($security_part > $security + 0.009) {
            throw new Exception('জামানতের চেয়ে বেশি ফেরত দেওয়া যাবে না।');
        }
        $security = round($security - $security_part, 2);
    }
    if ($kind === 'installment' || $kind === 'principal_settlement') {
        if ($principal_part > $outstanding + 0.009) {
            throw new Exception('মূল বাকির চেয়ে বেশি ফেরত দেওয়া যাবে না।');
        }
        $outstanding = round($outstanding - $principal_part, 2);
        $repaid = round($repaid + $principal_part, 2);
    }
    $status = $outstanding <= 0.009 ? 'closed' : 'active';
    $upd = $db->prepare('UPDATE biz_loans SET principal_outstanding=?, principal_repaid=?, interest_paid_total=?, fee_paid_total=?, savings_paid_total=?, security_held=?, status=? WHERE id=?');
    $upd->bind_param('ddddddsi', $outstanding, $repaid, $interest_paid, $fee_paid, $savings_paid, $security, $status, $loan_id);
    $upd->execute();
    $upd->close();

    $today = date('Y-m-d');
    $ins = $db->prepare('INSERT INTO biz_loan_txns (loan_id, txn_date, kind, principal_part, interest_part, fee_part, savings_part, security_part, total, method, account_id, reference_no, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $ins->bind_param('issddddddsissi', $loan_id, $today, $kind, $principal_part, $interest_part, $fee_part, $savings_part, $security_part, $total, $method, $account_id, $ref, $notes, $user_id);
    $ins->execute();
    $txn_id = (int)$db->insert_id;
    $ins->close();

    if ($kind === 'security_out') {
        account_credit($account_id, $total, 'biz_loan_security_return', $txn_id, 'জামানত ফেরত');
    } else {
        account_debit($account_id, $total, 'biz_loan_payment', $txn_id, 'ধার পরিশোধ ' . $kind);
    }
    $db->commit();
    log_activity('Business loan payment', 'borrow', 'Loan #' . $loan_id . ' ' . $kind . ' ' . $total);
    header('Location: receipt.php?id=' . $txn_id);
    exit;
} catch (Throwable $e) {
    $db->rollback();
    header('Location: pay.php?loan_id=' . $loan_id . '&err=' . rawurlencode($e->getMessage()));
    exit;
}
