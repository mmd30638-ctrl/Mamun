<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../customer_schema.php';
require_once __DIR__ . '/../customer_ledger_functions.php';
ensure_customer_tables($db);

$id = (int)($_POST['id'] ?? 0);
$back = (string)($_POST['back'] ?? '');
if ($back !== '' && !preg_match('/^[A-Za-z0-9_%=&.\-]*$/', $back)) {
    $back = '';
}
$fail = static function (string $message) use ($id, $back): void {
    $_SESSION['cpay_edit_error'] = $message;
    header('Location: ../payment-edit.php?id=' . $id . ($back !== '' ? '&back=' . rawurlencode($back) : ''));
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $id <= 0) {
    header('Location: payments-list.php');
    exit;
}
$session_token = (string)($_SESSION['csrf_token'] ?? '');
$posted_token = (string)($_POST['csrf_token'] ?? '');
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    $fail('নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করে আবার সেভ করুন।');
}

$amount = round((float)($_POST['amount'] ?? 0), 2);
$discount = round((float)($_POST['discount'] ?? 0), 2);
$payment_date = trim((string)($_POST['payment_date'] ?? ''));
$method = trim((string)($_POST['method'] ?? 'CASH'));
$reference_no = trim((string)($_POST['reference_no'] ?? ''));
$note = trim((string)($_POST['note'] ?? ''));
$account_id = (int)($_POST['account_id'] ?? 0);
$edit_token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);
if ($amount < 0) {
    $amount = 0.0;
}
if ($discount < 0) {
    $discount = 0.0;
}
$relief = round($amount + $discount, 2);
$allowed = ['CASH', 'BKASH', 'NAGAD', 'BANK', 'CHEQUE'];
if (!in_array($method, $allowed, true)) {
    $method = 'CASH';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
    $fail('তারিখ সঠিক নয়।');
}
if ($relief <= 0) {
    $fail('পরিশোধ বা ডিসকাউন্ট শূন্য রাখা যায় না।');
}
if ($amount > 0 && $account_id <= 0) {
    $fail('টাকা যে অ্যাকাউন্টে জমা হবে সেটি বাছাই করুন।');
}
if ($amount > 0) {
    try {
        ame_require_selectable_account($db, $account_id);
    } catch (InvalidArgumentException $e) {
        $fail('সক্রিয় ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট বাছাই করুন।');
    }
}

$old_stmt = $db->prepare('SELECT * FROM customer_payments WHERE id = ?');
if (!$old_stmt) {
    $fail('পেমেন্ট খোলা যায়নি।');
}
$old_stmt->bind_param('i', $id);
$old_stmt->execute();
$old = $old_stmt->get_result()->fetch_assoc();
$old_stmt->close();
if (!$old) {
    $fail('পেমেন্ট খুঁজে পাওয়া যায়নি।');
}

$old_amount = round((float)$old['amount'], 2);
$old_discount = round((float)($old['discount'] ?? 0), 2);
$old_relief = round($old_amount + $old_discount, 2);
$old_account = (int)($old['account_id'] ?? 0);
$customer_id = (int)$old['customer_id'];
$ref = 'PAY-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);

$db->begin_transaction();
try {
    $edit_replay = 0;
    if ($edit_token !== ''
        && !claim_idempotency_token($db, 'customer_payment_edit', $edit_token, $edit_replay)) {
        $db->rollback();
        header('Location: ../receipt.php?payment_id=' . $id);
        exit();
    }

    $locked_old = $db->prepare('SELECT * FROM customer_payments WHERE id = ? FOR UPDATE');
    if (!$locked_old) {
        throw new RuntimeException('পেমেন্ট লক করা যায়নি।');
    }
    $locked_old->bind_param('i', $id);
    $locked_old->execute();
    $old = $locked_old->get_result()->fetch_assoc();
    $locked_old->close();
    if (!$old) {
        throw new RuntimeException('পেমেন্ট খুঁজে পাওয়া যায়নি।');
    }
    $old_amount = round((float)$old['amount'], 2);
    $old_discount = round((float)($old['discount'] ?? 0), 2);
    $old_relief = round($old_amount + $old_discount, 2);
    $old_account = (int)($old['account_id'] ?? 0);
    $customer_id = (int)$old['customer_id'];

    $upd = $db->prepare('UPDATE customer_payments SET amount = ?, discount = ?, payment_date = ?, method = ?, account_id = NULLIF(?, 0), reference_no = ?, note = ? WHERE id = ?');
    if (!$upd) {
        throw new RuntimeException('আপডেট প্রস্তুত হয়নি।');
    }
    $upd->bind_param('ddssissi', $amount, $discount, $payment_date, $method, $account_id, $reference_no, $note, $id);
    $upd->execute();
    $upd->close();

    $delta = round($relief - $old_relief, 2);
    if (abs($delta) >= 0.009) {
        $desc = 'পেমেন্ট সংশোধন ' . $ref;
        if ($delta > 0) {
            add_ledger_entry($db, $customer_id, 'PAYMENT', $ref . '-ADJ', $desc, 0, $delta);
        } else {
            add_ledger_entry($db, $customer_id, 'PAYMENT', $ref . '-ADJ', $desc, abs($delta), 0);
        }
    }

    if ($old_account === $account_id) {
        $cash_delta = round($amount - $old_amount, 2);
        if ($account_id > 0 && $cash_delta >= 0.009) {
            account_credit($account_id, $cash_delta, 'customer_payment_edit', $id, 'পেমেন্ট সংশোধন ' . $ref);
        } elseif ($account_id > 0 && $cash_delta <= -0.009) {
            account_debit($account_id, abs($cash_delta), 'customer_payment_edit', $id, 'পেমেন্ট সংশোধন ' . $ref, true);
        }
    } else {
        if ($old_account > 0 && $old_amount >= 0.009) {
            account_debit($old_account, $old_amount, 'customer_payment_edit', $id, 'পেমেন্ট অ্যাকাউন্ট বদল ' . $ref, true);
        }
        if ($account_id > 0 && $amount >= 0.009) {
            account_credit($account_id, $amount, 'customer_payment_edit', $id, 'পেমেন্ট অ্যাকাউন্ট বদল ' . $ref);
        }
    }

    if ($edit_token !== '') {
        finish_idempotency_token($db, 'customer_payment_edit', $edit_token, $id);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    error_log('payment edit failed: ' . $e->getMessage());
    $fail('সংশোধন সেভ হয়নি। টাকা আগের হিসাবেই আছে।');
}

if (function_exists('log_activity')) {
    log_activity('Customer Payment Edit', 'Customers', $ref . ' ৳' . number_format($amount, 2), [
        'action_type' => 'payment_edit',
        'entity_type' => 'payment',
        'entity_id' => (string)$id,
        'reference' => $ref,
        'party_type' => 'customer',
        'party_id' => $customer_id,
        'amount_paid' => $amount,
        'discount' => $discount,
    ]);
}

header('Location: ../receipt.php?payment_id=' . $id);
exit;
