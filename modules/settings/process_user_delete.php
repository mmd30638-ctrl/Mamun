<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
if (is_file('../../includes/super_admin.php')) {
    require_once '../../includes/super_admin.php';
}
admin_only();
ensure_user_schema($db);
if (isset($db) && $db instanceof mysqli && function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}
require_post();
csrf_verify();

$action = strtolower(trim((string) ($_POST['action'] ?? 'purge')));
$from = strtolower(trim((string) ($_POST['from'] ?? '')));

if ($action === 'purge_all') {
    $result = ame_purge_all_removed_users($db);
    if ($result['ok'] > 0 && $result['fail'] === 0) {
        $_SESSION['user_flash'] = $result['ok'] . ' জন সরানো ইউজার চিরতরে ডিলিট হয়েছে। বিক্রয় ও লেজার অক্ষত। / ' . $result['ok'] . ' removed users deleted forever. Sales and ledger kept.';
        $_SESSION['user_flash_ok'] = true;
    } elseif ($result['ok'] > 0) {
        $_SESSION['user_flash'] = $result['ok'] . ' জন ডিলিট হয়েছে, ' . $result['fail'] . ' জন হয়নি। / Some users could not be deleted.';
        $_SESSION['user_flash_ok'] = false;
    } else {
        $_SESSION['user_flash'] = $result['errors'][0] ?? 'সরানো ইউজার ডিলিট হয়নি। / No removed user was deleted.';
        $_SESSION['user_flash_ok'] = false;
    }
} else {
    $id = (int) ($_POST['id'] ?? 0);
    $err = ame_purge_user($db, $id);
    $_SESSION['user_flash'] = $err === null
        ? 'ইউজার চিরতরে ডিলিট হয়েছে। লগইন আর নেই। বিক্রয়, লেজার ও ইনভয়েস অক্ষত। / User deleted forever. Login gone. Sales, ledger and invoices kept.'
        : $err;
    $_SESSION['user_flash_ok'] = $err === null;
}

$back = 'users.php?status=archive';
if ($from === 'view' && $action !== 'purge_all') {
    $back = !empty($_SESSION['user_flash_ok']) ? 'users.php?status=archive' : ('user_view.php?id=' . (int) ($_POST['id'] ?? 0));
}
header('Location: ' . $back);
exit();
