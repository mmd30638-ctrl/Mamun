<?php
/**
 * কর্মীর তথ্য আপডেট প্রসেসর — আগে এই ফাইলটি সিস্টেমেই ছিল না
 * (edit_employee.php ফর্ম এটাতে সাবমিট করত, কিন্তু ফাইল না থাকায় "404" হতো)
 */
require_once __DIR__ . '/../includes/config.php';
admin_only();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../employee_list.php");
    exit();
}

csrf_verify();

$id           = (int)($_POST['id'] ?? 0);
$emp_id       = trim($_POST['emp_id'] ?? '');
$name         = trim($_POST['name'] ?? '');
$department   = trim($_POST['department'] ?? '');
$designation  = trim($_POST['designation'] ?? '');
$joining_date = trim($_POST['joining_date'] ?? '');
$joining_date = ($joining_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $joining_date))
    ? $joining_date
    : null;

if ($id <= 0 || $emp_id === '' || $name === '') {
    $_SESSION['error'] = 'প্রয়োজনীয় তথ্য দিন।';
    header("Location: ../edit_employee.php?id=" . $id);
    exit();
}

// ================= ছবি আপলোড (ঐচ্ছিক, দিলে যাচাই হবে) =================
$new_photo = null;
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $tmp_path  = $_FILES['profile_pic']['tmp_name'];
    $orig_name = $_FILES['profile_pic']['name'];
    $size      = $_FILES['profile_pic']['size'];

    $allowed_ext  = ['jpg', 'jpeg', 'png'];
    $allowed_mime = ['image/jpeg', 'image/png'];
    $max_size     = 2 * 1024 * 1024;

    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_ext, true) || $size > $max_size) {
        $_SESSION['error'] = 'ছবি বৈধ নয় (শুধু JPG/PNG, সর্বোচ্চ ২MB)।';
        header("Location: ../edit_employee.php?id=" . $id);
        exit();
    }

    $img_info = @getimagesize($tmp_path);
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = $finfo ? finfo_file($finfo, $tmp_path) : '';
    if ($finfo) { finfo_close($finfo); }

    if ($img_info === false || !in_array($mime, $allowed_mime, true)) {
        $_SESSION['error'] = 'ফাইলটি বৈধ ছবি নয়।';
        header("Location: ../edit_employee.php?id=" . $id);
        exit();
    }

    $upload_dir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }

    $new_photo = "EMP_" . bin2hex(random_bytes(8)) . "." . $ext;
    if (!move_uploaded_file($tmp_path, $upload_dir . $new_photo)) {
        error_log('process_edit_employee: photo move failed for id=' . $id);
        $new_photo = null;
    }
}

try {
    if ($new_photo !== null) {
        $stmt = $db->prepare(
            "UPDATE employees SET emp_id=?, name=?, department=?, designation=?, joining_date=?, photo=? WHERE id=?"
        );
        $stmt->bind_param(
            "ssssssi",
            $emp_id, $name, $department, $designation, $joining_date, $new_photo, $id
        );

        // পুরনো ছবি সরিয়ে ফেলা (default.png বাদে)
        $old = $db->prepare("SELECT photo FROM employees WHERE id = ?");
        $old->bind_param("i", $id);
        $old->execute();
        $old_photo = $old->get_result()->fetch_assoc()['photo'] ?? null;
        if ($old_photo && $old_photo !== 'default.png') {
            $old_path = __DIR__ . '/../uploads/profiles/' . $old_photo;
            if (is_file($old_path)) { @unlink($old_path); }
        }
    } else {
        $stmt = $db->prepare(
            "UPDATE employees SET emp_id=?, name=?, department=?, designation=?, joining_date=? WHERE id=?"
        );
        $stmt->bind_param(
            "sssssi",
            $emp_id, $name, $department, $designation, $joining_date, $id
        );
    }

    $stmt->execute();

    $_SESSION['success'] = "কর্মীর তথ্য সফলভাবে আপডেট হয়েছে।";
    header("Location: ../employee_list.php");
    exit();
} catch (Throwable $e) {
    error_log('process_edit_employee failed: ' . $e->getMessage());
    $_SESSION['error'] = "আপডেটে সমস্যা হয়েছে। পরে আবার চেষ্টা করুন।";
    header("Location: ../edit_employee.php?id=" . $id);
    exit();
}
