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

$stmt = $db->prepare("SELECT is_default FROM accounts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$check = $stmt->get_result()->fetch_assoc();

if (!$check) {
    echo "<script>alert('অ্যাকাউন্ট খুঁজে পাওয়া যায়নি'); window.location='list.php';</script>";
    exit();
}

if ((int)$check['is_default'] === 1) {
    echo "<script>alert('প্রধান ক্যাশ অ্যাকাউন্ট বন্ধ করা সম্ভব নয়'); window.location='list.php';</script>";
    exit();
}

$upd = $db->prepare("UPDATE accounts SET status = 'inactive' WHERE id = ?");
$upd->bind_param("i", $id);
$upd->execute();
try { log_activity('deactivate', 'accounts', 'account #' . $id); } catch (Throwable $e) {}
echo "<script>alert('অ্যাকাউন্টটি সফলভাবে বন্ধ করা হয়েছে'); window.location='list.php';</script>";
