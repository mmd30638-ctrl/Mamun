<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
admin_only();
ensure_hrm_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: employee_list.php');
    exit();
}

$session_token = $_SESSION['csrf_token'] ?? '';
$posted_token = $_POST['csrf_token'] ?? '';
if (empty($session_token) || !hash_equals($session_token, $posted_token)) {
    die('❌ নিরাপত্তা যাচাই ব্যর্থ হয়েছে। <a href="employee_list.php">ফিরে যান</a>');
}

$action = (string)($_POST['action'] ?? '');
$employee_id = (int)($_POST['employee_id'] ?? 0);

if ($employee_id <= 0) {
    die('❌ সঠিক কর্মী নির্বাচন করুন। <a href="employee_list.php">ফিরে যান</a>');
}

$stmt = $db->prepare("SELECT name FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$emp) {
    die('❌ কর্মী পাওয়া যায়নি। <a href="employee_list.php">ফিরে যান</a>');
}

if ($action === 'create') {
    $username = trim(clean_input($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '') {
        die('❌ সঠিক ইউজারনেম ও পাসওয়ার্ড দিন। <a href="employee_login.php?id=' . $employee_id . '">ফিরে যান</a>');
    }
    $pw_err = ame_validate_password($password);
    if ($pw_err !== null) {
        die('❌ ' . htmlspecialchars($pw_err, ENT_QUOTES, 'UTF-8') . ' <a href="employee_login.php?id=' . $employee_id . '">ফিরে যান</a>');
    }

    // ইউজারনেম আগে থেকে আছে কিনা যাচাই
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        die('❌ এই ইউজারনেম আগে থেকেই ব্যবহৃত হচ্ছে, অন্য একটা দিন। <a href="employee_login.php?id=' . $employee_id . '">ফিরে যান</a>');
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $role = 'Staff';

    $insert = $db->prepare("INSERT INTO users (username, password, role, employee_id, status) VALUES (?, ?, ?, ?, 1)");
    $insert->bind_param("sssi", $username, $hashed, $role, $employee_id);

    if ($insert->execute()) {
        sync_employee_as_engineer($db, $employee_id);
        log_activity('Employee Login Created', 'HRM', $emp['name'] . ' -> ' . $username);
        echo "<script>alert('লগইন একাউন্ট তৈরি হয়েছে'); window.location='employee_profile.php?id=$employee_id';</script>";
    } else {
        echo "❌ এরর: " . htmlspecialchars($db->error, ENT_QUOTES, 'UTF-8');
    }
    $insert->close();
    exit();
}

if ($action === 'reset_password') {
    $password = (string)($_POST['password'] ?? '');

    $pw_err = ame_validate_password($password);
    if ($pw_err !== null) {
        die('❌ ' . htmlspecialchars($pw_err, ENT_QUOTES, 'UTF-8') . ' <a href="employee_login.php?id=' . $employee_id . '">ফিরে যান</a>');
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $update = $db->prepare(
        "UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE employee_id = ?"
    );
    $update->bind_param("si", $hashed, $employee_id);
    $update->execute();
    $update->close();

    log_activity('Employee Password Reset', 'HRM', $emp['name']);
    echo "<script>alert('পাসওয়ার্ড পরিবর্তন হয়েছে'); window.location='employee_profile.php?id=$employee_id';</script>";
    exit();
}

if ($action === 'toggle_status') {
    $stmt2 = $db->prepare("SELECT id, status FROM users WHERE employee_id = ?");
    $stmt2->bind_param("i", $employee_id);
    $stmt2->execute();
    $user = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    if ($user) {
        $new_status = $user['status'] ? 0 : 1;
        $update = $db->prepare(
            "UPDATE users SET status = ?, auth_version = auth_version + 1 WHERE id = ?"
        );
        $update->bind_param("ii", $new_status, $user['id']);
        $update->execute();
        $update->close();

        log_activity('Employee Login Status Changed', 'HRM', $emp['name'] . ' -> ' . ($new_status ? 'active' : 'disabled'));
    }

    echo "<script>alert('একাউন্টের স্ট্যাটাস পরিবর্তন হয়েছে'); window.location='employee_profile.php?id=$employee_id';</script>";
    exit();
}

header('Location: employee_profile.php?id=' . $employee_id);
exit();
