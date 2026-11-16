<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।
ensure_hrm_schema($db);

// Employee creation is canonical in the unified hub. Keeping this legacy
// processor writable would bypass the one-person and effective-pay checks.
$_SESSION['user_flash'] = 'নতুন কর্মী এখন কর্মী হাব থেকে যোগ করুন—পুরোনো ফর্ম বন্ধ করা হয়েছে।';
$_SESSION['user_flash_ok'] = 0;
header('Location: employee_list.php');
exit();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $session_token = $_SESSION['csrf_token'] ?? '';
    $posted_token = $_POST['csrf_token'] ?? '';
    if (empty($session_token) || !hash_equals($session_token, $posted_token)) {
        die('❌ নিরাপত্তা যাচাই ব্যর্থ হয়েছে। <a href="employee_add.php">ফিরে যান</a>');
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

    $code_result = resolve_employee_code($db, (string)($_POST['employee_code'] ?? ''));
    if (!$code_result['ok']) {
        die('❌ ' . htmlspecialchars($code_result['error'], ENT_QUOTES, 'UTF-8') . ' <a href="employee_add.php">ফিরে যান</a>');
    }
    $employee_code = $code_result['code'];

    // ছবি আপলোড (নিরাপদ যাচাই - shop banner upload-এর মতোই)
    $photo_name = null;
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
        finfo_close($finfo);

        if (isset($allowed_mimes[$real_mime])) {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/employees/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $photo_name = 'emp_' . time() . '_' . random_int(1000, 9999) . '.' . $allowed_mimes[$real_mime];
            move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $photo_name);
        }
    }

    $stmt = $db->prepare(
        "INSERT INTO employees
            (name, phone, address, designation, salary, department_id, salary_type, employee_code, joining_date, status, photo, service_share_percent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "ssssdisssssd",
        $name, $phone, $address, $desig, $salary,
        $department_id, $salary_type, $employee_code, $joining_date, $status, $photo_name, $share_percent
    );

    $ok = $stmt->execute();
    if (!$ok && (int)$db->errno === 1062 && str_contains((string)$db->error, 'employee_code')) {
        // দুইজন প্রায় একই মুহূর্তে যোগ করলে কোড সংঘর্ষ হতে পারে —
        // একবার নতুন কোড নিয়ে চুপচাপ আবার চেষ্টা, বাজে SQL এরর না দেখিয়ে
        $employee_code = generate_employee_code($db);
        $stmt->close();
        $stmt = $db->prepare(
            "INSERT INTO employees
                (name, phone, address, designation, salary, department_id, salary_type, employee_code, joining_date, status, photo, service_share_percent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "ssssdisssssd",
            $name, $phone, $address, $desig, $salary,
            $department_id, $salary_type, $employee_code, $joining_date, $status, $photo_name, $share_percent
        );
        $ok = $stmt->execute();
    }

    if ($ok) {
        $new_employee_id = $db->insert_id;
        sync_employee_as_engineer($db, $new_employee_id);
        if (function_exists('ame_earnings_save_rule')) {
            ame_earnings_save_rule($db, (int)$new_employee_id, ame_earnings_rule_from_post($_POST));
        }
        // ⭐ বাগ-ফিক্স: ame_earnings_save_rule() "আয়ের নিয়ম" দেখে salary/salary_type
        // নিজে থেকে অনুমান করে বসিয়ে দিতে পারে, যা এই ফর্মে সরাসরি দেওয়া
        // "বেতনের ধরন"/"বেতন"-কে ওভাররাইট করে ফেলতে পারে — এখানে যা সাবমিট
        // হয়েছে সেটাই শেষবার আবার বসিয়ে নিশ্চিত করা হলো (দেখুন
        // process_employee_edit.php-এর একই ফিক্স)।
        $fix_salary = $db->prepare('UPDATE employees SET salary = ?, salary_type = ?, service_share_percent = ?, payment_type = ? WHERE id = ?');
        if ($fix_salary) {
            $fix_salary->bind_param('dsdsi', $salary, $salary_type, $share_percent, $pay_mode, $new_employee_id);
            $fix_salary->execute();
            $fix_salary->close();
        }
        log_activity('Employee Added', 'HRM', $name . ' (' . $employee_code . ')');
        echo "<script>alert('কর্মী সফলভাবে যোগ হয়েছে'); window.location='employee_list.php';</script>";
    } else {
        echo "❌ এরর: " . htmlspecialchars($db->error, ENT_QUOTES, 'UTF-8');
    }
    $stmt->close();
}
?>
