<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/purchase_return_functions.php';
require_once __DIR__ . '/../../../../includes/account_functions.php';
admin_only();
ensure_purchase_return_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../create.php');
    exit;
}
csrf_verify();

$is_manual = ($_POST['is_manual'] ?? '0') === '1';
$memo_no = trim($_POST['memo_no'] ?? '');
$memo_no = $memo_no === '' ? null : $memo_no;
$supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
$supplier_name = trim($_POST['supplier_name'] ?? '');
$allowed_refund_types = ['cash_refund', 'bank_transfer', 'mobile_banking', 'supplier_credit'];
$refund_type = in_array($_POST['refund_type'] ?? '', $allowed_refund_types, true) ? $_POST['refund_type'] : 'supplier_credit';
$account_id = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
$reason = trim($_POST['reason'] ?? '');
$reason_category = trim($_POST['reason_category'] ?? '') ?: null;
$notes = substr(trim($_POST['notes'] ?? ''), 0, 500);
$items_raw = json_decode($_POST['items_json'] ?? '[]', true);
$idempotency_token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);

// ================= ভ্যালিডেশন (মোড অনুযায়ী) =================
if (!$is_manual && !$memo_no) {
    $_SESSION['error'] = 'মেমো নম্বর পাওয়া যায়নি।';
    header('Location: ../create.php');
    exit;
}
if ($is_manual && !$supplier_id) {
    $_SESSION['error'] = 'ম্যানুয়াল ফেরতের জন্য একজন সাপ্লায়ার নির্বাচন করুন।';
    header('Location: ../create.php');
    exit;
}
if ($reason === '') {
    $_SESSION['error'] = 'ফেরতের কারণ লিখুন।';
    header('Location: ../create.php');
    exit;
}
if ($refund_type !== 'supplier_credit' && !$account_id) {
    $_SESSION['error'] = 'নগদ/ব্যাংক/মোবাইল ব্যাংকিং ফেরতের জন্য অ্যাকাউন্ট নির্বাচন করুন।';
    header('Location: ../create.php');
    exit;
}
if ($refund_type !== 'supplier_credit') {
    try {
        ame_require_active_account($db, (int)$account_id);
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ../create.php');
        exit;
    }
}
if (!is_array($items_raw) || empty($items_raw)) {
    $_SESSION['error'] = 'কমপক্ষে একটা পণ্য ফেরতের পরিমাণ দিন।';
    header('Location: ../create.php');
    exit;
}

$transaction_started = false;

