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

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$partner_type = trim($_POST['partner_type'] ?? 'equity');
$share_percentage = (float)($_POST['share_percentage'] ?? 0);
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$join_date = trim($_POST['join_date'] ?? '');
$exit_date = trim($_POST['exit_date'] ?? '');
$status = trim($_POST['status'] ?? 'active');
$notes = trim($_POST['notes'] ?? '');

// ================= ভ্যালিডেশন =================
$allowed_types = ['equity', 'profit_share', 'silent'];
$allowed_status = ['active', 'inactive', 'exited'];

if ($id <= 0 || $name === '') {
    $_SESSION['error'] = 'প্রয়োজনীয় তথ্য দিন।';
    header('Location: ../index.php');
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
    header('Location: ../edit.php?id=' . $id);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $join_date)) {
    $join_date = null;
}
$exit_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $exit_date) ? $exit_date : null;

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'ইমেইল ঠিকানা সঠিক নয়।';
    header('Location: ../edit.php?id=' . $id);
    exit;
}

try {
    $stmt = $db->prepare(
        "UPDATE partners SET
            name = ?, partner_type = ?, share_percentage = ?, phone = ?, email = ?,
            address = ?, join_date = ?, exit_date = ?, status = ?, notes = ?, updated_at = NOW()
         WHERE id = ?"
    );
    $stmt->bind_param(
        'ssdsssssssi',
        $name, $partner_type, $share_percentage, $phone, $email,
        $address, $join_date, $exit_date, $status, $notes, $id
    );
    $stmt->execute();

    log_activity('Partner Updated', 'Partnership', $name . ' (ID: ' . $id . ')');

    $_SESSION['success'] = 'পার্টনারের তথ্য সফলভাবে আপডেট হয়েছে।';
    header('Location: ../index.php');
    exit;
} catch (Throwable $e) {
    error_log('Partner edit failed: ' . $e->getMessage());
    $_SESSION['error'] = 'আপডেটে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: ../edit.php?id=' . $id);
    exit;
}
