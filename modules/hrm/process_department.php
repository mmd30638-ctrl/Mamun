<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';

// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।
require_post();
csrf_verify();
ensure_hrm_schema($db);

$action = (string)($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

if ($action === 'add' || $action === 'edit') {
    $dept_name = trim((string)($_POST['dept_name'] ?? ''));
    if ($dept_name === '') {
        die('❌ বিভাগের নাম দিন। <a href="departments.php">ফিরে যান</a>');
    }

    if ($action === 'add') {
        $stmt = $db->prepare('INSERT IGNORE INTO departments (dept_name) VALUES (?)');
        $activity = 'Department Added';
    } else {
        if ($id <= 0) {
            die('❌ সঠিক বিভাগ নির্বাচন করুন। <a href="departments.php">ফিরে যান</a>');
        }
        $stmt = $db->prepare('UPDATE departments SET dept_name = ? WHERE id = ?');
        $activity = 'Department Updated';
    }

    if (!$stmt) {
        die('❌ বিভাগ সংরক্ষণ করা যায়নি। <a href="departments.php">ফিরে যান</a>');
    }
    if ($action === 'add') {
        $stmt->bind_param('s', $dept_name);
    } else {
        $stmt->bind_param('si', $dept_name, $id);
    }
    $stmt->execute();
    $stmt->close();
    log_activity($activity, 'HRM', $dept_name);
    header('Location: departments.php');
    exit();
}

if ($action === 'toggle_status') {
    if ($id <= 0) {
        die('❌ সঠিক বিভাগ নির্বাচন করুন। <a href="departments.php">ফিরে যান</a>');
    }

    $stmt = $db->prepare(
        "UPDATE departments
         SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END
         WHERE id = ?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    log_activity('Department Status Changed', 'HRM', 'Department ID: ' . $id);
    header('Location: departments.php');
    exit();
}

header('Location: departments.php');
exit();
