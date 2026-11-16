<?php
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/purchase_draft.php';
require_once '../../includes/integrity_functions.php';
ame_purchase_require_access($db);
ensure_purchase_schema($db);
ame_purchase_draft_schema($db);
ensure_integrity_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add.php");
    exit();
}

$purchase_wants_json = !empty($_POST['ajax_complete'])
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json'));

if (!function_exists('ame_purchase_respond_client')) {
    function ame_purchase_respond_client(bool $as_json, string $message, string $code = 'error', int $status = 422): void
    {
        if ($as_json) {
            if (!headers_sent()) {
                http_response_code($status);
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode(
                ['ok' => false, 'status' => 'error', 'code' => $code, 'message' => $message],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }
        die('❌ ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . ' <a href="add.php">ফিরে যান</a>');
    }
}

$session_token = (string)($_SESSION['csrf_token'] ?? '');
$posted_token = (string)($_POST['csrf_token'] ?? '');
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    die('❌ নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন। <a href="add.php">ফিরে যান</a>');
}

$supplier_id  = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
$customer_id  = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$party_type   = trim((string)($_POST['party_type'] ?? ''));
if ($customer_id <= 0 && $supplier_id > 0 && $party_type === 'customer') {
    $linked = ame_purchase_customer_ids_for_supplier($db, (int)$supplier_id);
    if ($linked) {
        $customer_id = (int)$linked[0];
    }
}
$memo_no      = ame_purchase_normalize_memo($_POST['memo_no'] ?? '');
$memo_no      = $memo_no === '' ? null : $memo_no;
$purchase_date = trim((string)($_POST['purchase_date'] ?? ''));
$notes        = substr(trim((string)($_POST['notes'] ?? '')), 0, 500);
$items_raw    = json_decode($_POST['items_json'] ?? '[]', true);
$expenses_raw = json_decode($_POST['expenses_json'] ?? '[]', true);

// ================= ক্রয়ের তারিখ যাচাই =================
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase_date)) {
    $purchase_date = date('Y-m-d');
}
if (strtotime($purchase_date) > strtotime('+1 day')) {
    die('❌ ক্রয়ের তারিখ ভবিষ্যতের হতে পারবে না। <a href="add.php">ফিরে যান</a>');
}
$purchase_datetime = $purchase_date . ' ' . date('H:i:s');

if (!is_array($items_raw) || empty($items_raw)) {
    die('❌ কমপক্ষে একটা পণ্য যোগ করুন। <a href="add.php">ফিরে যান</a>');
}

// ================= প্রতিটা লাইন আইটেম সার্ভার-সাইডে যাচাই ও পরিষ্কার =================
$clean_items = [];
$items_total = 0;

foreach ($items_raw as $item) {
    $product_id = (int)($item['product_id'] ?? 0);
    $qty = round((float)($item['qty'] ?? 0), 2);
    $unit_id = (int)($item['unit_id'] ?? 0);
    $pp = round((float)($item['purchase_price'] ?? 0), 2);
    $dp = round((float)($item['dealer_price'] ?? 0), 2);
    $wp = round((float)($item['wholesale_price'] ?? 0), 2);
    $sp = round((float)($item['sale_price'] ?? 0), 2);
    $free_qty = ame_purchase_free_qty($item);
    if ($qty < 0) {
        $qty = 0.0;
    }

    if ($product_id <= 0 || ($qty + $free_qty) <= 0) {
        continue;
    }
    if ($qty > 0 && $pp <= 0) {
        continue;
    }
    if ($dp < 0 || $wp < 0 || $sp < 0) {
        die('❌ মূল্য সঠিকভাবে লিখুন। <a href="add.php">ফিরে যান</a>');
    }

    // ================= বিক্রয় রেট ক্রয় রেটের কম হতে পারবে না =================
    $prod_name_stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
    $prod_name_stmt->bind_param('i', $product_id);
    $prod_name_stmt->execute();
    $prod_row = $prod_name_stmt->get_result()->fetch_assoc();
    $prod_name_stmt->close();
    if (!$prod_row) {
        continue;
    }
    $prod_name = trim((string)($prod_row['name'] ?? ''));
    if ($prod_name === '') {
        $prod_name = 'পণ্য #' . $product_id;
    }

    $price_err = ame_purchase_sale_price_error($prod_name, $pp, $dp, $wp, $sp);
    if ($price_err) {
        die('❌ ' . htmlspecialchars($price_err) . ' <a href="add.php">ফিরে যান</a>');
    }

    $line_total = round($qty * $pp, 2);
    $items_total += $line_total;

    $clean_items[] = [
        'product_id' => $product_id, 'qty' => $qty, 'purchase_price' => $pp,
        'dealer_price' => $dp, 'wholesale_price' => $wp, 'sale_price' => $sp, 'total_cost' => $line_total,
        'free_qty' => $free_qty, 'stock_qty' => round($qty + $free_qty, 2),
        'unit_id' => $unit_id,
    ];
}

