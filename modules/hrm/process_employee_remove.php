<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।
ensure_hrm_schema($db);
require_post();
csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? 'remove')));

if ($action === 'restore') {
    $err = ame_restore_employee($db, $id);
    $_SESSION['user_flash'] = $err === null
        ? 'কর্মী তালিকায় ফিরেছে। / Employee is back on the list.'
        : $err;
} else {
    $err = ame_remove_employee($db, $id);
    $_SESSION['user_flash'] = $err === null
        ? 'তালিকা থেকে সরে গেছে। বেতন, অগ্রিম ও হিসাবের ইতিহাস অক্ষত আছে। / Gone from the list. Salary, advance and history are kept.'
        : $err;
}
$_SESSION['user_flash_ok'] = $err === null;

header('Location: employee_list.php' . ($action === 'restore' ? '?archive=1' : ''));
exit();
