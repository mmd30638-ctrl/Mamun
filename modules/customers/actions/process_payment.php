<?php
// নিরাপত্তা ফিক্স: আগে শুধু db.php কল হতো যা লগইন-গার্ড এড়িয়ে যেত।
// config.php ব্যবহার করলে গ্লোবাল লগইন-চেক (includes/config.php) স্বয়ংক্রিয়ভাবে কার্যকর হয়।
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/integrity_functions.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_integrity_schema($db);

try {
    ensure_customer_tables($db);
} catch (Throwable $e) {
    error_log('Payment schema error: ' . $e->getMessage());
    die('❌ সিস্টেম প্রস্তুতিতে সমস্যা হয়েছে। <a href="../payment-receive.php">ফিরে যান</a>');
}

function ensure_next_due_amount_column($db) {
    $check = $db->query("SHOW COLUMNS FROM customers LIKE 'next_due_amount'");
    if ($check && $check->num_rows === 0) {
        $db->query("ALTER TABLE customers ADD COLUMN next_due_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER next_due_date");
    }
}
try {
    ensure_next_due_amount_column($db);
} catch (Throwable $e) {
    error_log('next_due_amount column error: ' . $e->getMessage());
    die('❌ সিস্টেম প্রস্তুতিতে সমস্যা হয়েছে। <a href="../payment-receive.php">ফিরে যান</a>');
}

$wants_json = ((string)($_POST['ajax'] ?? '') === '1')
    || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

$pay_fail = static function (string $message) use ($wants_json): void {
    if ($wants_json) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    die('❌ ' . $message . ' <a href="../payment-receive.php">ফিরে যান</a>');
};

$pay_ok = static function (int $payment_id, bool $replayed = false) use ($wants_json): void {
    if ($wants_json) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'success',
            'payment_id' => $payment_id,
            'replayed' => $replayed,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ../receipt.php?payment_id=' . $payment_id);
    exit;
};

$pay_dup = static function (int $payment_id) use ($wants_json): void {
    $ref = 'PAY-' . str_pad((string)$payment_id, 6, '0', STR_PAD_LEFT);
    $message = 'একই কাস্টমারের এই পরিমাণ গত ২ মিনিটেই নেওয়া হয়েছে — আবার কাটেনি। আগের রসিদ ' . $ref . '। দুই মিনিট পর নতুন পেমেন্ট নিন, অথবা ভুল হলে সেই রসিদ থেকে বাতিল করুন।';
    if ($wants_json) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'error',
            'duplicate' => true,
            'payment_id' => $payment_id,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    die('❌ ' . $message . ' <a href="../receipt.php?payment_id=' . $payment_id . '">আগের রসিদ</a>');
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../payment-receive.php');
    exit;
}

$session_token = (string)($_SESSION['csrf_token'] ?? '');
$posted_token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    $pay_fail('নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
}

$customer_id   = (int)($_POST['customer_id'] ?? 0);
$amount        = round((float)($_POST['amount'] ?? 0), 2);
$discount      = round((float)($_POST['discount'] ?? 0), 2);
$payment_date  = trim($_POST['payment_date'] ?? date('Y-m-d'));
$method        = trim($_POST['method'] ?? 'CASH');
$reference_no  = trim($_POST['reference_no'] ?? '');
$note          = trim($_POST['note'] ?? '');
$account_raw = trim((string)($_POST['posted_account_id'] ?? ''));
if ($account_raw === '') {
    $account_raw = trim((string)($_POST['account_id'] ?? ''));
}
$account_id = ame_posted_shop_account_id($db, $account_raw);
if ($amount < 0) {
    $amount = 0.0;
}
if ($discount < 0) {
    $discount = 0.0;
}
$relief = round($amount + $discount, 2);

$allowed_methods = ['CASH', 'BKASH', 'NAGAD', 'BANK', 'CHEQUE'];
if (!in_array($method, $allowed_methods, true)) {
    $method = 'CASH';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
    $payment_date = date('Y-m-d');
}

$next_due_date_raw = trim($_POST['next_due_date'] ?? '');
$next_due_date      = $next_due_date_raw !== '' ? $next_due_date_raw : null;

$next_due_amount_raw = trim($_POST['next_due_amount'] ?? '');
$next_due_amount      = ($next_due_amount_raw !== '' && is_numeric($next_due_amount_raw))
    ? (float)$next_due_amount_raw
    : null;

$due_note_raw       = trim($_POST['due_note'] ?? '');
$due_note           = $due_note_raw !== '' ? $due_note_raw : null;
$notify_off         = !empty($_POST['notify_off']) && (string)$_POST['notify_off'] !== '0';

$token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);