if (empty($clean_items)) {
    die('❌ বৈধ কোনো পণ্যের এন্ট্রি পাওয়া যায়নি। <a href="add.php">ফিরে যান</a>');
}

// ================= অতিরিক্ত খরচ =================
$clean_expenses = [];
$expense_total = 0;
if (is_array($expenses_raw)) {
    foreach ($expenses_raw as $exp) {
        $etype = substr(trim((string)($exp['type'] ?? 'অন্যান্য')), 0, 50);
        $eamount = round((float)($exp['amount'] ?? 0), 2);
        if ($eamount <= 0) continue;
        $clean_expenses[] = ['type' => $etype, 'amount' => $eamount];
        $expense_total += $eamount;
    }
}

$ame_adj = ame_purchase_read_adjustments($_POST, $items_total, $expense_total);
if (!$ame_adj['ok']) {
    die('❌ ' . htmlspecialchars($ame_adj['error'], ENT_QUOTES, 'UTF-8') . ' <a href="add.php">ফিরে যান</a>');
}
$grand_total = $ame_adj['grand_total'];
$ame_adjust_total = round($grand_total - $items_total, 2);

if (!$supplier_id) {
    die('❌ সাপ্লায়ার নির্বাচন করুন। স্টেটমেন্টে প্লাস-মাইনাস সাপ্লায়ার দিয়ে হয়। <a href="add.php">ফিরে যান</a>');
}

$paid_total = round((float)($_POST['paid_amount'] ?? $_POST['advance_amount'] ?? 0), 2);
if ($paid_total < 0) {
    die('❌ পরিশোধ ঋণাত্মক হতে পারবে না। <a href="add.php">ফিরে যান</a>');
}

$account_id = (int)($_POST['account_id'] ?? 0);
if ($account_id <= 0 && !empty($_POST['advance_account_id'])) {
    $account_id = (int)$_POST['advance_account_id'];
}
if ($account_id <= 0 && !empty($_POST['purchase_pay_account'])) {
    $account_id = (int)$_POST['purchase_pay_account'];
}
if ($paid_total > 0.009) {
    if ($account_id <= 0) {
        die('❌ পরিশোধের টাকা যে প্রকৃত ব্যবসায়িক অ্যাকাউন্ট থেকে যাবে সেটি নির্বাচন করুন। <a href="add.php">ফিরে যান</a>');
    }
    try {
        ame_require_active_account($db, $account_id);
    } catch (Throwable $e) {
        ame_purchase_respond_client($purchase_wants_json, $e->getMessage(), 'account_invalid');
    }
    if (function_exists('ame_account_money_snapshot')) {
        $snap = ame_account_money_snapshot($db, $account_id);
        if ($snap === null) {
            ame_purchase_respond_client($purchase_wants_json, 'অ্যাকাউন্ট খুঁজে পাওয়া যায়নি।', 'account_missing');
        }
        $available = round((float)($snap['amount'] ?? 0), 2);
        if ($available + 0.009 < $paid_total) {
            $msg = 'অপর্যাপ্ত ব্যালেন্স — এই পরিমাণ (৳' . number_format($paid_total, 2) . ') লেনদেনের জন্য অ্যাকাউন্টে পর্যাপ্ত টাকা নেই। পর্যাপ্ত ব্যালেন্সের অন্য অ্যাকাউন্ট বেছে নিয়ে আবার সম্পন্ন করুন।';
            ame_purchase_respond_client($purchase_wants_json, $msg, 'insufficient_balance');
        }
    }
}

$pay_math = ame_purchase_payment_result(0.0, $grand_total, $paid_total);
$bill_due = $pay_math['bill_due'];
$due_delta = round($grand_total - $paid_total, 2);
$payment_type = $bill_due > 0.009 ? 'credit' : 'cash';
$due_total = $bill_due;
$account_id_bind = $account_id > 0 ? $account_id : 0;
$posted_previous_due = ame_purchase_posted_previous_due($_POST, (int)$customer_id);
$settle_type = strtolower(trim((string)($_POST['settle_type'] ?? '')));
$settle_reason = trim((string)($_POST['settle_reason'] ?? ''));

