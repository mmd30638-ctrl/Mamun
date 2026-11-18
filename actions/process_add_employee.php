<?php
/**
 * কর্মী তৈরি — নিরাপত্তা ফিক্স করা ভার্সন
 * পরিবর্তনসমূহ:
 *  - config.php ব্যবহার করে লগইন-গার্ড চালু করা হয়েছে (আগে কোনো লগইন লাগতো না)
 *  - CSRF টোকেন যাচাই যোগ করা হয়েছে
 *  - PDO ($conn) বাদ দিয়ে বাকি অ্যাপের মতো mysqli ($db) ব্যবহার করা হয়েছে (আগে $conn কোথাও ডিফাইন করা ছিল না — ফলে এই ফাইলটি আসলে ভাঙা ছিল)
 *  - ফাইল আপলোডে প্রকৃত ইমেজ কন্টেন্ট যাচাই (শুধু এক্সটেনশন না) ও র‍্যান্ডম ফাইলনেম
 *  - ইনপুট এখন শুধু trim হয়, HTML-escape করে DB-তে সংরক্ষণ করা হয় না (আউটপুটের সময় escape হবে)
 *  - DB এরর ইউজারকে দেখানো হয় না, শুধু লগ ফাইলে যায়
 */
require_once __DIR__ . '/../includes/config.php';
admin_only();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../add_employee.php");
    exit();
}

csrf_verify();

$emp_id      = trim($_POST['emp_id'] ?? '');
$full_name   = trim($_POST['full_name'] ?? '');
$department  = trim($_POST['department'] ?? '');
$designation = trim($_POST['designation'] ?? '');
$joining_date = trim($_POST['joining_date'] ?? '');
$joining_date = $joining_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $joining_date)
    ? $joining_date
    : date('Y-m-d');

if ($emp_id === '' || $full_name === '') {
    $_SESSION['error'] = 'কর্মী আইডি ও নাম আবশ্যক।';
    header("Location: ../add_employee.php");
    exit();
}

// ================= ফাইল আপলোড সিকিউরিটি =================
$photo_name = "default.png";
$upload_dir = __DIR__ . '/../uploads/profiles/';

if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $tmp_path   = $_FILES['profile_pic']['tmp_name'];
    $orig_name  = $_FILES['profile_pic']['name'];
    $size       = $_FILES['profile_pic']['size'];

    $allowed_ext  = ['jpg', 'jpeg', 'png'];
    $allowed_mime = ['image/jpeg', 'image/png'];
    $max_size     = 2 * 1024 * 1024; // 2MB

    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

    // ১. এক্সটেনশন যাচাই
    if (!in_array($ext, $allowed_ext, true)) {
        $_SESSION['error'] = 'শুধুমাত্র JPG/PNG ছবি আপলোড করা যাবে।';
        header("Location: ../add_employee.php");
        exit();
    }

    // ২. সাইজ যাচাই
    if ($size > $max_size) {
        $_SESSION['error'] = 'ছবির সাইজ সর্বোচ্চ ২ মেগাবাইট হতে পারবে।';
        header("Location: ../add_employee.php");
        exit();
    }

    // ৩. প্রকৃত ফাইল কনটেন্ট যাচাই (শুধু এক্সটেনশনের নাম না — আসল ইমেজ কিনা)
    $img_info = @getimagesize($tmp_path);
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = $finfo ? finfo_file($finfo, $tmp_path) : '';
    if ($finfo) { finfo_close($finfo); }

    if ($img_info === false || !in_array($mime, $allowed_mime, true)) {
        $_SESSION['error'] = 'ফাইলটি বৈধ ছবি নয়।';
        header("Location: ../add_employee.php");
        exit();
    }

    // ৪. অনুমানযোগ্য নয় এমন র‍্যান্ডম ফাইলনেম (ওভাররাইট/এনুমারেশন ঠেকাতে)
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    $photo_name = "EMP_" . bin2hex(random_bytes(8)) . "." . $ext;
    if (!move_uploaded_file($tmp_path, $upload_dir . $photo_name)) {
        error_log('Employee photo upload failed to move: ' . $orig_name);
        $photo_name = "default.png";
    }
}

// ================= ডাটাবেস সংরক্ষণ (mysqli, prepared statement) =================
try {
    $sql = "INSERT INTO employees (emp_id, name, department, designation, joining_date, photo)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $db->error);
    }
    $stmt->bind_param('ssssss', $emp_id, $full_name, $department, $designation, $joining_date, $photo_name);
    $stmt->execute();

    $_SESSION['success'] = "কর্মী সফলভাবে যোগ করা হয়েছে।";
    header("Location: ../employee_list.php");
    exit();
} catch (Throwable $e) {
    error_log('process_add_employee failed: ' . $e->getMessage());
    $_SESSION['error'] = "কর্মী সংরক্ষণে সমস্যা হয়েছে। এমপ্লয়ি আইডি হয়তো আগে থেকেই আছে।";
    header("Location: ../add_employee.php");
    exit();
}
