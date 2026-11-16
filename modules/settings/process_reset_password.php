<?php
/**
 * Admin / Super Admin sets a user's login password.
 * Shop money is never written.
 */
define('AME_READ_ONLY_REQUEST', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/user_functions.php';
require_once __DIR__ . '/../../includes/user_profile.php';
if (is_file(__DIR__ . '/../../includes/super_admin.php')) {
    require_once __DIR__ . '/../../includes/super_admin.php';
}

require_post();
csrf_verify();
admin_only();
if (function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}

$id = (int) ($_POST['id'] ?? 0);
$backFail = 'reset_password.php?id=' . $id;
$fail = static function (string $msg) use ($backFail): void {
    $_SESSION['user_flash'] = $msg;
    $_SESSION['user_flash_ok'] = false;
    header('Location: ' . $backFail);
    exit();
};

if ($id <= 0) {
    $fail('ভুল ইউজার। / Invalid user.');
}

$result = ame_admin_set_user_password(
    $db,
    $id,
    (string) ($_POST['new_password'] ?? ''),
    (string) ($_POST['new_password_confirm'] ?? ''),
    (string) ($_POST['actor_password'] ?? ''),
    !empty($_POST['force_change'])
);

if (empty($result['ok'])) {
    $fail((string) ($result['error'] ?? 'পাসওয়ার্ড বদলায়নি।'));
}

$_SESSION['user_flash'] = (string) ($result['message'] ?? 'পাসওয়ার্ড আপডেট হয়েছে।');
$_SESSION['user_flash_ok'] = true;
header('Location: users.php');
exit;
