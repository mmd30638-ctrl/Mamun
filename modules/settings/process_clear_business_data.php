<?php
require_once '../../includes/config.php';
require_once '../../includes/backup_functions.php';
require_once '../../includes/factory_reset_functions.php';
require_once '../../includes/critical_action_otp.php';
admin_only();
csrf_verify();

const CBD_ACTION = 'business_clear';

function cbd_fail(string $msg): void
{
    header('Location: clear_business_data.php?error=' . urlencode($msg));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cbd_fail('সরাসরি এই পেজ খোলা যাবে না।');
}

$action = trim((string)($_POST['action'] ?? 'clear'));
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($action === 'send_otp') {
    if (trim((string)($_POST['confirm_text'] ?? '')) !== 'মুছুন') {
        cbd_fail('নিশ্চিত করতে ঠিক "মুছুন" টাইপ করুন।');
    }
    if (empty($_POST['confirm1']) || empty($_POST['confirm2'])) {
        cbd_fail('দুটো চেকবক্স টিক দিন।');
    }

    $send = ame_send_critical_action_otp($db, $user_id, CBD_ACTION, 'ব্যবসায়িক ডাটা মুছুন');
    if (!$send['ok']) {
        cbd_fail($send['message']);
    }

    $_SESSION['cbd_otp_pending'] = 1;
    $_SESSION['cbd_otp_mask'] = $send['mask'];
    if ($send['mock'] && !empty($send['otp'])) {
        $_SESSION['cbd_otp_preview'] = $send['otp'];
    } else {
        unset($_SESSION['cbd_otp_preview']);
    }

    header('Location: clear_business_data.php?otp_sent=1');
    exit();
}

if ($action === 'clear') {
    if (empty($_SESSION['cbd_otp_pending'])) {
        cbd_fail('প্রথমে WhatsApp/SMS OTP পাঠান।');
    }
    if (trim((string)($_POST['confirm_text'] ?? '')) !== 'মুছুন') {
        cbd_fail('নিশ্চিত করতে ঠিক "মুছুন" টাইপ করুন।');
    }
    if (empty($_POST['confirm1']) || empty($_POST['confirm2'])) {
        cbd_fail('দুটো চেকবক্স টিক দিন।');
    }

    $otp = (string)($_POST['otp'] ?? '');
    $row = ame_verify_critical_action_otp($db, $user_id, CBD_ACTION, $otp);
    if (!$row) {
        cbd_fail('OTP ভুল অথবা মেয়াদ শেষ। আবার OTP পাঠান।');
    }

    $pre_dir = AME_PRIVATE_DATA_DIR . '/pre_reset_snapshots';
    if (!is_dir($pre_dir)) {
        @mkdir($pre_dir, 0700, true);
    }
    $snapshot = $pre_dir . '/pre_reset_' . date('Y-m-d_H-i-s') . '.sql';
    $fh = @fopen($snapshot, 'w');
    if ($fh) {
        @chmod($snapshot, 0600);
        try {
            ame_stream_sql_dump($db, function ($chunk) use ($fh) {
                fwrite($fh, $chunk);
            });
        } catch (Throwable $e) {
            error_log('Pre-reset snapshot failed: ' . $e->getMessage());
        } finally {
            fclose($fh);
        }
    }

    try {
        ame_mark_critical_otp_used($db, (int)$row['id']);
        $stats = ame_factory_reset_run($db);
        unset($_SESSION['cbd_otp_pending'], $_SESSION['cbd_otp_mask'], $_SESSION['cbd_otp_preview']);
        if (function_exists('log_activity')) {
            log_activity('Business Data Clear (OTP)', 'Settings', json_encode($stats, JSON_UNESCAPED_UNICODE));
        }
        header('Location: clear_business_data.php?done=1&n=' . (int)$stats['truncated']);
        exit();
    } catch (Throwable $e) {
        error_log('Factory reset failed: ' . $e->getMessage());
        cbd_fail('মুছে ফেলা ব্যর্থ: ' . $e->getMessage());
    }
}

cbd_fail('অজানা অনুরোধ।');
