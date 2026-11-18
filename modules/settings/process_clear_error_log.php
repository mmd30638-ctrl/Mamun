<?php
require_once '../../includes/config.php';
require_once '../../includes/error_monitor.php';
admin_only();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: error_diagnostics.php');
    exit();
}

csrf_verify();

$ok = ame_clear_diagnostic_logs();

try {
    log_activity('Clear Error Log', 'Settings', $ok ? 'এরর লগ খালি করা হয়েছে।' : 'এরর লগ খালি করতে সমস্যা হয়েছে।');
} catch (Throwable $e) {
}

$_SESSION['error_log_cleared'] = $ok
    ? 'এরর লগ খালি করা হয়েছে।'
    : 'এরর লগ খালি করা যায়নি — ফাইল পারমিশন দেখুন।';

header('Location: error_diagnostics.php');
exit();
