<?php
require_once '../../includes/config.php';
require_post();
csrf_verify();
admin_only();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo "<script>alert('ভুল অ্যাকাউন্ট আইডি'); window.location='list.php';</script>";
    exit();
}

$stmt = $db->prepare("SELECT id FROM accounts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo "<script>alert('অ্যাকাউন্ট খুঁজে পাওয়া যায়নি'); window.location='list.php';</script>";
    exit();
}

$upd = $db->prepare("UPDATE accounts SET status = 'active' WHERE id = ?");
$upd->bind_param("i", $id);
$upd->execute();
try { log_activity('reactivate', 'accounts', 'account #' . $id); } catch (Throwable $e) {}
echo "<script>alert('অ্যাকাউন্টটি সফলভাবে আবার চালু করা হয়েছে'); window.location='list.php';</script>";
