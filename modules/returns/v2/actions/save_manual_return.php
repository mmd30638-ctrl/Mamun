<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/account_functions.php';
require_once __DIR__ . '/../../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../create_manual_return.php');
    exit;
}
csrf_verify();

$customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
$customer_name = trim($_POST['customer_name'] ?? '');
$product_id = (int)($_POST['product_id'] ?? 0);
$qty = round((float)($_POST['qty'] ?? 0), 2);
$unit_price = round((float)($_POST['unit_price'] ?? 0), 2);
$sales_type = in_array($_POST['sales_type'] ?? '', ['retail', 'wholesale'], true) ? $_POST['sales_type'] : 'retail';
if (!$customer_id) {
    $customer_name = 'রিটেল কাস্টমার';
    $sales_type = 'retail';
} elseif ($customer_name === '' || strcasecmp($customer_name, 'Retail Customer') === 0) {
    $customer_name = 'রিটেল কাস্টমার';
}
$reason = trim($_POST['reason'] ?? '');

$allowed_refund_methods = ['customer_balance', 'cash_refund', 'bank_transfer', 'mobile_banking'];
$refund_method = in_array($_POST['refund_method'] ?? '', $allowed_refund_methods, true) ? $_POST['refund_method'] : 'customer_balance';
$raw_account = trim((string)($_POST['account_id'] ?? ''));
if ($raw_account === '') {
    $raw_account = trim((string)($_POST['posted_account_id'] ?? ''));
}
$account_id = $raw_account !== '' ? ame_posted_shop_account_id($db, $raw_account) : null;
if (!$account_id && !empty($_POST['account_id'])) {
    $account_id = (int)$_POST['account_id'];
}
if ($account_id <= 0) {
    $account_id = null;
}
$selected_account = null;
if ($account_id) {
    try {
        $selected_account = ame_require_active_account($db, (int)$account_id);
        if ($refund_method === 'customer_balance') {
            $pm = ame_account_payment_method($selected_account);
            $refund_method = $pm === 'bank' ? 'bank_transfer' : ($pm === 'mobile' ? 'mobile_banking' : 'cash_refund');
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ../create_manual_return.php');
        exit;
    }
}

if ($product_id <= 0 || $qty <= 0 || $unit_price < 0) {
    $_SESSION['error'] = 'পণ্য, পরিমাণ ও মূল্য সঠিকভাবে দিন।';
    header('Location: ../create_manual_return.php');
    exit;
}
if ($reason === '') {
    $_SESSION['error'] = 'ফেরতের কারণ লিখুন।';
    header('Location: ../create_manual_return.php');
    exit;
}
if (!$customer_id && !$account_id) {
    $_SESSION['error'] = '⚠️ কোন একাউন্ট থেকে ফেরতের টাকা মাইনাস হবে তা নির্বাচন করুন।';
    header('Location: ../create_manual_return.php');
    exit;
}
if ($refund_method !== 'customer_balance' && !$selected_account) {
    try {
        $selected_account = ame_require_active_account($db, (int)$account_id);
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ../create_manual_return.php');
        exit;
    }
}

// ================= পণ্য সত্যিই বিদ্যমান কিনা যাচাই =================
$check = $db->prepare("SELECT id FROM products WHERE id = ?");
$check->bind_param('i', $product_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    $_SESSION['error'] = 'নির্বাচিত পণ্যকে খুঁজে পাওয়া যায়নি।';
    header('Location: ../create_manual_return.php');
    exit;
}

$transaction_started = false;

try {
    $db->begin_transaction();
    $transaction_started = true;

    $return_no = generate_return_no($db);
    $total = round($qty * $unit_price, 2);
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $pay_status = ($refund_method !== 'customer_balance' && $account_id) ? 'paid' : ($customer_id ? 'adjusted' : 'paid');

    $stmt = $db->prepare(
        "INSERT INTO sale_returns_v2 (return_no, customer_id, customer_name, sales_type, return_type, return_amount, refund_method, account_id, payment_status, reason, return_date, status, created_by, approved_by, approved_at)
         VALUES (?, ?, ?, ?, 'manual', ?, ?, ?, ?, ?, CURDATE(), 'approved', ?, ?, NOW())"
    );
    $stmt->bind_param('sissdsissii', $return_no, $customer_id, $customer_name, $sales_type, $total, $refund_method, $account_id, $pay_status, $reason, $user_id, $user_id);
    $stmt->execute();
    $return_id = $stmt->insert_id;

    $item_stmt = $db->prepare(
        "INSERT INTO sale_return_items (return_id, product_id, returned_qty, unit_price, line_total, is_replacement)
         VALUES (?, ?, ?, ?, ?, 0)"
    );
    $item_stmt->bind_param('iiddd', $return_id, $product_id, $qty, $unit_price, $total);
    $item_stmt->execute();

    if (!function_exists('add_ledger_entry')) {
        $ledgerFile = __DIR__ . '/../../../../modules/customers/customer_ledger_functions.php';
        if (is_file($ledgerFile)) {
            require_once $ledgerFile;
        }
    }
    complete_return_transaction($db, $return_id, $user_id, false);

    log_activity('Manual Return Created', 'Returns', $return_no . ' — ৳' . $total);

    $db->commit();
    $transaction_started = false;

    if ($selected_account && $refund_method !== 'customer_balance') {
        $acc_name = trim((string)($selected_account['account_name'] ?? ''));
        $_SESSION['success'] = 'বিক্রয় ফেরত সম্পন্ন হয়েছে (' . $return_no . ')। «' . $acc_name . '» একাউন্ট থেকে −৳' . number_format((float)$total, 2) . ' মাইনাস হয়েছে।';
    } else {
        $_SESSION['success'] = 'ম্যানুয়াল রিটার্ন সম্পন্ন হয়েছে (' . $return_no . ')। গ্রাহক ব্যালেন্স সমন্বয় হলে স্টেটমেন্টে ক্রেডিট বসেছে।';
    }
    header('Location: ../view.php?id=' . $return_id);
    exit;
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Manual return save failed: ' . $e->getMessage());
    $_SESSION['error'] = $e->getMessage() ?: 'সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../create_manual_return.php');
    exit;
}
