<?php
require_once __DIR__ . '/../includes/config.php';
admin_only();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../employee_list.php");
    exit();
}

csrf_verify(); // আগে CSRF চেক ছিল না — যোগ করা হলো

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: ../employee_list.php");
    exit();
}

try {
    // ছবি ফাইল ডিলিট করা (ঐচ্ছিক)
    $get_pic = $db->prepare("SELECT photo FROM employees WHERE id = ?");
    $get_pic->bind_param("i", $id);
    $get_pic->execute();
    $pic = $get_pic->get_result()->fetch_assoc()['photo'] ?? null;

    if ($pic && $pic !== 'default.png') {
        $path = __DIR__ . '/../uploads/profiles/' . $pic;
        if (is_file($path)) { @unlink($path); }
    }

    $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $_SESSION['success'] = 'কর্মী সফলভাবে ডিলিট হয়েছে।';
} catch (Throwable $e) {
    error_log('delete_employee failed: ' . $e->getMessage());
    $_SESSION['error'] = 'ডিলিট করা যায়নি। পরে আবার চেষ্টা করুন।';
}

header("Location: ../employee_list.php");
exit();
