<?php
/**
 * ক্রয় মেমো এডিট — সেভ হ্যান্ডলার।
 *
 * modules/purchase/add.php-এর একই প্রফেশনাল ক্রয়-স্ক্রিন থেকে ("✏️ এডিট"
 * বাটনে ক্লিক করলে ?edit_memo=... নিয়ে ওই পেজেই মেমোটা কার্টে লোড হয়ে
 * যায়) "💾 মেমো আপডেট নিশ্চিত করুন" চাপলে এই ফাইলে POST আসে। ফর্মের
 * ফিল্ড-নাম process_purchase.php-এর সাথে হুবহু এক (items_json-এ
 * dealer_price/wholesale_price/sale_price সমেত পূর্ণ তথ্য থাকে), তাই
 * এই ফাইলের মূল ক্যালকুলেশন লজিকও process_purchase.php-এর সাথে হুবহু মিল
 * রাখা হয়েছে — শুধু শুরুতে "পুরনো মেমোর প্রভাব বাতিল" ধাপ যোগ করা হয়েছে।
 *
 * নীতি: আগের মেমোর প্রভাব (স্টক বৃদ্ধি, সাপ্লায়ারের বাকি, অ্যাকাউন্ট থেকে
 * পরিশোধ) প্রথমে পুরোপুরি উল্টে দেওয়া হয়, তারপর নতুন তথ্য দিয়ে আবার নতুন
 * ক্রয়ের মতোই হিসাব করে বসানো হয় — সবটাই একটাই DB ট্রানজেকশনের ভেতরে,
 * যাতে মাঝপথে সমস্যা হলে কিছুই আধা-পরিবর্তিত না থেকে যায়।
 *
 * মেমো নম্বর এখন এডিট করা যায় (আগে রিড-অনলি ছিল)। এজন্য দুটো আলাদা
 * ভ্যারিয়েবল ব্যবহার করা হয়: $original_memo_no (POST-এর "original_memo_no"
 * থেকে — এটা দিয়েই পুরনো সারি খুঁজে বের করে মোছা হয়) এবং $memo_no
 * (POST-এর "memo_no" থেকে — ইউজার যা টাইপ করেছে/রেখে দিয়েছে, নতুন সারি
 * এই নম্বর দিয়েই ঢোকে)। মেমো নম্বর অপরিবর্তিত থাকলে দুটোর মান একই থাকে।
 */
require_once '../../includes/config.php';
require_once '../../includes/unit_engine.php';
ame_unit_ensure($db);
require_once '../../includes/account_functions.php';
require_once '../../includes/purchase_functions.php';
require_once '../../includes/purchase_draft.php';
require_once '../../includes/integrity_functions.php';
ame_purchase_require_access($db);
ensure_purchase_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add.php');
    exit();
}

$session_token = (string)($_SESSION['csrf_token'] ?? '');
$posted_token = (string)($_POST['csrf_token'] ?? '');
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    die('❌ নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন। <a href="add.php">ফিরে যান</a>');
}

$memo_no = ame_purchase_normalize_memo($_POST['memo_no'] ?? '');
if ($memo_no === '') {
    die('❌ মেমো নম্বর পাওয়া যায়নি — এডিট করা যাবে না। <a href="history.php">ফিরে যান</a>');
}

// ================= মূল (আসল) মেমো নম্বর বনাম নতুন/পরিবর্তিত মেমো নম্বর =================
// $memo_no উপরে POST থেকে এসেছে — এটাই সেভ হওয়ার পর এই ক্রয়ের চূড়ান্ত
// মেমো নম্বর হবে (ইউজার চাইলে বদলাতে পারে)। কিন্তু ডাটাবেজে পুরনো সারি
// (লাইন-আইটেম, খরচ) খুঁজে বের করে মুছতে হবে সেই মেমো নম্বর দিয়ে যেটা
// এতদিন রেকর্ডে ছিল — তাই সেটা আলাদা ভ্যারিয়েবলে ($original_memo_no)
// রাখা হলো, যাতে মেমো নম্বর বদলালেও পুরনো রেকর্ড ঠিকভাবে খুঁজে পাওয়া যায়।
$original_memo_no = ame_purchase_normalize_memo($_POST['original_memo_no'] ?? '');
if ($original_memo_no === '') {
    die('❌ মূল মেমো নম্বর পাওয়া যায়নি — এডিট করা যাবে না। <a href="history.php">ফিরে যান</a>');
}
$original_supplier_id = (int)($_POST['original_supplier_id'] ?? 0);

