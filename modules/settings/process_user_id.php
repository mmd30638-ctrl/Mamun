<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
admin_only();
ensure_user_schema($db);
require_post();
csrf_verify();

$old_id = (int) ($_POST['id'] ?? 0);
$new_id = (int) ($_POST['new_id'] ?? 0);
$back = 'users.php';
$filter = strtolower(trim((string) ($_POST['back'] ?? '')));
if (in_array($filter, ['active', 'inactive', 'removed'], true)) {
    $back .= '?status=' . $filter;
}
$from = (string) ($_POST['from'] ?? '');
if ($from === 'view' && $old_id > 0) {
    $back = 'user_view.php?id=' . $old_id;
}

$err = ame_set_user_id($db, $old_id, $new_id);
if ($err === null && $from === 'view') {
    $back = 'user_view.php?id=' . $new_id;
}
$_SESSION['user_flash'] = $err === null
    ? 'ইউজার আইডি বসানো হয়েছে — বিক্রয় ও লেজার নতুন আইডিতে গেছে। / User ID set — sales and ledger now use the new number.'
    : $err;
$_SESSION['user_flash_ok'] = $err === null;

header('Location: ' . $back);
exit();
