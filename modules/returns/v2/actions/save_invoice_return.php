<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/account_functions.php';
require_once __DIR__ . '/../../../../includes/return_v2_functions.php';
ensure_return_v2_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../create_invoice_return.php');
    exit;
}
csrf_verify();

$is_manual = ($_POST['is_manual'] ?? '0') === '1';
$sale_id = !empty($_POST['sale_id']) ? (int)$_POST['sale_id'] : null;
$customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
$customer_name = trim($_POST['customer_name'] ?? '');
$sales_type = in_array($_POST['sales_type'] ?? '', ['retail', 'wholesale'], true) ? $_POST['sales_type'] : 'retail';
if (!$customer_id) {
    $customer_name = 'রিটেল কাস্টমার';
    $sales_type = 'retail';
} elseif ($customer_name === '') {
    $customer_name = 'রিটেল কাস্টমার';
}
$allowed_refund_methods = ['customer_balance', 'cash_refund', 'bank_transfer', 'mobile_banking'];
$refund_method = in_array($_POST['refund_method'] ?? '', $allowed_refund_methods, true) ? $_POST['refund_method'] : 'customer_balance';
$registered_invoice = ame_return_is_registered_invoice($is_manual, (int) $customer_id);
if ($registered_invoice) {
    $refund_method = 'customer_balance';
}
$raw_account = trim((string)($_POST['account_id'] ?? ''));
if ($raw_account === '') {
    $raw_account = trim((string)($_POST['posted_account_id'] ?? ''));
}
$account_id = $raw_account !== '' ? ame_posted_shop_account_id($db, $raw_account) : null;
if (!$account_id && !empty($_POST['account_id'])) {
    $account_id = (int)$_POST['account_id'];
}
if ($account_id <= 0 || $registered_invoice) {
    $account_id = null;
}
$selected_account = null;
if ($account_id && !$registered_invoice) {
    try {
        $selected_account = ame_require_active_account($db, (int)$account_id);
        if ($refund_method === 'customer_balance') {
            $pm = ame_account_payment_method($selected_account);
            $refund_method = $pm === 'bank' ? 'bank_transfer' : ($pm === 'mobile' ? 'mobile_banking' : 'cash_refund');
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ../create_invoice_return.php');
        exit;
    }
}
$notes = substr(trim($_POST['notes'] ?? ''), 0, 500);
$items_raw = json_decode($_POST['items_json'] ?? '[]', true);

// ================= ভ্যালিডেশন =================
if (!$is_manual && !$sale_id) {
    $_SESSION['error'] = '⚠️ বিক্রয় ইনভয়েস নির্বাচন করুন।';
    header('Location: ../create_invoice_return.php');
    exit;
}
if (!$customer_id && !$account_id) {
    $_SESSION['error'] = '⚠️ কোন একাউন্ট থেকে ফেরতের টাকা মাইনাস হবে তা নির্বাচন করুন।';
    header('Location: ../create_invoice_return.php');
    exit;
}
if ($refund_method !== 'customer_balance' && !$selected_account) {
    try {
        $selected_account = ame_require_active_account($db, (int)$account_id);
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ../create_invoice_return.php');
        exit;
    }
}
if (!is_array($items_raw) || empty($items_raw)) {
    $_SESSION['error'] = '⚠️ অনুগ্রহ করে অন্তত একটি পণ্য নির্বাচন করুন।';
    header('Location: ../create_invoice_return.php');
    exit;
}

$transaction_started = false;

try {
    $db->begin_transaction();
    $transaction_started = true;

    $return_no = generate_return_no($db);
    $total_return_amount = 0;
    $total_discount = 0;
    $clean_items = [];
    $combined_reason = [];

    // ================= প্রতিটা লাইন আইটেম সার্ভার-সাইডে পুনরায় যাচাই =================
    foreach ($items_raw as $item) {
        $product_id = (int)($item['product_id'] ?? 0);
        $qty = round((float)($item['returned_qty'] ?? 0), 2);
        $discount_pct = min(100, max(0, round((float)($item['discount_pct'] ?? 0), 2)));
        $line_reason = substr(trim((string)($item['reason'] ?? '')), 0, 100);

        if ($product_id <= 0 || $qty <= 0) continue;

        if ($is_manual) {
            // ================= ম্যানুয়াল মোড: শুধু পণ্যের বাস্তব অস্তিত্ব যাচাই =================
            $p_stmt = $db->prepare("SELECT id, sale_price FROM products WHERE id = ?");
            $p_stmt->bind_param('i', $product_id);
            $p_stmt->execute();
            $p = $p_stmt->get_result()->fetch_assoc();

            if (!$p) {
                throw new RuntimeException('⚠️ নির্বাচিত পণ্যকে খুঁজে পাওয়া যায়নি।');
            }

            $unit_price = round((float)($item['unit_price'] ?? $p['sale_price']), 2);
            if ($unit_price < 0) { $unit_price = 0; }
            $sold_qty = null;
        } else {
            $si_stmt = $db->prepare("SELECT qty, sale_price FROM sale_items WHERE sale_id = ? AND product_id = ?");
            $si_stmt->bind_param('ii', $sale_id, $product_id);
            $si_stmt->execute();
            $si = $si_stmt->get_result()->fetch_assoc();

            if (!$si) {
                throw new RuntimeException('⚠️ এই পণ্যটা মূল ইনভয়েসে পাওয়া যায়নি।');
            }

            $sold_qty = (float)$si['qty'];
            // মূল্য ক্লায়েন্ট থেকে আসছে (খুচরা/পাইকারি টগলের কারণে সম্পাদনযোগ্য)
            $unit_price = round((float)($item['unit_price'] ?? $si['sale_price']), 2);
            if ($unit_price < 0) { $unit_price = (float)$si['sale_price']; }

            $already_returned = get_already_returned_qty($db, $sale_id, $product_id);
            $remaining = $sold_qty - $already_returned;

            if ($remaining <= 0) {
                throw new RuntimeException('⚠️ এই পণ্যের আর কোনো ফেরতযোগ্য quantity নেই।');
            }
            if ($qty > $remaining + 0.001) {
                throw new RuntimeException('⚠️ ফেরতের পরিমাণ বিক্রিত পরিমাণের চেয়ে বেশি হতে পারবে না (সর্বোচ্চ ' . $remaining . ')।');
            }
        }

        $gross = round($qty * $unit_price, 2);
        $discount_amt = round($gross * ($discount_pct / 100), 2);
        $line_total = round($gross - $discount_amt, 2);

        $total_return_amount += $line_total;
        $total_discount += $discount_amt;
        if ($line_reason !== '') { $combined_reason[$line_reason] = true; }

        $clean_items[] = [
            'product_id' => $product_id,
            'original_qty' => $sold_qty,
            'returned_qty' => $qty,
            'unit_price' => $unit_price,
            'discount_pct' => $discount_pct,
            'line_reason' => $line_reason,
            'line_total' => $line_total,
        ];
    }

    if (empty($clean_items)) {
        throw new RuntimeException('⚠️ বৈধ কোনো পণ্য ফেরতের এন্ট্রি পাওয়া যায়নি।');
    }

    $reason_summary = implode(', ', array_keys($combined_reason)) ?: 'উল্লেখ করা হয়নি';
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $return_type = $is_manual ? 'manual' : 'invoice_based';

    $stmt = $db->prepare(
        "INSERT INTO sale_returns_v2 (return_no, sale_id, customer_id, customer_name, sales_type, return_type, return_amount, refund_method, discount_amount, account_id, reason, notes, return_date, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'pending_approval', ?)"
    );
    // টাইপ: s(return_no) i(sale_id) i(customer_id) s(customer_name) s(sales_type) s(return_type) d(return_amount) s(refund_method) d(discount_amount) i(account_id) s(reason) s(notes) i(created_by) = "siisssdsdissi" (১৩টি)
    $stmt->bind_param(
        'siisssdsdissi',
        $return_no, $sale_id, $customer_id, $customer_name, $sales_type, $return_type, $total_return_amount, $refund_method, $total_discount, $account_id, $reason_summary, $notes, $user_id
    );
    $stmt->execute();
    $return_id = $stmt->insert_id;
    $stmt->close();

    $item_stmt = $db->prepare(
        "INSERT INTO sale_return_items (return_id, product_id, original_qty, returned_qty, unit_price, discount_pct, line_reason, line_total, is_replacement)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)"
    );
    foreach ($clean_items as $ci) {
        $item_stmt->bind_param(
            'iiddddsd',
            $return_id, $ci['product_id'], $ci['original_qty'], $ci['returned_qty'], $ci['unit_price'], $ci['discount_pct'], $ci['line_reason'], $ci['line_total']
        );
        $item_stmt->execute();
    }
    $item_stmt->close();

    $approve = $db->prepare("UPDATE sale_returns_v2 SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
    $approve->bind_param('ii', $user_id, $return_id);
    $approve->execute();
    $approve->close();

    if (!function_exists('add_ledger_entry')) {
        require_once __DIR__ . '/../../../../modules/customers/customer_ledger_functions.php';
    }
    complete_return_transaction($db, $return_id, $user_id, false);

    $pay_status = ($refund_method !== 'customer_balance' && $account_id) ? 'paid' : ($customer_id ? 'adjusted' : 'paid');
    $upd_pay = $db->prepare("UPDATE sale_returns_v2 SET payment_status = ? WHERE id = ?");
    if ($upd_pay) {
        $upd_pay->bind_param('si', $pay_status, $return_id);
        $upd_pay->execute();
        $upd_pay->close();
    }

    log_activity('Return Created', 'Returns', $return_no . ' — ৳' . $total_return_amount . ($is_manual ? ' (ম্যানুয়াল)' : ''));

    $db->commit();
    $transaction_started = false;

    if ($registered_invoice) {
        $snap = $db->prepare('SELECT previous_due, balance_after FROM sale_returns_v2 WHERE id = ? LIMIT 1');
        $prevShown = null;
        $afterShown = null;
        if ($snap) {
            $snap->bind_param('i', $return_id);
            $snap->execute();
            $snapRow = $snap->get_result()->fetch_assoc();
            $snap->close();
            if ($snapRow && $snapRow['previous_due'] !== null && $snapRow['balance_after'] !== null) {
                $prevShown = (float) $snapRow['previous_due'];
                $afterShown = (float) $snapRow['balance_after'];
            }
        }
        if ($prevShown === null) {
            $prevShown = ame_return_customer_due($db, (int) $customer_id) + (float) $total_return_amount;
            $afterShown = ame_return_due_after($prevShown, (float) $total_return_amount);
        }
        $_SESSION['success'] = 'বিক্রয় ফেরত সম্পন্ন হয়েছে (' . $return_no . ')। সাবেক বকেয়া ৳'
            . number_format($prevShown, 2) . ' থেকে −৳' . number_format((float) $total_return_amount, 2)
            . ' মাইনাস। সর্বমোট ব্যালেন্স ৳' . number_format($afterShown, 2) . '। মেমোতে দেখা যাবে।';
    } elseif ($selected_account && $refund_method !== 'customer_balance') {
        $acc_name = trim((string)($selected_account['account_name'] ?? ''));
        $_SESSION['success'] = 'বিক্রয় ফেরত সম্পন্ন হয়েছে (' . $return_no . ')। «' . $acc_name . '» একাউন্ট থেকে −৳' . number_format((float)$total_return_amount, 2) . ' মাইনাস হয়েছে।';
    } else {
        $_SESSION['success'] = 'বিক্রয় ফেরত সম্পন্ন হয়েছে (' . $return_no . ')। রিটার্ন ইনভয়েস তৈরি হয়েছে। গ্রাহক ব্যালেন্স সমন্বয় হলে স্টেটমেন্টে ক্রেডিট বসেছে।';
    }
    header('Location: ../view.php?id=' . $return_id);
    exit;
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Invoice return save failed: ' . $e->getMessage());
    $_SESSION['error'] = $e->getMessage() ?: '⚠️ রিটার্ন সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../create_invoice_return.php');
    exit;
}
