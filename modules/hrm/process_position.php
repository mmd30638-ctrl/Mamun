<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।
ensure_hrm_schema($db);

require_post();
csrf_verify();

$action = (string)($_POST['action'] ?? '');

if ($action === 'add') {
    $position_name = clean_input($_POST['position_name'] ?? '');

    if ($position_name === '') {
        die('❌ পদের নাম দিন। <a href="departments.php">ফিরে যান</a>');
    }

    $stmt = $db->prepare("INSERT IGNORE INTO positions (position_name) VALUES (?)");
    $stmt->bind_param("s", $position_name);
    if ($stmt->execute()) {
        log_activity('Position Added', 'HRM', $position_name);
        echo "<script>alert('পদ সফলভাবে যোগ হয়েছে'); window.location='departments.php';</script>";
    } else {
        echo "❌ এরর: " . htmlspecialchars($db->error, ENT_QUOTES, 'UTF-8');
    }
    $stmt->close();
    exit();
}

if ($action === 'edit') {
    $edit_id = (int)($_POST['id'] ?? 0);
    $position_name = clean_input($_POST['position_name'] ?? '');

    if ($edit_id <= 0 || $position_name === '') {
        die('❌ সঠিক তথ্য দিন। <a href="departments.php">ফিরে যান</a>');
    }

    $stmt = $db->prepare("UPDATE positions SET position_name = ? WHERE id = ?");
    $stmt->bind_param("si", $position_name, $edit_id);
    if ($stmt->execute()) {
        log_activity('Position Updated', 'HRM', $position_name);
        echo "<script>alert('পদ আপডেট হয়েছে'); window.location='departments.php';</script>";
    } else {
        echo "❌ এরর: " . htmlspecialchars($db->error, ENT_QUOTES, 'UTF-8');
    }
    $stmt->close();
    exit();
}

if ($action === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        die('❌ সঠিক পদ নির্বাচন করুন। <a href="departments.php">ফিরে যান</a>');
    }

    $stmt = $db->prepare("SELECT status FROM positions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        die('❌ পদ পাওয়া যায়নি। <a href="departments.php">ফিরে যান</a>');
    }

    $new_status = ($row['status'] ?? 'active') === 'active' ? 'inactive' : 'active';

    $update = $db->prepare("UPDATE positions SET status = ? WHERE id = ?");
    $update->bind_param("si", $new_status, $id);
    $update->execute();
    $update->close();

    log_activity('Position Status Changed', 'HRM', 'Position ID: ' . $id . ' -> ' . $new_status);

    header('Location: departments.php');
    exit();
}

header('Location: departments.php');
exit();
