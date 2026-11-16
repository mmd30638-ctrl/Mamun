<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/attendance_functions.php';
require_once '../../includes/staff_duty.php';
if (is_file('../../includes/user_functions.php')) {
    require_once '../../includes/user_functions.php';
}

ensure_hrm_schema($db);
if (function_exists('ame_staff_duty_schema')) {
    ame_staff_duty_schema($db);
}
require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$err = 'ডিলিট সিস্টেম পাওয়া যায়নি।';
try {
    if (function_exists('ame_staff_hard_delete')) {
        $err = ame_staff_hard_delete($db, $id);
    }
} catch (Throwable $e) {
    error_log('process_employee_delete: ' . $e->getMessage());
    $err = 'ডিলিট সম্পন্ন হয়নি। আবার চেষ্টা করুন।';
}

$_SESSION['user_flash'] = $err === null || $err === ''
    ? 'কর্মী, ইউজার ও ইঞ্জিনিয়ার প্রজেক্ট থেকে চিরতরে মুছে গেছে। বিক্রয় ও বেতনের খাতা আছে।'
    : (string)$err;
$_SESSION['user_flash_ok'] = ($err === null || $err === '');

$back = 'employee_list.php';
if (!empty($_POST['archive'])) {
    $back .= '?archive=1';
}
header('Location: ' . $back);
exit();
