<?php
require_once '../../includes/config.php';
require_once '../../includes/leave_functions.php';
// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।
ensure_leave_schema($db);

require_post();
csrf_verify();

$action = (string)($_POST['action'] ?? '');

if ($action === 'apply') {
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $leave_type = clean_input($_POST['leave_type'] ?? 'সাধারণ ছুটি');
    $start_date = (string)($_POST['start_date'] ?? '');
    $end_date = (string)($_POST['end_date'] ?? '');
    $reason = clean_input($_POST['reason'] ?? '');

    if ($employee_id <= 0 || $start_date === '' || $end_date === '') {
        die('❌ কর্মী ও তারিখ অবশ্যই দিতে হবে। <a href="leave.php">ফিরে যান</a>');
    }

    $start_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);

    if ($start_ts === false || $end_ts === false || $end_ts < $start_ts) {
        die('❌ তারিখ সঠিক নয়। <a href="leave.php">ফিরে যান</a>');
    }

    $total_days = (int)round(($end_ts - $start_ts) / 86400) + 1;

    $stmt = $db->prepare(
        "INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, total_days, reason, status)
         VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
    );
    $stmt->bind_param("isssis", $employee_id, $leave_type, $start_date, $end_date, $total_days, $reason);
    $stmt->execute();
    $stmt->close();

    log_activity('Leave Applied', 'HRM', 'Employee ID: ' . $employee_id . ' (' . $total_days . ' days)');

    echo "<script>alert('ছুটির আবেদন জমা হয়েছে'); window.location='leave.php';</script>";
    exit();
}

if ($action === 'decide') {
    $id = (int)($_POST['id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');

    if ($id <= 0 || !in_array($decision, ['Approved', 'Rejected', 'Cancelled'], true)) {
        die('❌ সঠিক তথ্য নয়। <a href="leave.php">ফিরে যান</a>');
    }

    $admin_id = (int)($_SESSION['user_id'] ?? 0);

    $stmt = $db->prepare(
        "UPDATE leave_requests SET status = ?, decided_by = ?, decided_at = NOW() WHERE id = ?"
    );
    $stmt->bind_param("sii", $decision, $admin_id, $id);
    $stmt->execute();
    $stmt->close();

    log_activity('Leave Decision', 'HRM', 'Leave ID: ' . $id . ' -> ' . $decision);

    header('Location: leave.php');
    exit();
}

header('Location: leave.php');
exit();