$new_supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
$new_customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$party_type = trim((string)($_POST['party_type'] ?? ''));
if ($new_customer_id <= 0 && $new_supplier_id > 0 && $party_type === 'customer') {
    $linked = ame_purchase_customer_ids_for_supplier($db, (int)$new_supplier_id);
    if ($linked) {
        $new_customer_id = (int)$linked[0];
    }
}
$purchase_date = trim((string)($_POST['purchase_date'] ?? ''));
$notes = substr(trim((string)($_POST['notes'] ?? '')), 0, 500);
$items_raw = json_decode($_POST['items_json'] ?? '[]', true);
$expenses_raw = json_decode($_POST['expenses_json'] ?? '[]', true);

// ================= ক্রয়ের তারিখ যাচাই =================
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase_date)) {
    $purchase_date = date('Y-m-d');
}
if (strtotime($purchase_date) > strtotime('+1 day')) {
    die('❌ ক্রয়ের তারিখ ভবিষ্যতের হতে পারবে না। <a href="add.php?edit_memo=' . urlencode($original_memo_no) . '">ফিরে যান</a>');
}
$purchase_datetime = $purchase_date . ' ' . date('H:i:s');

if (!is_array($items_raw) || empty($items_raw)) {
    die('❌ কমপক্ষে একটা পণ্য যোগ করুন। <a href="add.php?edit_memo=' . urlencode($original_memo_no) . '">ফিরে যান</a>');
}

// ================= প্রতিটা লাইন আইটেম সার্ভার-সাইডে যাচাই ও পরিষ্কার =================
$clean_items = [];
$items_total = 0;

foreach ($items_raw as $item) {
    $product_id = (int)($item['product_id'] ?? 0);
    $qty = round((float)($item['qty'] ?? 0), 2);
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
        die('❌ মূল্য সঠিকভাবে লিখুন। <a href="add.php?edit_memo=' . urlencode($original_memo_no) . '">ফিরে যান</a>');
    }

    // ================= বিক্রয় রেট ক্রয় রেটের কম হতে পারবে না =================
    $prod_name_stmt = $db->prepare('SELECT name FROM products WHERE id = ?');
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
        die('❌ ' . htmlspecialchars($price_err) . ' <a href="add.php?edit_memo=' . urlencode($original_memo_no) . '">ফিরে যান</a>');
    }

    $line_total = round($qty * $pp, 2);
    $items_total += $line_total;

    $clean_items[] = [
        'product_id' => $product_id, 'qty' => $qty, 'purchase_price' => $pp,
        'dealer_price' => $dp, 'wholesale_price' => $wp, 'sale_price' => $sp, 'total_cost' => $line_total,
        'free_qty' => $free_qty, 'stock_qty' => round($qty + $free_qty, 2),
    ];
}

if (empty($clean_items)) {
    die('❌ বৈধ কোনো পণ্যের এন্ট্রি পাওয়া যায়নি। <a href="add.php?edit_memo=' . urlencode($original_memo_no) . '">ফিরে যান</a>');
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
    die('❌ ' . htmlspecialchars($ame_adj['error'], ENT_QUOTES, 'UTF-8') . ' <a href="add.php?edit_memo=' . urlencode($original_memo_no) . '">ফিরে যান</a>');
}
$grand_total = $ame_adj['grand_total'];
$ame_adjust_total = round($grand_total - $items_total, 2);

if (!$new_supplier_id) {
    die('❌ সাপ্লায়ার নির্বাচন করুন। <a href="add.php?edit_memo=' . urlencode($original_memo_no) . '">ফিরে যান</a>');
}

$paid_total = round((float)($_POST['paid_amount'] ?? $_POST['advance_amount'] ?? 0), 2);
if ($paid_total < 0) {
    die('❌ পরিশোধ ঋণাত্মক হতে পারবে না। <a href="add.php?edit_memo=' . urlencode($original_memo_no) . '">ফিরে যান</a>');
}

