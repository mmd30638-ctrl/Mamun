<?php
require_once '../../includes/config.php';
require_once '../../includes/easy_update_functions.php';
require_once '../../includes/update_backup_functions.php';
admin_only();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: easy_update.php');
    exit();
}

csrf_verify();

$id = (string)($_POST['id'] ?? '');
$restoreDb = (string)($_POST['restore_db'] ?? '') === '1';

if ($id === '') {
    header('Location: easy_update.php?error=' . urlencode('ব্যাকআপ আইডি পাওয়া যায়নি।'));
    exit();
}

$root = dirname(__DIR__, 2);
$result = ame_update_backup_restore($db instanceof mysqli ? $db : null, $id, $root, $restoreDb);

if (!$result['ok']) {
    header('Location: easy_update.php?error=' . urlencode($result['message']));
    exit();
}

try {
    log_activity('Update Rollback', 'Settings', $result['message'] . ' (backup: ' . $id . ')');
} catch (Throwable $e) {
}

$_SESSION['rollback_message'] = $result['message'];
header('Location: easy_update.php?rolled_back=1');
exit();
