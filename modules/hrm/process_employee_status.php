<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।

require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    die('❌ সঠিক কর্মী নির্বাচন করুন। <a href="employee_list.php">ফিরে যান</a>');
}

$stmt = $db->prepare("SELECT status, name FROM employees WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) {
    die('❌ কর্মী পাওয়া যায়নি। <a href="employee_list.php">ফিরে যান</a>');
}

$new_status = ($emp['status'] ?? 'active') === 'active' ? 'inactive' : 'active';

$update = $db->prepare("UPDATE employees SET status = ? WHERE id = ?");
$update->bind_param("si", $new_status, $id);
$update->execute();
$update->close();

log_activity('Employee Status Changed', 'HRM', $emp['name'] . ' -> ' . $new_status);
sync_employee_as_engineer($db, $id);

header('Location: employee_list.php');
exit();
