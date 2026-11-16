<?php
require_once '../../../includes/config.php';
require_once '../../../includes/account_functions.php';

require_post();
csrf_verify();
ensure_accounts_schema($db);

$threshold_raw = trim($_POST['threshold'] ?? '');
$pin = trim($_POST['pin'] ?? '');
$pin_confirm = trim($_POST['pin_confirm'] ?? '');

if ($threshold_raw !== '') {
    set_app_setting($db, 'transfer_approval_threshold', (string)(float)$threshold_raw);
} else {
    set_app_setting($db, 'transfer_approval_threshold', '');
}

if ($pin !== '') {
    if (!preg_match('/^[0-9]{4,6}$/', $pin)) {
        die('❌ PIN অবশ্যই ৪-৬ ডিজিটের সংখ্যা হতে হবে। <a href="../approval_settings.php">ফিরে যান</a>');
    }
    if ($pin !== $pin_confirm) {
        die('❌ দুটো PIN মিলছে না। <a href="../approval_settings.php">ফিরে যান</a>');
    }
    $hash = password_hash($pin, PASSWORD_DEFAULT);
    set_app_setting($db, 'transfer_approval_pin_hash', $hash);
}

echo "<script>alert('সেটিংস সংরক্ষিত হয়েছে'); window.location='../list.php';</script>";
?>
