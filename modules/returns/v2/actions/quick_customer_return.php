<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/return_v2_functions.php';

ensure_return_v2_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../quick_return.php');
    exit;
}
csrf_verify();

$customer_id = (int)($_POST['customer_id'] ?? 0);
$sale_id = (int)($_POST['sale_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$qty = round((float)($_POST['qty'] ?? 1), 2);
$unit_price = round((float)($_POST['unit_price'] ?? 0), 2);
$sales_type = in_array($_POST['sales_type'] ?? '', ['retail', 'wholesale'], true) ? $_POST['sales_type'] : 'retail';
$reason = substr(trim((string)($_POST['reason'] ?? 'গ্রাহক ফেরত')), 0, 100);

if ($customer_id <= 0 || $sale_id <= 0 || $product_id <= 0) {
    $_SESSION['error'] = 'ক্রেতা ও পণ্য নির্বাচন করুন।';
    header('Location: ../quick_return.php');
    exit;
}
if ($qty <= 0) {
    $_SESSION['error'] = 'ফেরত পরিমাণ দিন।';
    header('Location: ../quick_return.php');
    exit;
}

$saleCheck = $db->prepare(
    "SELECT s.id, s.customer_id, c.name AS customer_name
     FROM sales s
     LEFT JOIN customers c ON c.id = s.customer_id
     WHERE s.id = ? AND s.status = 'completed' AND s.customer_id = ?
     LIMIT 1"
);
if (!$saleCheck) {
    $_SESSION['error'] = 'ডাটাবেস ত্রুটি।';
    header('Location: ../quick_return.php');
    exit;
}
$saleCheck->bind_param('ii', $sale_id, $customer_id);
$saleCheck->execute();
$sale = $saleCheck->get_result()->fetch_assoc();
$saleCheck->close();
if (!$sale) {
    $_SESSION['error'] = 'এই পণ্যটি ওই ক্রেতার মেমোতে নেই বা মেমো সম্পন্ন নয়।';
    header('Location: ../quick_return.php');
    exit;
}

$customer_name = trim((string)($sale['customer_name'] ?? ''));
if ($customer_name === '') {
    $customer_name = 'রেজিস্টার ক্রেতা';
}

$item = [
    'product_id' => $product_id,
    'returned_qty' => $qty,
    'unit_price' => $unit_price > 0 ? $unit_price : null,
    'discount_pct' => 0,
    'reason' => $reason,
];

$transaction_started = false;
try {
    $db->begin_transaction();
    $transaction_started = true;
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $result = ame_return_v2_save_and_complete_invoice_return(
        $db,
        $user_id,
        $sale_id,
        $customer_id,
        $customer_name,
        $sales_type,
        [$item],
        false,
        'customer_balance',
        null,
        'দ্রুত ফেরত — ক্রেতা + পণ্য'
    );
    $db->commit();
    $transaction_started = false;

    $prev = $result['previous_due'];
    $after = $result['balance_after'];
  $msg = 'ফেরত সম্পন্ন (' . $result['return_no'] . ')। স্টক যুক্ত হয়েছে।';
    if ($prev !== null && $after !== null) {
        $msg .= ' সাবেক ৳' . number_format($prev, 2) . ' → সর্বমোট ৳' . number_format($after, 2);
    }
    $_SESSION['success'] = $msg;
    log_activity('Quick Return', 'Returns', $result['return_no'] . ' — ৳' . $result['total']);
    header('Location: ../view.php?id=' . (int)$result['return_id']);
    exit;
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Quick customer return failed: ' . $e->getMessage());
    $_SESSION['error'] = $e->getMessage() ?: 'ফেরত সংরক্ষণ ব্যর্থ।';
    header('Location: ../quick_return.php');
    exit;
}