try {
    $return_lock_key = 'purchase_return_' . (int)$supplier_id . '_' . (string)$memo_no;
    if (function_exists('ame_lock') && !ame_lock($db, $return_lock_key)) {
        throw new RuntimeException('এই মেমোর আরেকটি রিটার্ন চলছে। কয়েক সেকেন্ড পরে চেষ্টা করুন।');
    }
    $db->begin_transaction();
    $transaction_started = true;
    $existing_ref = 0;
    if ($idempotency_token !== ''
        && !claim_idempotency_token($db, 'purchase_return_create', $idempotency_token, $existing_ref)) {
        $db->rollback();
        $transaction_started = false;
        if ($existing_ref > 0) {
            header('Location: ../view.php?id=' . $existing_ref);
            exit;
        }
        throw new RuntimeException('আগের রিটার্ন অনুরোধটি যাচাই হচ্ছে। আবার চেষ্টা করুন।');
    }

    $return_no = generate_purchase_return_no($db);
    $total_return_amount = 0;
    $clean_items = [];

    foreach ($items_raw as $item) {
        $product_id = (int)($item['product_id'] ?? 0);
        $qty = round((float)($item['returned_qty'] ?? 0), 2);
        $free_qty = round((float)($item['free_qty'] ?? 0), 2);

        if ($product_id <= 0 || $qty <= 0) continue;
        if ($free_qty < 0) { $free_qty = 0; }

        if ($is_manual) {
            // ================= ম্যানুয়াল মোড: শুধু পণ্যের বাস্তব অস্তিত্ব যাচাই, কোনো
            // পূর্ববর্তী ক্রয়-রেকর্ডের সাথে মেলানো হচ্ছে না =================
            $p_stmt = $db->prepare("SELECT id FROM products WHERE id = ?");
            $p_stmt->bind_param('i', $product_id);
            $p_stmt->execute();
            if ($p_stmt->get_result()->num_rows === 0) {
                throw new RuntimeException('নির্বাচিত পণ্যকে খুঁজে পাওয়া যায়নি।');
            }

            $unit_price = round((float)($item['unit_price'] ?? 0), 2);
            if ($unit_price < 0) {
                throw new RuntimeException('মূল্য সঠিক নয়।');
            }

            $purchased_qty = null;
        } else {
            $pu_stmt = $db->prepare(
                "SELECT SUM(qty) as total_qty, MAX(purchase_price) as unit_price
                 FROM purchases WHERE memo_no = ? AND supplier_id <=> ? AND product_id = ?"
            );
            $pu_stmt->bind_param('sii', $memo_no, $supplier_id, $product_id);
            $pu_stmt->execute();
            $pu = $pu_stmt->get_result()->fetch_assoc();

            if (!$pu || $pu['total_qty'] === null) {
                throw new RuntimeException('এই পণ্যটা মূল ক্রয় মেমোতে পাওয়া যায়নি।');
            }

            $purchased_qty = (float)$pu['total_qty'];
            $posted_price = round((float)($item['unit_price'] ?? -1), 2);
            $unit_price = $posted_price >= 0 ? $posted_price : (float)$pu['unit_price'];
            $already_returned = get_already_purchase_returned_qty($db, $memo_no, (int)$supplier_id, $product_id);
            $remaining = $purchased_qty - $already_returned;

            if ($qty > $remaining + 0.001) {
                throw new RuntimeException('ক্রয়কৃত পরিমাণের চেয়ে বেশি ফেরত নেওয়া যাবে না (সর্বোচ্চ ' . $remaining . ')।');
            }
        }

        $line_total = round($qty * $unit_price, 2);
        $total_return_amount += $line_total;

        $clean_items[] = [
            'product_id' => $product_id,
            'purchased_qty' => $purchased_qty,
            'returned_qty' => $qty,
            'free_qty' => $free_qty,
            'unit_price' => $unit_price,
            'line_total' => $line_total,
        ];
    }

    if (empty($clean_items)) {
        throw new RuntimeException('বৈধ কোনো পণ্য ফেরতের এন্ট্রি পাওয়া যায়নি।');
    }

    $user_id = (int)($_SESSION['user_id'] ?? 0);

    $stmt = $db->prepare(
        "INSERT INTO purchase_returns (return_no, supplier_id, supplier_name, memo_no, return_amount, refund_type, account_id, reason, reason_category, notes, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', ?)"
    );
    // টাইপ: s(return_no) i(supplier_id) s(supplier_name) s(memo_no) d(amount) s(refund_type) i(account_id) s(reason) s(reason_category) s(notes) i(created_by) = "sissdsisssi" (১১টি)
    $stmt->bind_param(
        'sissdsisssi',
        $return_no, $supplier_id, $supplier_name, $memo_no, $total_return_amount, $refund_type, $account_id, $reason, $reason_category, $notes, $user_id
    );
    $stmt->execute();
    $return_id = $stmt->insert_id;

    $item_stmt = $db->prepare(
        "INSERT INTO purchase_return_items (return_id, product_id, purchased_qty, returned_qty, free_qty, unit_price, line_total)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($clean_items as $ci) {
        $item_stmt->bind_param(
            'iiddddd',
            $return_id, $ci['product_id'], $ci['purchased_qty'], $ci['returned_qty'], $ci['free_qty'], $ci['unit_price'], $ci['line_total']
        );
        $item_stmt->execute();
    }

    log_activity('Purchase Return Created', 'Purchase Returns', $return_no . ' — ৳' . $total_return_amount . ($is_manual ? ' (ম্যানুয়াল)' : ''));
    log_return_action($db, $return_id, 'created', null, 'pending_approval', $is_manual ? 'ম্যানুয়াল রিটার্ন তৈরি করা হয়েছে (মেমো ছাড়া)' : 'রিটার্ন তৈরি করা হয়েছে');

    $approve = $db->prepare("UPDATE purchase_returns SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
    $approve->bind_param('ii', $user_id, $return_id);
    $approve->execute();
    $approve->close();
    log_return_action($db, $return_id, 'approved', 'pending_approval', 'approved', 'সেভের সাথে অনুমোদন');
    complete_purchase_return_transaction($db, (int)$return_id);

    if ($idempotency_token !== '') {
        finish_idempotency_token($db, 'purchase_return_create', $idempotency_token, (int)$return_id);
    }
    $db->commit();
    $transaction_started = false;

    $_SESSION['success'] = 'ক্রয় ফেরত সম্পন্ন হয়েছে (' . $return_no . ')। রিটার্ন ইনভয়েস তৈরি হয়েছে। সাপ্লায়ার স্টেটমেন্টে ফেরত বসেছে।';
    header('Location: ../view.php?id=' . $return_id);
    exit;
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Purchase return save failed: ' . $e->getMessage());
    $_SESSION['error'] = $e->getMessage() ?: 'সংরক্ষণে সমস্যা হয়েছে।';
    header('Location: ../create.php');
    exit;
}
