<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।
ensure_hrm_schema($db);

// Profile and compensation changes must pass through the unified hub so an
// effective-dated pay rule is always recorded and duplicate identities are blocked.
$_SESSION['user_flash'] = 'কর্মীর তথ্য ও বেতন এখন কর্মী হাব থেকে সংশোধন করুন—পুরোনো ফর্ম বন্ধ করা হয়েছে।';
$_SESSION['user_flash_ok'] = 0;
header('Location: employee_list.php');
exit();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: employee_list.php');
    exit();
}

$session_token = $_SESSION['csrf_token'] ?? '';
$posted_token = $_POST['csrf_token'] ?? '';
if (empty($session_token) || !hash_equals($session_token, $posted_token)) {
    die('❌ নিরাপত্তা যাচাই ব্যর্থ হয়েছে। <a href="employee_list.php">ফিরে যান</a>');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('❌ সঠিক কর্মী নির্বাচন করুন। <a href="employee_list.php">ফিরে যান</a>');
}

$name = clean_input($_POST['name']);
$phone = clean_input($_POST['phone']);
$address = clean_input($_POST['address'] ?? '');
$desig = clean_input($_POST['designation']);
remember_position($db, $desig);
$salary = (float)$_POST['salary'];
$department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$pay_mode = (($_POST['payment_type'] ?? 'percentage') === 'daily') ? 'daily' : 'percentage';
$salary_type = $pay_mode === 'daily' ? 'daily' : 'monthly';
$joining_date = !empty($_POST['joining_date']) ? $_POST['joining_date'] : date('Y-m-d');
$status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
$share_percent = $pay_mode === 'percentage'
    ? ame_clamp_share_percent($_POST['service_share_percent'] ?? 50)
    : 0.0;
$_POST['payment_type'] = $pay_mode;
$_POST['daily_rate'] = $pay_mode === 'daily' ? $salary : 0;
$_POST['worker_type'] = 'artisan';

$code_result = resolve_employee_code($db, (string)($_POST['employee_code'] ?? ''), $id);
if (!$code_result['ok']) {
    die('❌ ' . htmlspecialchars($code_result['error'], ENT_QUOTES, 'UTF-8') . ' <a href="employee_edit.php?id=' . $id . '">ফিরে যান</a>');
}
$employee_code = $code_result['code'];

// নতুন ছবি দিলে আপলোড করা, না দিলে পুরনো ছবি অক্ষত রাখা
$allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$photo_update_sql = '';
$new_photo = null;

if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);

    if (isset($allowed_mimes[$real_mime])) {
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/employees/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $new_photo = 'emp_' . time() . '_' . random_int(1000, 9999) . '.' . $allowed_mimes[$real_mime];
        move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $new_photo);
        $photo_update_sql = ', photo = ?';
    }
}

if ($new_photo !== null) {
    $stmt = $db->prepare(
        "UPDATE employees SET name=?, phone=?, address=?, designation=?, salary=?, department_id=?, salary_type=?, joining_date=?, status=?, photo=?, service_share_percent=?, employee_code=? WHERE id=?"
    );
    $stmt->bind_param(
        "ssssdissssdsi",
        $name, $phone, $address, $desig, $salary, $department_id, $salary_type, $joining_date, $status, $new_photo, $share_percent, $employee_code, $id
    );
} else {
    $stmt = $db->prepare(
        "UPDATE employees SET name=?, phone=?, address=?, designation=?, salary=?, department_id=?, salary_type=?, joining_date=?, status=?, service_share_percent=?, employee_code=? WHERE id=?"
    );
    $stmt->bind_param(
        "ssssdisssdsi",
        $name, $phone, $address, $desig, $salary, $department_id, $salary_type, $joining_date, $status, $share_percent, $employee_code, $id
    );
}

if ($stmt->execute()) {
    log_activity('Employee Updated', 'HRM', $name);
    sync_employee_as_engineer($db, $id);
    if (function_exists('ame_earnings_save_rule')) {
        ame_earnings_save_rule($db, $id, ame_earnings_rule_from_post($_POST));
    }
    // ⭐ বাগ-ফিক্স: ame_earnings_save_rule() নিজে থেকেই "আয়ের নিয়ম" (worker_type/
    // payment_type) দেখে employees টেবিলের salary/salary_type অনুমান করে বসিয়ে
    // দিত — এই ফর্মের উপরের "বেতনের ধরন" (মাসিক/দৈনিক) ও "বেতন" ফিল্ডে আগে যা
    // সেভ হয়েছিল সেটা তখনই ভুল মানে ওভাররাইট হয়ে যেত। ফলে এডিট করার পরও পরের
    // বার দেখলে "বেতনের ধরন" আগের/ভুল মানে ফিরে যেত। এখানে ফর্মে যা আসলে
    // সাবমিট হয়েছে সেটাই শেষবার আবার বসিয়ে ঠিক করে দেওয়া হলো।
    $fix_salary = $db->prepare('UPDATE employees SET salary = ?, salary_type = ?, service_share_percent = ?, payment_type = ? WHERE id = ?');
    if ($fix_salary) {
        $fix_salary->bind_param('dsdsi', $salary, $salary_type, $share_percent, $pay_mode, $id);
        $fix_salary->execute();
        $fix_salary->close();
    }
    echo "<script>alert('তথ্য সফলভাবে আপডেট হয়েছে'); window.location='employee_profile.php?id=$id';</script>";
} else {
    echo "❌ এরর: " . htmlspecialchars($db->error, ENT_QUOTES, 'UTF-8');
}
$stmt->close();
?>
