<?php
require_once '../../includes/config.php';
admin_only();
require_post();
csrf_verify();

$module = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($_POST['module_key'] ?? '')));
$enabled = (string)($_POST['enabled'] ?? '1') === '1';
$reason = trim((string)($_POST['reason'] ?? ''));

if (!isset(ame_module_control_catalog()[$module])) {
    http_response_code(400);
    die('অজানা মডিউল।');
}
if (!$enabled && $reason === '') {
    $reason = 'রক্ষণাবেক্ষণ চলছে';
}

if (!ame_module_control_set($db, $module, $enabled, $reason, (int)($_SESSION['user_id'] ?? 0))) {
    header('Location: module_controls.php?error=save');
    exit;
}

log_activity(
    $enabled ? 'Module enabled' : 'Module disabled',
    'Settings',
    $module . ($reason !== '' ? (': ' . $reason) : '')
);
header('Location: module_controls.php?saved=1');
exit;
