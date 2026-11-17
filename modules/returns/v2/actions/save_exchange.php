<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../create_exchange.php');
    exit;
}
csrf_verify();

$customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
$customer_name = trim($_POST['customer_name'] ?? '') ?: 'Retail Customer';
$old_product_id = (int)($_POST['old_product_id'] ?? 0);
$new_product_id = (int)($_POST['new_product_id'] ?? 0);
$old_qty = round((float)($_POST['old_qty'] ?? 0), 2);
$new_qty = round((float)($_POST['new_qty'] ?? 0), 2);
$old_price = round((float)($_POST['old_price'] ?? 0), 2);
$new_price = round((float)($_POST['new_price'] ?? 0), 2);
$sales_type = in_array($_POST['sales_type'] ?? '', ['retail', 'wholesale'], true) ? $_POST['sales_type'] : 'retail';
$reason = trim($_POST['reason'] ?? '');

if ($old_product_id <= 0 || $new_product_id <= 0 || $old_qty <= 0 || $new_qty <= 0 || $old_price < 0 || $new_price < 0) {
    $_SESSION['error'] = 'ফেরত ও বদলি পণ্যের তথ্য সঠিকভাবে দিন।';
    header('Location: ../create_exchange.php');
    exit;
}
if ($reason === '') {
    $_SESSION['error'] = 'এক্সচেঞ্জের কারণ লিখুন।';
    header('Location: ../create_exchange.php');
    exit;
}

$check_stmt = $db->prepare("SELECT id, stock FROM products WHERE id IN (?, ?)");
$check_stmt->bind_param('ii', $old_product_id, $new_product_id);
$check_stmt->execute();
$found = $check_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if (count($found) < 2 && $old_product_id !== $new_product_id) {
    $_SESSION['error'] = 'নির্বাচিত পণ্য(গুলো) খুঁজে পাওয়া যায়নি।';
    header('Location: ../create_exchange.php');
    exit;
}

// ================= সার্ভার-সাইডে নিজে গণনা করা — ক্লায়েন্টের হিসাব বিশ্বাস করা হচ্ছে না =================
$old_value = round($old_qty * $old_price, 2);
$new_value = round($new_qty * $new_price, 2);
$adjustment = round($new_value - $old_value, 2);

$transaction_started = false;

try {
    $db->begin_transaction();
    $transaction_started = true;

    $return_no = generate_return_no($db);
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    $stmt = $db->prepare(
        "INSERT INTO sale_returns_v2 (return_no, customer_id, customer_name, sales_type, return_type, return_amount, exchange_amount, customer_adjustment, reason, return_date, status, created_by)
         VALUES (?, ?, ?, ?, 'exchange', ?, ?, ?, ?, CURDATE(), 'pending_approval', ?)"
    );
    // টাইপ: s(return_no) i(customer_id) s(customer_name) s(sales_type) d(return_amount) d(exchange_amount) d(adjustment) s(reason) i(created_by) = "sissdddsi" (৯টি প্যারামিটার)
    $stmt->bind_param(
        'sissdddsi',
        $return_no, $customer_id, $customer_name, $sales_type, $old_value, $new_value, $adjustment, $reason, $user_id
    );
    $stmt->execute();
    $return_id = $stmt->insert_id;

    $item_stmt = $db->prepare(
        "INSERT INTO sale_return_items (return_id, product_id, returned_qty, unit_price, line_total, is_replacement)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $is_replacement = 0;
    $item_stmt->bind_param('iidddi', $return_id, $old_product_id, $old_qty, $old_price, $old_value, $is_replacement);
    $item_stmt->execute();

    $is_replacement = 1;
    $item_stmt->bind_param('iidddi', $return_id, $new_product_id, $new_qty, $new_price, $new_value, $is_replacement);
    $item_stmt->execute();

    $approve = $db->prepare("UPDATE sale_returns_v2 SET status = 'approved', approved_by = ?, approved_at = NOW(), refund_method = 'customer_balance' WHERE id = ?");
    $approve->bind_param('ii', $user_id, $return_id);
    $approve->execute();
    $approve->close();

    ame_return_v2_ensure_customer_ledger();
    complete_return_transaction($db, $return_id, $user_id, false);

    $pay_status = $customer_id ? 'adjusted' : 'paid';
    $upd_pay = $db->prepare("UPDATE sale_returns_v2 SET payment_status = ? WHERE id = ?");
    if ($upd_pay) {
        $upd_pay->bind_param('si', $pay_status, $return_id);
        $upd_pay->execute();
        $upd_pay->close();
    }

    log_activity('Exchange Created', 'Returns', $return_no . ' — সমন্বয় ৳' . $adjustment);

    $db->commit();
    $transaction_started = false;

    $msg = 'এক্সচেঞ্জ সম্পন্ন (' . $return_no . ')। স্টক ও কাস্টমার ব্যালেন্স আপডেট হয়েছে।';
    if ($customer_id) {
        $snap = $db->prepare('SELECT previous_due, balance_after FROM sale_returns_v2 WHERE id = ? LIMIT 1');
        if ($snap) {
            $snap->bind_param('i', $return_id);
            $snap->execute();
            $snapRow = $snap->get_result()->fetch_assoc();
            $snap->close();
            if ($snapRow && $snapRow['previous_due'] !== null && $snapRow['balance_after'] !== null) {
                $msg .= ' সাবেক ৳' . number_format((float)$snapRow['previous_due'], 2)
                    . ' → সর্বমোট ৳' . number_format((float)$snapRow['balance_after'], 2);
            }
        }
    }
    $_SESSION['success'] = $msg;
    header('Location: ../view.php?id=' . $return_id);
    exit;
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Exchange save failed: ' . $e->getMessage());
    $_SESSION['error'] = 'সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../create_exchange.php');
    exit;
}
