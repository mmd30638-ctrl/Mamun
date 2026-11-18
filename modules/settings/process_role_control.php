<?php
/**
 * Apply one IAM role change. Super Admin only. Money is not written.
 */
define('AME_READ_ONLY_REQUEST', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/user_role_control.php';

require_post();
admin_only();
csrf_verify();
if (function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}
if (!function_exists('ame_is_super_admin') || !ame_is_super_admin()) {
    $_SESSION['rc_err'] = 'শুধু সুপার অ্যাডমিন সবার রোল বদলাতে পারেন।';
    header('Location: /modules/settings/role_control.php');
    exit;
}

$result = ame_role_control_apply($db, $_POST);
if (!empty($result['ok'])) {
    $_SESSION['rc_ok'] = (string) ($result['message'] ?? 'রোল আপডেট হয়েছে।');
    unset($_SESSION['rc_err']);
} else {
    $_SESSION['rc_err'] = (string) ($result['error'] ?? 'রোল বদলায়নি।');
    unset($_SESSION['rc_ok']);
}
header('Location: /modules/settings/role_control.php');
exit;
