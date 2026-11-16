<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
ame_earnings_boot($db, 'settings');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: earnings_settings.php');
    exit();
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    header('Location: earnings_settings.php?err=csrf');
    exit();
}
ame_earnings_set_setting($db, 'pay_direct_max', (string)max(0, (float)($_POST['pay_direct_max'] ?? 5000)));
ame_earnings_set_setting($db, 'pay_manager_max', (string)max(0, (float)($_POST['pay_manager_max'] ?? 20000)));
ame_earnings_audit($db, 'settings', 'worker_earnings_settings', 0, 0, null, $_POST);
header('Location: earnings_settings.php?ok=settings');
exit();
