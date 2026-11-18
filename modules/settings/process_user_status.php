<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
admin_only();
ensure_user_schema($db);
require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$want = strtolower(trim((string)($_POST['status'] ?? '')));
$active = in_array($want, ['1', 'active', 'on'], true);
$back = 'users.php';
$filter = strtolower(trim((string)($_POST['back'] ?? '')));
if (in_array($filter, ['active', 'inactive', 'removed'], true)) {
    $back .= '?status=' . $filter;
}
$from = (string)($_POST['from'] ?? '');
if ($from === 'view' && $id > 0) {
    $back = 'user_view.php?id=' . $id;
}

$err = ame_set_user_active($db, $id, $active);
$_SESSION['user_flash'] = $err === null
    ? ($active
        ? 'ইউজার এখন এক্টিভ — লগইন করতে পারবে। / User is active — can sign in.'
        : 'ইউজার এখন ইনঅ্যাকটিভ — লগইন বন্ধ। / User is inactive — login off.')
    : $err;
$_SESSION['user_flash_ok'] = $err === null;

header('Location: ' . $back);
exit();