if ($customer_id <= 0 || $relief <= 0) {
    $pay_fail('কাস্টমার ও সঠিক পরিমাণ আবশ্যক।');
}
if ($amount > 0 && $account_id <= 0) {
    $pay_fail('টাকা যে অ্যাকাউন্টে জমা হবে সেটি বাছাই করুন।');
}
if ($amount > 0) {
    try {
        ame_require_selectable_account($db, $account_id);
    } catch (InvalidArgumentException $e) {
        $pay_fail('সক্রিয় ক্যাশ, ব্যাংক বা মোবাইল অ্যাকাউন্ট বাছাই করুন।');
    }
} else {
    $account_id = 0;
}

$chk = $db->prepare("SELECT id, name, status FROM customers WHERE id = ?");
if (!$chk) { $pay_fail('কাস্টমার যাচাই করা যায়নি।'); }
$chk->bind_param("i", $customer_id);
$chk->execute();
$chkRow = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$chkRow) {
    $pay_fail('কাস্টমার খুঁজে পাওয়া যায়নি।');
}
if (function_exists('ame_customer_row_operable') && !ame_customer_row_operable($chkRow)) {
    $pay_fail('বন্ধ কাস্টমারের পেমেন্ট নেওয়া যায় না। কাস্টমার তালিকা থেকে চালু করুন।');
}
$party_name = (string)($chkRow['name'] ?? '');

if (function_exists('ame_pay_sec_verify')) {
    try {
        ame_pay_sec_verify('customer_pay', 0, ame_pay_sec_posted());
    } catch (Throwable $e) {
        $pay_fail($e->getMessage());
    }
}

ame_lock($db, 'cust_pay_' . $customer_id);

$db->begin_transaction();
try {
    $taken = ame_customer_take_payment($db, [
        'customer_id' => $customer_id,
        'amount' => $amount,
        'discount' => $discount,
        'payment_date' => $payment_date,
        'method' => $method,
        'account_id' => $account_id,
        'reference_no' => $reference_no,
        'note' => $note,
        'token' => $token,
        'notify_off' => $notify_off,
        'next_due_date' => $next_due_date,
        'next_due_amount' => $next_due_amount,
        'due_note' => $due_note,
        'post_account' => false,
    ]);
    $payment_id = (int)$taken['id'];
    $ref = 'PAY-' . str_pad((string)$payment_id, 6, '0', STR_PAD_LEFT);
    if ($amount > 0 && empty($taken['replayed']) && empty($taken['duplicate'])) {
        $posted = ame_post_account(
            $db,
            'receive',
            $account_id,
            $amount,
            'customer_payment',
            $payment_id,
            'পেমেন্ট গৃহীত (' . $method . ') — ' . $ref,
            true
        );
        if (!$posted) {
            throw new RuntimeException('অ্যাকাউন্টে জমা হয়নি।');
        }
    }
    $db->commit();
    if (!empty($taken['duplicate'])) {
        $pay_dup($payment_id);
    }
    if (!empty($taken['replayed'])) {
        $pay_ok($payment_id, true);
    }
    try {
        require_once __DIR__ . '/../../../includes/provenance_functions.php';
        ame_provenance_record($db, 'payment', (int)$payment_id, [
            'amount' => (float)$amount,
            'label' => 'PAY-' . str_pad((string)$payment_id, 6, '0', STR_PAD_LEFT),
        ]);
    } catch (Throwable $e) {
        error_log('Payment provenance skipped: ' . $e->getMessage());
    }
    if (function_exists('log_activity')) {
        log_activity(
            'Customer Payment',
            'Customers',
            ($party_name !== '' ? $party_name . ' ' : '') . $ref . ' ৳' . number_format($amount, 2)
                . ($discount > 0 ? (' · ডিসকাউন্ট ৳' . number_format($discount, 2)) : ''),
            [
                'action_type' => 'payment',
                'entity_type' => 'payment',
                'entity_id' => (string)(int)$payment_id,
                'reference' => $ref,
                'party_type' => 'customer',
                'party_id' => $customer_id,
                'party_name' => $party_name,
                'amount_paid' => (float)$amount,
                'discount' => (float)$discount,
            ]
        );
    }
    $pay_ok((int)$payment_id);
} catch (Throwable $e) {
    $db->rollback();
    error_log('Payment processing failed: ' . $e->getMessage());
    $msg = $e->getMessage();
    if ($msg !== '' && (str_contains($msg, 'নিরাপত্তা') || str_contains($msg, 'একই') || str_contains($msg, 'কোড') || str_contains($msg, 'মিনিট') || str_contains($msg, 'অপেক্ষমাণ'))) {
        $pay_fail($msg);
    }
    $pay_fail('পেমেন্ট সংরক্ষণে সমস্যা হয়েছে, কোনো তথ্য সেভ হয়নি।');
}