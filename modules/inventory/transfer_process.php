<?php
require_once '../../includes/config.php';
require_once '../../includes/workshop_functions.php';
ensure_workshop_schema($db);
require_permission($db, 'm-inv');
require_post();
csrf_verify();

$from = (int)($_POST['from_store_id'] ?? 0);
$to = (int)($_POST['to_store_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$qty = (float)($_POST['qty'] ?? 0);
$note = trim((string)($_POST['note'] ?? ''));
$user_id = (int)($_SESSION['user_id'] ?? 0);

$fail = static function (string $msg): void {
    header('Location: transfer.php?err=' . rawurlencode($msg));
    exit;
};

if ($from <= 0 || $to <= 0 || $from === $to) {
    $fail('দুইটি আলাদা স্টোর বাছুন।');
}
if ($product_id <= 0 || $qty <= 0) {
    $fail('পণ্য ও পরিমাণ দিন।');
}
if (function_exists('ame_user_can_access_store')) {
    if (!ame_user_can_access_store($db, $from) || !ame_user_can_access_store($db, $to)) {
        $fail('এই স্টোরে আপনার অনুমতি নেই।');
    }
}

$avail = ame_workshop_available_qty($db, $product_id, $from);
if ($avail + 0.0001 < $qty) {
    $fail('পাঠানো স্টোরে পর্যাপ্ত অবশিষ্ট স্টক নেই। অবশিষ্ট: ' . $avail);
}

try {
    $db->begin_transaction();
    $no = ame_workshop_next_no($db, 'store_transfers', 'TR');
    $stmt = $db->prepare(
        "INSERT INTO store_transfers (transfer_no, from_store_id, to_store_id, status, note, created_by, completed_at)
         VALUES (?, ?, ?, 'completed', ?, ?, NOW())"
    );
    $stmt->bind_param('siisi', $no, $from, $to, $note, $user_id);
    $stmt->execute();
    $tid = (int)$stmt->insert_id;
    $stmt->close();

    $item = $db->prepare(
        'INSERT INTO store_transfer_items (transfer_id, product_id, qty) VALUES (?, ?, ?)'
    );
    $item->bind_param('iid', $tid, $product_id, $qty);
    $item->execute();
    $item->close();

    if (!ame_store_stock_change($db, $from, $product_id, -$qty, true)) {
        throw new RuntimeException('পাঠানো স্টোর থেকে স্টক কাটা যায়নি।');
    }
    if (!ame_store_stock_change($db, $to, $product_id, $qty, false)) {
        throw new RuntimeException('গন্তব্য স্টোরে স্টক যোগ হয়নি।');
    }
    $db->commit();
    header('Location: transfer.php?ok=1');
    exit;
} catch (Throwable $e) {
    try {
        $db->rollback();
    } catch (Throwable $ignored) {
    }
    error_log('store transfer: ' . $e->getMessage());
    $fail($e instanceof RuntimeException ? $e->getMessage() : 'ট্রান্সফার হয়নি।');
}