$account_id = (int)($_POST['account_id'] ?? 0);
if ($account_id <= 0 && !empty($_POST['advance_account_id'])) {
    $account_id = (int)$_POST['advance_account_id'];
}
if ($account_id <= 0 && !empty($_POST['purchase_pay_account'])) {
    $account_id = (int)$_POST['purchase_pay_account'];
}
if ($paid_total > 0.009 && $account_id <= 0) {
    die('❌ পরিশোধের টাকা যে অ্যাকাউন্ট থেকে যাবে সেটি নির্বাচন করুন। <a href="add.php?edit_memo=' . urlencode($original_memo_no) . '">ফিরে যান</a>');
}
if ($paid_total <= 0.009) {
    $paid_total = 0.0;
}
$pay_math = ame_purchase_payment_result(0.0, $grand_total, $paid_total);
$due_delta = round($grand_total - $pay_math['paid'], 2);
$payment_type = $due_delta > 0.009 ? 'credit' : 'cash';
$account_id_bind = $account_id > 0 ? $account_id : 0;

$purchase_token = substr(trim((string)($_POST['idempotency_token'] ?? '')), 0, 80);
$ame_purchase_lock_keys = [
    'purchase_memo_' . (int)$original_supplier_id . '_' . substr(sha1($original_memo_no), 0, 32),
    'purchase_memo_' . (int)$new_supplier_id . '_' . substr(sha1($memo_no), 0, 32),
];
$ame_purchase_lock_keys = array_values(array_unique($ame_purchase_lock_keys));
sort($ame_purchase_lock_keys, SORT_STRING);
foreach ($ame_purchase_lock_keys as $ame_purchase_lock_key) {
    if (!ame_lock($db, $ame_purchase_lock_key)) {
        die('❌ এই মেমোটি অন্য একটি ক্রয়ে আপডেট হচ্ছে। কয়েক সেকেন্ড পরে আবার চেষ্টা করুন।');
    }
}
// die()/exception যেভাবেই স্ক্রিপ্ট শেষ হোক না কেন লকটা যেন সবসময় ছেড়ে
// দেওয়া হয় — shutdown-এ পাকাপাকিভাবে নিশ্চিত করা হচ্ছে।
register_shutdown_function(static function () use ($db, $ame_purchase_lock_keys) {
    if ($db instanceof mysqli) {
        foreach ($ame_purchase_lock_keys as $ame_purchase_lock_key) {
            ame_unlock($db, $ame_purchase_lock_key);
        }
    }
});

    $db->begin_transaction();
