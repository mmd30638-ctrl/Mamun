<?php
require_once '../../includes/config.php';
require_once '../../includes/attendance_functions.php';
// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।
ensure_attendance_schema($db);

require_post();
csrf_verify();

$action = (string)($_POST['action'] ?? '');
$emp_id = (int)($_POST['emp'] ?? 0);
$date = (string)($_POST['date'] ?? date('Y-m-d'));

if ($emp_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    die('❌ সঠিক কর্মী বা তারিখ নির্বাচন করুন। <a href="employee_list.php">ফিরে যান</a>');
}

$existing = get_attendance_row($db, $emp_id, $date);
$now_time = date('H:i:s');

$office_start = '10:00:00';

if ($action === 'check_in') {
    $status = ($now_time > $office_start) ? 'Late' : 'Present';

    $activated_at = date('Y-m-d H:i:s');
    if ($existing) {
        $stmt = $db->prepare("UPDATE attendance SET check_in = ?, status = ?, activated_at = COALESCE(activated_at, ?), source = COALESCE(source, 'attendance_page') WHERE employee_id = ? AND att_date = ?");
        $stmt->bind_param("sssis", $now_time, $status, $activated_at, $emp_id, $date);
    } else {
        $stmt = $db->prepare("INSERT INTO attendance (employee_id, att_date, check_in, status, activated_at, source) VALUES (?, ?, ?, ?, ?, 'attendance_page')");
        $stmt->bind_param("issss", $emp_id, $date, $now_time, $status, $activated_at);
    }
    $stmt->execute();
    $stmt->close();
    log_activity('Attendance Check-In', 'HRM', 'Employee ID: ' . $emp_id . ' @ ' . $now_time);
} elseif ($action === 'check_out') {
    if ($existing) {
        $stmt = $db->prepare("UPDATE attendance SET check_out = ? WHERE employee_id = ? AND att_date = ?");
        $stmt->bind_param("sis", $now_time, $emp_id, $date);
        $stmt->execute();
        $stmt->close();
        log_activity('Attendance Check-Out', 'HRM', 'Employee ID: ' . $emp_id . ' @ ' . $now_time);
    }
} elseif ($action === 'mark') {
    $status = (string)($_POST['status'] ?? '');
    if (!in_array($status, ['Present', 'Absent', 'Leave', 'Late'], true)) {
        die('❌ সঠিক স্ট্যাটাস নয়। <a href="employee_list.php">ফিরে যান</a>');
    }

    $activated_at = in_array($status, ['Present', 'Late'], true) ? date('Y-m-d H:i:s') : null;
    if ($existing) {
        if ($activated_at) {
            $stmt = $db->prepare("UPDATE attendance SET status = ?, activated_at = COALESCE(activated_at, ?), source = COALESCE(source, 'attendance_page') WHERE employee_id = ? AND att_date = ?");
            $stmt->bind_param("ssis", $status, $activated_at, $emp_id, $date);
        } else {
            $stmt = $db->prepare("UPDATE attendance SET status = ? WHERE employee_id = ? AND att_date = ?");
            $stmt->bind_param("sis", $status, $emp_id, $date);
        }
    } else {
        $stmt = $db->prepare("INSERT INTO attendance (employee_id, att_date, status, activated_at, source) VALUES (?, ?, ?, ?, 'attendance_page')");
        $stmt->bind_param("isss", $emp_id, $date, $status, $activated_at);
    }
    $stmt->execute();
    $stmt->close();
    log_activity('Attendance Status Set', 'HRM', 'Employee ID: ' . $emp_id . ' -> ' . $status);
}

header('Location: employee_list.php');
exit();