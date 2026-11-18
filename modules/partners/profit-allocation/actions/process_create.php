<?php
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_profit_allocation_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$period_label = trim($_POST['period_label'] ?? '');
$period_start = trim($_POST['period_start'] ?? '');
$period_end = trim($_POST['period_end'] ?? '');

// ================= ভ্যালিডেশন =================
if ($period_label === '') {
    $_SESSION['error'] = 'সময়কালের নাম দিন।';
    header('Location: ../create.php');
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_end)) {
    $_SESSION['error'] = 'সঠিক তারিখ দিন।';
    header('Location: ../create.php');
    exit;
}
if (strtotime($period_start) > strtotime($period_end)) {
    $_SESSION['error'] = 'শুরু তারিখ শেষ তারিখের পরে হতে পারবে না।';
    header('Location: ../create.php');
    exit;
}
if (has_overlapping_allocation($db, $period_start, $period_end)) {
    $_SESSION['error'] = 'এই সময়কালের সাথে ওভারল্যাপ করে এমন একটা বণ্টন ইতিমধ্যে আছে। আগেরটা দেখুন।';
    header('Location: ../index.php');
    exit;
}

$partners_result = $db->query("SELECT id, share_percentage FROM partners WHERE status = 'active'");
$partners = $partners_result ? $partners_result->fetch_all(MYSQLI_ASSOC) : [];

if (empty($partners)) {
    $_SESSION['error'] = 'কোনো সক্রিয় পার্টনার পাওয়া যায়নি।';
    header('Location: ../create.php');
    exit;
}

$transaction_started = false;

try {
    $gross_profit = get_partnership_gross_profit_for_period($db, $period_start, $period_end);
    $total_expenses = get_partnership_total_expenses_for_period($db, $period_start, $period_end);
    $total_adjustments = get_partnership_total_adjustments_for_period($db, $period_start, $period_end);
    $net_profit = $gross_profit - $total_expenses - $total_adjustments;

    $db->begin_transaction();
    $transaction_started = true;

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $db->prepare(
        "INSERT INTO profit_allocations (period_label, period_start, period_end, gross_profit, total_expenses, total_adjustments, net_profit, status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'proposed', ?)"
    );
    $stmt->bind_param(
        'sssddddi',
        $period_label, $period_start, $period_end, $gross_profit, $total_expenses, $total_adjustments, $net_profit, $user_id
    );
    $stmt->execute();
    $allocation_id = $stmt->insert_id;
    $stmt->close();

    $detail_stmt = $db->prepare(
        "INSERT INTO profit_allocation_details (allocation_id, partner_id, share_percentage_used, allocated_amount)
         VALUES (?, ?, ?, ?)"
    );
    foreach ($partners as $p) {
        $share_pct = (float)$p['share_percentage'];
        $allocated = round($net_profit * ($share_pct / 100), 2);
        $partner_id = (int)$p['id'];
        $detail_stmt->bind_param('iidd', $allocation_id, $partner_id, $share_pct, $allocated);
        $detail_stmt->execute();
    }
    $detail_stmt->close();

    log_activity('Profit Allocation Proposed', 'Partnership', $period_label . ' — নিট লাভ ৳' . $net_profit);

    $db->commit();
    $transaction_started = false;

    $_SESSION['success'] = 'প্রস্তাবিত লাভ বণ্টন তৈরি হয়েছে। এখন যাচাই করে অনুমোদন করুন।';
    header('Location: ../view.php?id=' . $allocation_id);
    exit;
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    error_log('Profit allocation create failed: ' . $e->getMessage());
    $_SESSION['error'] = 'বণ্টন তৈরিতে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../create.php');
    exit;
}
