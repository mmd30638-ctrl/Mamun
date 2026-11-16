<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}
csrf_verify();

$name = trim($_POST['name'] ?? '');
$partner_type = trim($_POST['partner_type'] ?? 'equity');
$share_percentage = (float)($_POST['share_percentage'] ?? 0);
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$join_date = trim($_POST['join_date'] ?? '');
$status = trim($_POST['status'] ?? 'active');
$notes = trim($_POST['notes'] ?? '');

// ================= ভ্যালিডেশন =================
$allowed_types = ['equity', 'profit_share', 'silent'];
$allowed_status = ['active', 'inactive', 'exited'];

if ($name === '') {
    $_SESSION['error'] = 'পার্টনারের নাম আবশ্যক।';
    header('Location: ../add.php');
    exit;
}
if (!in_array($partner_type, $allowed_types, true)) {
    $partner_type = 'equity';
}
if (!in_array($status, $allowed_status, true)) {
    $status = 'active';
}
if ($share_percentage < 0 || $share_percentage > 100) {
    $_SESSION['error'] = 'শেয়ার শতাংশ ০ থেকে ১০০-এর মধ্যে হতে হবে।';
    header('Location: ../add.php');
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $join_date)) {
    $join_date = date('Y-m-d');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'ইমেইল ঠিকানা সঠিক নয়।';
    header('Location: ../add.php');
    exit;
}

try {
    $stmt = $db->prepare(
        "INSERT INTO partners (name, partner_type, share_percentage, phone, email, address, join_date, status, notes, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param(
        'ssdssssss',
        $name, $partner_type, $share_percentage, $phone, $email, $address, $join_date, $status, $notes
    );
    $stmt->execute();

    log_activity('Partner Added', 'Partnership', $name . ' (' . $share_percentage . '%)');

    $_SESSION['success'] = 'নতুন পার্টনার সফলভাবে যোগ করা হয়েছে।';
    header('Location: ../index.php');
    exit;
} catch (Throwable $e) {
    error_log('Partner add failed: ' . $e->getMessage());
    $_SESSION['error'] = 'পার্টনার সংরক্ষণে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../add.php');
    exit;
}
