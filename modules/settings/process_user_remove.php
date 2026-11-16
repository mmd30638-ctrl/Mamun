<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
admin_only();
ensure_user_schema($db);
require_post();
csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? 'remove')));

if ($action === 'restore') {
    $err = ame_restore_user($db, $id);
    $_SESSION['user_flash'] = $err === null
        ? 'ব্যবহারকারী তালিকায় ফিরেছে — আবার লগইন করতে পারবে। / User is back on the list and can sign in again.'
        : $err;
} else {
    $err = ame_remove_user($db, $id);
    $_SESSION['user_flash'] = $err === null
        ? 'তালিকা থেকে সরে গেছে। লগইন বন্ধ, বিক্রয় ও লেজার ইতিহাস অক্ষত। / Gone from this page. Login off, sales and ledger history kept.'
        : $err;
}
$_SESSION['user_flash_ok'] = $err === null;

header('Location: users.php');
exit();
