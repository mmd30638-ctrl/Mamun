<?php
require_once '../../../includes/config.php';
require_once '../../../includes/account_functions.php';

require_post();
csrf_verify();
ensure_accounts_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../family_members.php');
    exit;
}

$name = clean_input($_POST['name'] ?? '');
$role = in_array($_POST['role'] ?? '', ['father', 'mother', 'brother', 'sister', 'other'], true) ? $_POST['role'] : 'other';

if ($name === '') {
    $_SESSION['user_flash'] = 'নাম দিন।';
    $_SESSION['user_flash_ok'] = false;
    header('Location: ../family_members.php');
    exit;
}

$stmt = $db->prepare('INSERT INTO family_members (name, role) VALUES (?, ?)');
if ($stmt) {
    $stmt->bind_param('ss', $name, $role);
    $stmt->execute();
    $stmt->close();
}

$_SESSION['user_flash'] = 'সদস্য যোগ করা হয়েছে।';
$_SESSION['user_flash_ok'] = true;
header('Location: ../family_members.php');
exit;