$draft_id = (int)($_POST['draft_id'] ?? 0);
$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_admin = strtolower((string)($_SESSION['role'] ?? '')) === 'admin';

$purchase_token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);
ame_lock($db, 'purchase_user_' . (int)($_SESSION['user_id'] ?? 0));

if ($memo_no === null) {
    $memo_no = generate_unique_purchase_memo($db, $supplier_id);
}
$memo_lock_key = 'purchase_memo_' . (int)$supplier_id . '_' . substr(sha1((string)$memo_no), 0, 32);
if (!ame_lock($db, $memo_lock_key)) {
    die('❌ এই মেমোটি অন্য একটি ক্রয়ে সংরক্ষণ হচ্ছে। কয়েক সেকেন্ড পরে আবার চেষ্টা করুন। <a href="add.php">ফিরে যান</a>');
}
if (purchase_memo_exists($db, $supplier_id, $memo_no)) {
    die('❌ এই সাপ্লায়ারের একই Invoice/Memo নম্বর আগেই ব্যবহার হয়েছে। অন্য নম্বর দিন। <a href="add.php">ফিরে যান</a> | <a href="history.php">ইতিহাস দেখুন</a>');
}

$db->begin_transaction();
try {
    $purchase_replay = 0;
    if ($purchase_token !== '' && !claim_idempotency_token($db, 'purchase', $purchase_token, $purchase_replay)) {
        $db->rollback();
        die('❌ এই ক্রয় ইতিমধ্যে সংরক্ষিত হয়েছে — ডাবল এন্ট্রি আটকানো হয়েছে। <a href="history.php">ইতিহাস দেখুন</a>');
    }

    if ($draft_id > 0) {
        $existing_draft = ame_purchase_draft_find($db, $draft_id, $user_id, $is_admin, true);
        if (!$existing_draft) {
            throw new RuntimeException('ড্রাফট পাওয়া যায়নি অথবা ইতিমধ্যে সম্পন্ন/বাতিল হয়েছে।');
        }
    }

    $created_by = $_SESSION['user_id'] ?? null;
    $item_count = count($clean_items);
    $ame_landed_running = 0.0;
    $ame_paid_running = 0.0;
    $ame_item_index = 0;
    $purchase_reference_id = 0;

    foreach ($clean_items as $ci) {
        $ame_item_index++;
        $is_last_item = ($ame_item_index === $item_count);
        $ci['qty'] = $ci['stock_qty'];
        $stmt = $ci['purchase_price'] > 0
            ? $db->prepare("UPDATE products SET purchase_price = ?, dealer_price = ?, wholesale_price = ?, sale_price = ? WHERE id = ?")
            : $db->prepare("UPDATE products SET purchase_price = purchase_price + ?, dealer_price = ?, wholesale_price = ?, sale_price = ? WHERE id = ?");
        $stmt->bind_param("ddddi", $ci['purchase_price'], $ci['dealer_price'], $ci['wholesale_price'], $ci['sale_price'], $ci['product_id']);
        $stmt->execute();
        if (!empty($ci['unit_id']) && is_file(__DIR__ . '/../../includes/unit_engine.php')) {
            require_once __DIR__ . '/../../includes/unit_engine.php';
            $base_qty = ame_unit_to_base($db, (int)$ci['product_id'], (string)$ci['qty'], (int)$ci['unit_id']);
            ame_unit_move($db, (int)$ci['product_id'], $base_qty, [
                'kind' => 'purchase',
                'entered_qty' => $ci['qty'],
                'unit_id' => (int)$ci['unit_id'],
                'ref_type' => 'purchase',
                'note' => 'purchase',
            ], false);
            $ci['base_qty'] = $base_qty;
        } elseif (function_exists('ame_store_stock_change') && current_store_id() > 0) {
            ame_store_stock_change($db, current_store_id(), (int)$ci['product_id'], (float)$ci['qty'], false);
        } else {
            $add_stock = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            $add_stock->bind_param('di', $ci['qty'], $ci['product_id']);
            $add_stock->execute();
            $add_stock->close();
        }

        // ================= অতিরিক্ত খরচ পণ্যের মূল্যের অনুপাতে বণ্টন (Landed Cost) =================
        $item_ratio = $items_total > 0 ? ($ci['total_cost'] / $items_total) : 0;
        if ($is_last_item) {
            $item_landed_cost = round($grand_total - $ame_landed_running, 2);
            $item_paid_amount = round($paid_total - $ame_paid_running, 2);
        } else {
            $item_landed_cost = round($ci['total_cost'] + ($ame_adjust_total * $item_ratio), 2);
            $item_paid_amount = round($paid_total * $item_ratio, 2);
        }
        $item_due_amount = round($item_landed_cost - $item_paid_amount, 2);
        $ame_landed_running = round($ame_landed_running + $item_landed_cost, 2);
        $ame_paid_running = round($ame_paid_running + $item_paid_amount, 2);

        $ins = $db->prepare(
            "INSERT INTO purchases (supplier_id, memo_no, product_id, qty, purchase_price, dealer_price, wholesale_price, sale_price, total_cost, account_id, created_by, payment_type, paid_amount, due_amount, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        // টাইপ: i(supplier_id) s(memo_no) i(product_id) + 6×d(qty,pp,dp,wp,sp,total_cost) + i(account_id) i(created_by) s(payment_type) + 2×d(paid,due) + s(notes) + s(created_at) = ১৬টি
        $ins->bind_param(
            "isiddddddiisddss",
            $supplier_id, $memo_no, $ci['product_id'], $ci['qty'], $ci['purchase_price'], $ci['dealer_price'], $ci['wholesale_price'], $ci['sale_price'],
            $item_landed_cost, $account_id_bind, $created_by, $payment_type, $item_paid_amount, $item_due_amount, $notes, $purchase_datetime
        );
        $ins->execute();
        $purchase_row_id = (int)$ins->insert_id;
        if ($purchase_reference_id <= 0) {
            $purchase_reference_id = $purchase_row_id;
        }
        if ($purchase_row_id > 0 && $ci['free_qty'] > 0) {
            $fq = $db->prepare('UPDATE purchases SET free_qty = ? WHERE id = ?');
            if ($fq) {
                $fq->bind_param('di', $ci['free_qty'], $purchase_row_id);
                $fq->execute();
                $fq->close();
            }
        }
        if ($purchase_row_id > 0 && !empty($ci['unit_id'])) {
            $pu = $db->prepare('UPDATE purchases SET unit_id=?, base_qty=? WHERE id=?');
            if ($pu) {
                $saved_unit = (int)$ci['unit_id'];
                $saved_base = (string)($ci['base_qty'] ?? $ci['qty']);
                $pu->bind_param('idi', $saved_unit, $saved_base, $purchase_row_id);
                $pu->execute();
                $pu->close();
            }
        }
        if ($purchase_row_id > 0 && function_exists('current_store_id') && current_store_id() > 0) {
            $store_bind = current_store_id();
            $ps = $db->prepare('UPDATE purchases SET store_id = ? WHERE id = ?');
            if ($ps) {
                $ps->bind_param('ii', $store_bind, $purchase_row_id);
                $ps->execute();
                $ps->close();
            }
        }
    }

    if (!empty($clean_expenses)) {
        $exp_stmt = $db->prepare("INSERT INTO purchase_expenses (memo_no, supplier_id, expense_type, amount, created_by) VALUES (?, ?, ?, ?, ?)");
        foreach ($clean_expenses as $exp) {
            $exp_stmt->bind_param("sisdi", $memo_no, $supplier_id, $exp['type'], $exp['amount'], $created_by);
            $exp_stmt->execute();
        }
    }

    ame_purchase_save_adjustments($db, (string)$memo_no, (int)$supplier_id, $ame_adj);

    ame_purchase_audit_write($db, (string)$memo_no, 'create', null, [
        'supplier_id' => (int)$supplier_id,
        'grand_total' => $grand_total,
        'paid_total' => $paid_total,
        'previous' => $posted_previous_due,
    ], $settle_reason);

    // ফর্মটি অনেকক্ষণ খোলা থাকলে hidden previous_due পুরোনো হয়ে যেতে পারে।
    // ইনভয়েস সেভের সময় সেটি দিয়ে লাইভ সাপ্লায়ার ব্যালেন্স ওভাররাইট করলে
    // আগের ক্রয়/পেমেন্ট হারিয়ে যায়। সাবেক বদলানোর আলাদা audited endpoint
    // আছে; সাধারণ ক্রয়ে সার্ভারের locked live balance-ই একমাত্র সত্য।
    $live_lock = $db->prepare('SELECT total_due FROM suppliers WHERE id = ? FOR UPDATE');
    if (!$live_lock) {
        throw new RuntimeException('সাপ্লায়ার লক করা যায়নি।');
    }
    $live_lock->bind_param('i', $supplier_id);
    $live_lock->execute();
    $live_row = $live_lock->get_result()->fetch_assoc();
    $live_lock->close();
    if (!$live_row) {
        throw new RuntimeException('সাপ্লায়ার খুঁজে পাওয়া যায়নি।');
    }
    $stamp_previous = round((float)($live_row['total_due'] ?? 0), 2);
    if ($customer_id > 0) {
        $stamp_previous = round(-ame_purchase_live_customer_balance($db, $customer_id), 2);
    }

    ame_purchase_stamp_party_dues(
        $db,
        (string)$memo_no,
        (int)$supplier_id,
        (int)$customer_id,
        $grand_total,
        $paid_total,
        $stamp_previous,
        $purchase_date
    );
    ame_running_rebuild_supplier($db, (int)$supplier_id);
    if ($customer_id > 0) {
        ame_purchase_protect_customer_balance($db, (string)$memo_no, (int)$customer_id, (int)$supplier_id);
    }
    ame_running_allocate_payment($db, (int)$supplier_id, (string)$memo_no, $grand_total, $paid_total);

    if ($paid_total > 0 && $account_id > 0) {
        $party_desc = ($customer_id > 0) ? 'কাস্টমার ক্রয় পরিশোধ' : 'ক্রয় পরিশোধ';
        account_debit(
            $account_id,
            $paid_total,
            'supplier_payment',
            $supplier_id,
            $party_desc . ' (মেমো: ' . ($memo_no ?: 'N/A') . ')'
        );
    }

    if ($draft_id > 0) {
        ame_purchase_draft_mark_completed($db, $draft_id, $user_id, $is_admin);
    }

    if (!empty($purchase_token)) {
        finish_idempotency_token($db, 'purchase', $purchase_token, $purchase_reference_id);
    }
    $db->commit();

    if (function_exists('log_activity')) {
        log_activity('Purchase Completed', 'Purchase', (string)$memo_no . ', Total: ' . $grand_total, [
            'action_type' => 'add',
            'entity_type' => 'memo',
            'reference' => (string)$memo_no,
            'party_type' => ($customer_id > 0 ? 'customer' : 'supplier'),
            'party_id' => ($customer_id > 0 ? (int)$customer_id : ($supplier_id ? (int)$supplier_id : 0)),
            'amount_total' => (float)$grand_total,
            'amount_paid' => (float)$paid_total,
            'amount_due' => (float)$bill_due,
        ]);
    }

    header(
        'Location: success.php?memo=' . rawurlencode((string)$memo_no)
        . '&supplier_id=' . (int)$supplier_id
    );
    exit();
} catch (mysqli_sql_exception $e) {
    $db->rollback();
    if ($e->getCode() === 1062) {
        echo "❌ এই Invoice Number ইতোমধ্যে ব্যবহৃত হয়েছে (ডুপ্লিকেট আটকানো হয়েছে)। <a href='add.php'>ফিরে যান</a>";
    } else {
        error_log('Purchase failed: ' . $e->getMessage());
        echo "❌ সিস্টেম এরর হয়েছে, ক্রয় সংরক্ষণ করা যায়নি। <a href='add.php'>ফিরে যান</a>";
    }
} catch (Throwable $e) {
    $db->rollback();
    $raw = $e->getMessage();
    $expected = $e instanceof InvalidArgumentException || str_contains($raw, 'অপর্যাপ্ত ব্যালেন্স');
    if (!$expected) {
        error_log('Purchase failed: ' . $raw);
    }
    $msg = $expected ? $raw : 'সিস্টেম এরর হয়েছে, ক্রয় সংরক্ষণ করা যায়নি।';
    if ($purchase_wants_json && (str_contains($raw, 'অপর্যাপ্ত ব্যালেন্স') || str_contains($raw, 'পর্যাপ্ত টাকা নেই'))) {
        ame_purchase_respond_client(true, $msg, 'insufficient_balance');
    }
    echo '❌ ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . ' <a href="add.php">ফিরে যান</a>';
}
