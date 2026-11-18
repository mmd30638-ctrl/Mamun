<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/pf_functions.php';
admin_only();
ensure_pf_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../accounts.php');
    exit;
}
csrf_verify();

// ⭐ মূল accounts টেবিলে owner_member_id কলাম (কার অ্যাকাউন্ট তা চিহ্নিত করতে) — না থাকলে যোগ করা
$col_check = $db->query("SHOW COLUMNS FROM accounts LIKE 'owner_member_id'");
if ($col_check && $col_check->num_rows === 0) {
    @$db->query("ALTER TABLE accounts ADD COLUMN owner_member_id INT NULL");
}

$name = trim($_POST['name'] ?? '');
$type = in_array($_POST['type'] ?? '', ['cash', 'bank', 'mobile_banking', 'card', 'savings', 'other'], true) ? $_POST['type'] : 'cash';
$opening_balance = round((float)($_POST['opening_balance'] ?? 0), 2);
$owner_member_id = !empty($_POST['owner_member_id']) ? (int)$_POST['owner_member_id'] : null;

if ($name === '') {
    $_SESSION['error'] = 'অ্যাকাউন্টের নাম দিন।';
    header('Location: ../accounts.php');
    exit;
}

$stmt = $db->prepare(
    "INSERT INTO accounts (account_name, account_type, balance, owner_member_id, status) VALUES (?, ?, ?, ?, 'active')"
);
$stmt->bind_param('ssdi', $name, $type, $opening_balance, $owner_member_id);
$stmt->execute();

log_activity('Account Added', 'Finance', $name);

$_SESSION['success'] = 'অ্যাকাউন্ট সফলভাবে যোগ করা হয়েছে।';
header('Location: ../accounts.php');
exit;