try {
    $purchase_replay = 0;
    if ($purchase_token === '' || !claim_idempotency_token($db, 'purchase_edit', $purchase_token, $purchase_replay)) {
        $db->rollback();
        die('❌ এই মেমো আপডেট ইতিমধ্যে সংরক্ষিত হয়েছে — ডাবল এন্ট্রি আটকানো হয়েছে। <a href="history.php">ইতিহাস দেখুন</a>');
    }
    $old_snap = ame_purchase_audit_snapshot($db, (string)$original_memo_no, $original_supplier_id);
    ame_purchase_audit_write($db, (string)$original_memo_no, 'edit_before', $old_snap, null, trim((string)($_POST['settle_reason'] ?? '')));
    // ================= পুরনো তথ্য লক করে পড়া =================
    $head_scope = $original_supplier_id > 0 ? ' AND supplier_id = ?' : '';
    $head_stmt = $db->prepare(
        'SELECT supplier_id, customer_id, account_id, created_by, previous_supplier_due FROM purchases WHERE memo_no = ?'
        . $head_scope . ' ORDER BY id ASC LIMIT 1 FOR UPDATE'
    );
    if (!$head_stmt) {
        $head_stmt = $db->prepare(
            'SELECT supplier_id, customer_id, account_id, created_by FROM purchases WHERE memo_no = ?'
            . $head_scope . ' ORDER BY id ASC LIMIT 1 FOR UPDATE'
        );
    }
    if (!$head_stmt) {
        $head_stmt = $db->prepare(
            'SELECT supplier_id, account_id, created_by FROM purchases WHERE memo_no = ?'
            . $head_scope . ' ORDER BY id ASC LIMIT 1 FOR UPDATE'
        );
    }
    if ($original_supplier_id > 0) {
        $head_stmt->bind_param('si', $original_memo_no, $original_supplier_id);
    } else {
        $head_stmt->bind_param('s', $original_memo_no);
    }
    $head_stmt->execute();
    $old_head = $head_stmt->get_result()->fetch_assoc();
    $head_stmt->close();
    if (!$old_head) {
        throw new RuntimeException('এই মেমো নম্বরে কোনো ক্রয় খুঁজে পাওয়া যায়নি — হয়তো ইতিমধ্যে মুছে ফেলা হয়েছে।');
    }
    $old_supplier_id = (int)$old_head['supplier_id'];
    $old_customer_id = (int)($old_head['customer_id'] ?? 0);
    $old_account_id = (int)$old_head['account_id'];
    $old_created_by = $old_head['created_by'] !== null ? (int)$old_head['created_by'] : null;
    $true_previous = function_exists('ame_running_previous_before_memo')
        ? ame_running_previous_before_memo($db, $old_supplier_id, (string)$original_memo_no)
        : (isset($old_head['previous_supplier_due']) && $old_head['previous_supplier_due'] !== null
            ? round((float)$old_head['previous_supplier_due'], 2)
            : null);

    $sum_stmt = ame_purchase_prepare_memo(
        $db,
        'SELECT COALESCE(SUM(total_cost),0) AS grand, COALESCE(SUM(paid_amount),0) AS paid FROM purchases WHERE memo_no = ?',
        $original_memo_no,
        $old_supplier_id
    );
    $sum_stmt->execute();
    $old_sum = $sum_stmt->get_result()->fetch_assoc();
    $sum_stmt->close();
    $old_grand_total = round((float)($old_sum['grand'] ?? 0), 2);
    $old_paid_total = round((float)($old_sum['paid'] ?? 0), 2);
    $old_due_delta = round($old_grand_total - $old_paid_total, 2);
    $true_previous_customer = ($old_customer_id > 0 && function_exists('ame_purchase_customer_previous_before_memo'))
        ? ame_purchase_customer_previous_before_memo($db, $old_customer_id, (string)$original_memo_no)
        : null;

    $base_col = $db->query("SHOW COLUMNS FROM purchases LIKE 'base_qty'");
    $line_select = ($base_col && $base_col->num_rows > 0)
        ? 'SELECT product_id, qty, base_qty FROM purchases WHERE memo_no = ?'
        : 'SELECT product_id, qty FROM purchases WHERE memo_no = ?';
    $old_lines_stmt = ame_purchase_prepare_memo($db, $line_select, $original_memo_no, $old_supplier_id);
    $old_lines_stmt->execute();
    $old_lines = $old_lines_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $old_lines_stmt->close();

    // ================= সাপ্লায়ার বদল হলে ডেডলক এড়াতে দুই সাপ্লায়ারই একসাথে,
    //                   সবসময় একই (ছোট আইডি আগে) ক্রমে লক করা হয় =================
    // কারণ: আগে পুরনো সাপ্লায়ার লক করে পরে নতুনটা লক করা হতো — দুইজন
    // এডমিন একই সময় দুটো ভিন্ন মেমোতে উল্টো ক্রমে (X→Y ও Y→X) সাপ্লায়ার
    // বদলালে একে অপরের লক আটকে দিয়ে ডেডলক তৈরি করতে পারতো। এখন থেকে
    // দুটো সাপ্লায়ারকেই আইডি অনুযায়ী ছোট-থেকে-বড় ক্রমে একবারে লক করা হয়,
    // তাই সব ট্রানজেকশনই একই ক্রমে লক নেয় — ডেডলক হওয়ার কোনো সুযোগ থাকে না।
    $ame_supplier_ids = array_values(array_unique(array_filter(
        [$old_supplier_id, $new_supplier_id],
        static function ($v) { return (int)$v > 0; }
    )));
    sort($ame_supplier_ids, SORT_NUMERIC);
    $ame_supplier_due_map = [];
    if (count($ame_supplier_ids) === 1) {
        $sup_lock_stmt = $db->prepare('SELECT id, total_due FROM suppliers WHERE id = ? FOR UPDATE');
        $sup_lock_stmt->bind_param('i', $ame_supplier_ids[0]);
        $sup_lock_stmt->execute();
        foreach ($sup_lock_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $ame_supplier_due_map[(int)$row['id']] = (float)$row['total_due'];
        }
        $sup_lock_stmt->close();
    } elseif (count($ame_supplier_ids) === 2) {
        $sup_lock_stmt = $db->prepare('SELECT id, total_due FROM suppliers WHERE id IN (?, ?) ORDER BY id ASC FOR UPDATE');
        $sup_lock_stmt->bind_param('ii', $ame_supplier_ids[0], $ame_supplier_ids[1]);
        $sup_lock_stmt->execute();
        foreach ($sup_lock_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $ame_supplier_due_map[(int)$row['id']] = (float)$row['total_due'];
        }
        $sup_lock_stmt->close();
    }

    // ================= ধাপ ১: পুরনো স্টক-বৃদ্ধি উল্টে দেওয়া =================
    foreach ($old_lines as $ol) {
        $pid = (int)$ol['product_id'];
        $qty = (isset($ol['base_qty']) && $ol['base_qty'] !== null && (float)$ol['base_qty'] > 0) ? (float)$ol['base_qty'] : (float)$ol['qty'];
        if ($pid <= 0 || $qty <= 0) {
            continue;
        }
        if (function_exists('ame_store_stock_change') && function_exists('current_store_id') && current_store_id() > 0) {
            ame_store_stock_change($db, current_store_id(), $pid, -$qty, false);
        } else {
            $rev = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            $rev->bind_param('di', $qty, $pid);
            $rev->execute();
            $rev->close();
        }
    }

    // ================= ধাপ ২: পুরনো পরিশোধ (যদি থাকে) অ্যাকাউন্টে ফেরত =================
    if ($old_paid_total > 0 && $old_account_id > 0) {
        account_credit(
            $old_account_id,
            $old_paid_total,
            'supplier_payment_reversal',
            $old_supplier_id,
            'ক্রয় মেমো এডিট — পুরনো পরিশোধ বাতিল (মেমো: ' . $original_memo_no . ')'
        );
    }

    // ================= ধাপ ৩: পুরনো সাপ্লায়ার/কাস্টমারের বাকি থেকে এই মেমোর অংশ বাদ =================
    if ($old_customer_id > 0) {
        ame_purchase_customer_ledger_revert($db, (string)$original_memo_no, $old_customer_id);
    }
    if ($old_supplier_id > 0 && isset($ame_supplier_due_map[$old_supplier_id])) {
        $reverted_due = round($ame_supplier_due_map[$old_supplier_id] - $old_due_delta, 2);
        $upd = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
        $upd->bind_param('di', $reverted_due, $old_supplier_id);
        $upd->execute();
        $upd->close();
        $ame_supplier_due_map[$old_supplier_id] = $reverted_due;
        ame_purchase_sync_linked_customer_due($db, $old_supplier_id, $old_customer_id, $reverted_due);
    }

    // ================= ধাপ ৪: পুরনো লাইন-আইটেম ও খরচ মুছে ফেলা =================
    $del1 = ame_purchase_prepare_memo($db, 'DELETE FROM purchases WHERE memo_no = ?', $original_memo_no, $old_supplier_id);
    $del1->execute();
    $del1->close();

    $del2 = ame_purchase_prepare_memo($db, 'DELETE FROM purchase_expenses WHERE memo_no = ?', $original_memo_no, $old_supplier_id);
    $del2->execute();
    $del2->close();
    ame_purchase_delete_adjustments($db, (string)$original_memo_no, (int)$old_supplier_id);

    // ================= নতুন (সেভ হতে যাওয়া) মেমো নম্বর অন্য কোনো ক্রয়ে
    //                   আগে থেকেই ব্যবহৃত কিনা — এতক্ষণে পুরনো সারি মুছে
    //                   ফেলা হয়ে গেছে, তাই মেমো নম্বর অপরিবর্তিত থাকলেও এই
    //                   চেক ভুলভাবে "ডুপ্লিকেট" দেখাবে না =================
    if (purchase_memo_exists($db, $new_supplier_id, $memo_no)) {
        throw new RuntimeException('এই সাপ্লায়ারের নামে এই Invoice/Memo নম্বর ইতিমধ্যে অন্য একটা ক্রয়ে ব্যবহৃত আছে।');
    }

    // ================= ধাপ ৫: নতুন তথ্য দিয়ে আবার বসানো (process_purchase.php-এর মতোই) =================
    $created_by = $old_created_by ?? ($_SESSION['user_id'] ?? null);
    $item_count = count($clean_items);

    // process_purchase.php-এর মতোই: শেষ লাইনে অবশিষ্ট অংশ বসিয়ে রাউন্ডিং
    // ড্রিফট এড়ানো হচ্ছে, যাতে সব লাইনের যোগফল ঠিক গ্র্যান্ড-টোটাল/
    // পেইড-টোটালের সমান থাকে (নিচের ব্যাখ্যা প্রসেস_পার্চেজ.php-তে আছে)।
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
        $stmt->bind_param('ddddi', $ci['purchase_price'], $ci['dealer_price'], $ci['wholesale_price'], $ci['sale_price'], $ci['product_id']);
        $stmt->execute();
        $stmt->close();

        if (function_exists('ame_store_stock_change') && function_exists('current_store_id') && current_store_id() > 0) {
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
            'INSERT INTO purchases (supplier_id, memo_no, product_id, qty, purchase_price, dealer_price, wholesale_price, sale_price, total_cost, account_id, created_by, payment_type, paid_amount, due_amount, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        // টাইপ: i(supplier_id) s(memo_no) i(product_id) + 6×d(qty,pp,dp,wp,sp,total_cost) + i(account_id) i(created_by) s(payment_type) + 2×d(paid,due) + s(notes) + s(created_at) = ১৬টি
        $ins->bind_param(
            'isiddddddiisddss',
            $new_supplier_id, $memo_no, $ci['product_id'], $ci['qty'], $ci['purchase_price'], $ci['dealer_price'], $ci['wholesale_price'], $ci['sale_price'],
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
        $ins->close();
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
        $exp_stmt = $db->prepare('INSERT INTO purchase_expenses (memo_no, supplier_id, expense_type, amount, created_by) VALUES (?, ?, ?, ?, ?)');
        foreach ($clean_expenses as $exp) {
            $exp_stmt->bind_param('sisdi', $memo_no, $new_supplier_id, $exp['type'], $exp['amount'], $created_by);
            $exp_stmt->execute();
        }
        $exp_stmt->close();
    }

    // ================= ধাপ ৬: নতুন সাপ্লায়ার/কাস্টমারের বাকি আপডেট =================
    if (!isset($ame_supplier_due_map[$new_supplier_id])) {
        throw new RuntimeException('সাপ্লায়ার খুঁজে পাওয়া যায়নি।');
    }
    $actual_previous = (float)$ame_supplier_due_map[$new_supplier_id];
    if ((int)$old_supplier_id === (int)$new_supplier_id && $true_previous !== null) {
        $actual_previous = (float)$true_previous;
    }
    $posted_previous = ame_purchase_posted_previous_due($_POST, (int)$new_customer_id);
    $stamp_previous = $posted_previous !== null ? (float)$posted_previous : $actual_previous;
    if ($new_customer_id > 0) {
        if ($old_customer_id === $new_customer_id && $true_previous_customer !== null) {
            // পুরনো customer ledger-এ মেমোর আগের running balance-ই এডিটের
            // নির্ভরযোগ্য সাবেক; supplier running balance দিয়ে এটি শূন্য করা যাবে না।
            $stamp_previous = round(-$true_previous_customer, 2);
        } else {
            $live_cust = ame_purchase_live_customer_balance($db, (int)$new_customer_id);
            $posted_cust = isset($_POST['customer_balance']) && $_POST['customer_balance'] !== ''
                ? ame_purchase_parse_money($_POST['customer_balance'])
                : round(-$stamp_previous, 2);
            if (abs($posted_cust - $live_cust) <= 0.02) {
                $stamp_previous = round(-$live_cust, 2);
            }
        }
    }
    $skip_supplier_settle = ($new_customer_id > 0 && abs($actual_previous) < 0.02);
    if (!$skip_supplier_settle && abs($stamp_previous - $actual_previous) > 0.02) {
        $settled = ame_running_settle_previous(
            $db,
            'supplier',
            (int)$new_supplier_id,
            $actual_previous,
            $stamp_previous,
            strtolower(trim((string)($_POST['settle_type'] ?? ''))),
            trim((string)($_POST['settle_reason'] ?? '')),
            (string)$memo_no,
            false
        );
        if (!$settled['ok']) {
            throw new RuntimeException($settled['message']);
        }
        $stamp_previous = $settled['previous'];
    }

    ame_purchase_save_adjustments($db, (string)$memo_no, (int)$new_supplier_id, $ame_adj);

    ame_purchase_stamp_party_dues(
        $db,
        (string)$memo_no,
        (int)$new_supplier_id,
        (int)$new_customer_id,
        $grand_total,
        $paid_total,
        $stamp_previous,
        $purchase_date
    );
    ame_running_rebuild_supplier($db, (int)$new_supplier_id);
    if ($new_customer_id > 0) {
        ame_purchase_protect_customer_balance($db, (string)$memo_no, (int)$new_customer_id, (int)$new_supplier_id);
    }
    if ($old_supplier_id > 0 && $old_supplier_id !== (int)$new_supplier_id) {
        ame_running_rebuild_supplier($db, (int)$old_supplier_id);
        $old_wipe = $db->prepare('DELETE FROM purchase_payment_allocations WHERE source_memo = ? AND supplier_id = ?');
        if ($old_wipe) {
            $old_wipe->bind_param('si', $original_memo_no, $old_supplier_id);
            $old_wipe->execute();
            $old_wipe->close();
        }
    }
    ame_running_allocate_payment($db, (int)$new_supplier_id, (string)$memo_no, $grand_total, $paid_total);
    ame_purchase_audit_write($db, (string)$memo_no, 'edit_after', $old_snap, [
        'supplier_id' => (int)$new_supplier_id,
        'grand_total' => $grand_total,
        'paid_total' => $paid_total,
        'previous' => $stamp_previous,
    ], trim((string)($_POST['settle_reason'] ?? '')));

    // ================= ধাপ ৭: নতুন পরিশোধ (যদি থাকে) অ্যাকাউন্ট থেকে কাটা =================
    if ($paid_total > 0 && $account_id > 0) {
        account_debit(
            $account_id,
            $paid_total,
            'supplier_payment',
            $new_supplier_id,
            'ক্রয় পরিশোধ — এডিট করা মেমো (মেমো: ' . $memo_no . ')'
        );
    }

    if (function_exists('log_activity')) {
        $ame_edit_log_msg = ($original_memo_no !== $memo_no)
            ? 'ক্রয় মেমো এডিট করা হয়েছে — মেমো নম্বর বদলানো হয়েছে: ' . $original_memo_no . ' → ' . $memo_no
            : 'ক্রয় মেমো এডিট করা হয়েছে — মেমো: ' . $memo_no;
        log_activity('update', 'purchase', $ame_edit_log_msg);
    }

    finish_idempotency_token($db, 'purchase_edit', $purchase_token, $purchase_reference_id);
    $db->commit();
    header(
        'Location: success.php?memo=' . rawurlencode((string)$memo_no)
        . '&supplier_id=' . (int)$new_supplier_id
    );
    exit();
} catch (mysqli_sql_exception $e) {
    $db->rollback();
    error_log('Purchase edit failed: ' . $e->getMessage());
    echo "❌ সিস্টেম এরর হয়েছে, মেমো এডিট সংরক্ষণ করা যায়নি। কোনো তথ্য বদলায়নি। <a href='add.php?edit_memo=" . urlencode($original_memo_no) . "'>ফিরে যান</a>";
} catch (Throwable $e) {
    $db->rollback();
    error_log('Purchase edit failed: ' . $e->getMessage());
    $msg = $e instanceof RuntimeException ? $e->getMessage() : 'সিস্টেম এরর হয়েছে, মেমো এডিট সংরক্ষণ করা যায়নি। কোনো তথ্য বদলায়নি।';
    echo '❌ ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . " <a href='add.php?edit_memo=" . urlencode($original_memo_no) . "'>ফিরে যান</a>";
}
