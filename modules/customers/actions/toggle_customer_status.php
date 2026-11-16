<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../customer_schema.php';
ensure_customer_tables($db);
if (function_exists('ame_customer_status_boot')) {
    ame_customer_status_boot($db);
}

// customers.status — 1 = Enabled, 0 = Disabled। কখনো ডিলিট হয় না।

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$back = '../index.php';

if ($id <= 0) {
    header('Location: ' . $back);
    exit;
}

$note = 'Enable/Disable সেভ হয়নি। আবার চেষ্টা করুন।';
try {
    $stmt = $db->prepare('SELECT status FROM customers WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('status select failed');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        header('Location: ' . $back);
        exit;
    }

    $current_on = ame_customer_status_enabled($row['status'] ?? 1);
    $new_status = ame_customer_status_store($db, !$current_on);
    $upd = $db->prepare('UPDATE customers SET status = ? WHERE id = ?');
    if (!$upd) {
        throw new RuntimeException('status update failed');
    }
    $upd->bind_param('si', $new_status, $id);
    $ok = $upd->execute();
    $upd->close();
    if (!$ok) {
        throw new RuntimeException('status execute failed');
    }
    $note = $current_on
        ? 'এই কাস্টমার এখন বন্ধ (Disable)।'
        : 'এই কাস্টমার এখন চালু (Enable)।';
} catch (Throwable $e) {
    error_log('toggle_customer_status failed: ' . $e->getMessage());
    $note = 'Enable/Disable সেভ হয়নি। আবার চেষ্টা করুন।';
}

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['ame_cus_status_note'] = $note;
}
header('Location: ' . $back);
exit;
