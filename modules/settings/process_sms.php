<?php
require_once '../../includes/config.php';
require_once '../../includes/sms_functions.php';

require_post();
csrf_verify();
admin_only();

$action = (string)($_POST['sms_action'] ?? 'save');


if ($action === 'save_simple') {
    $phone = ame_sms_normalize_bd_mobile((string)($_POST['gateway_phone'] ?? ''));
    if ($phone === '' || strlen($phone) < 11) {
        $_SESSION['sms_flash'] = 'সঠিক সিম মোবাইল নম্বর দিন (01XXXXXXXXX)।';
        header('Location: sms_config.php');
        exit;
    }
    $status = (($_POST['status'] ?? '') === 'active') ? 'active' : 'inactive';
    $current = ame_sms_settings($db);
    $ok = ame_sms_save(
        $db,
        (string)($current['api_url'] ?? ''),
        '',
        (string)($current['sender_id'] ?? ''),
        $status,
        [
            'channel' => 'sim',
            'gateway_phone' => $phone,
            'smsgate_url' => (string)($current['smsgate_url'] ?? ''),
            'smsgate_user' => (string)($current['smsgate_user'] ?? ''),
        ]
    );
    if ($ok) {
        log_activity('SMS SIM phone saved', 'Settings', 'গেটওয়ে নম্বর সেভ');
        $_SESSION['sms_flash'] = 'নম্বর সেভ হয়েছে। এখন সেই ফোনে সিম লিংক খুলুন।';
        header('Location: sms_config.php');
        exit;
    }
    echo 'সেভ হয়নি। আবার চেষ্টা করুন।';
    exit;
}

if ($action === 'rotate') {
    ame_sms_device_token(true);
    log_activity('SMS SIM token rotated', 'Settings', 'সিম ফোন লিংক নতুন করা হয়েছে');
    $_SESSION['sms_flash'] = 'নতুন সিম লিংক তৈরি হয়েছে। পুরনো লিংক বন্ধ।';
    header('Location: sms_config.php');
    exit;
}

if ($action === 'save_sim') {
    $id = ame_sms_sim_save(
        $db,
        (int)($_POST['sim_id'] ?? 0),
        (string)($_POST['label'] ?? ''),
        (string)($_POST['phone'] ?? ''),
        (int)($_POST['slot'] ?? 1),
        (string)($_POST['status'] ?? 'active')
    );
    $_SESSION['sms_flash'] = $id > 0 ? t('sms_sim_saved') : t('sms_sim_bad_phone');
    header('Location: sms_config.php');
    exit;
}

if ($action === 'default_sim') {
    $ok = ame_sms_sim_make_default($db, (int)($_POST['sim_id'] ?? 0));
    $_SESSION['sms_flash'] = $ok ? t('sms_sim_now_default') : t('sms_sim_failed');
    header('Location: sms_config.php');
    exit;
}

if ($action === 'remove_sim') {
    $ok = ame_sms_sim_remove($db, (int)($_POST['sim_id'] ?? 0));
    $_SESSION['sms_flash'] = $ok ? t('sms_sim_removed') : t('sms_sim_failed');
    header('Location: sms_config.php');
    exit;
}

if ($action === 'rotate_sim') {
    $token = ame_sms_sim_rotate($db, (int)($_POST['sim_id'] ?? 0));
    $_SESSION['sms_flash'] = $token !== '' ? t('sms_sim_link_new') : t('sms_sim_failed');
    header('Location: sms_config.php');
    exit;
}

if ($action === 'test') {
    $mobile = preg_replace('/[^0-9]/', '', (string)($_POST['test_mobile'] ?? ''));
    $typed = trim((string)($_POST['test_message'] ?? ''));
    $msg = ($typed === '' || $typed === 'APONSHOP সিম এসএমএস টেস্ট') ? ame_sms_text('sim_test') : $typed;
    $ok = send_sms($mobile, $msg, 'sim_test', (int)($_POST['sim_id'] ?? 0));
    $_SESSION['sms_flash'] = $ok
        ? 'টেস্ট কিউ হয়েছে। সিম ফোনের পেজ খোলা থাকলে এখনই পাঠান।'
        : 'টেস্ট যায়নি। নম্বর ও স্ট্যাটাস চালু আছে কিনা দেখুন।';
    header('Location: sms_config.php');
    exit;
}

$url = trim((string)($_POST['api_url'] ?? ''));
$key = trim((string)($_POST['api_key'] ?? ''));
$sid = trim((string)($_POST['sender_id'] ?? ''));
$status = (($_POST['status'] ?? '') === 'active') ? 'active' : 'inactive';
$channel = (($_POST['channel'] ?? 'sim') === 'api') ? 'api' : 'sim';
$extra = [
    'channel' => $channel,
    'gateway_phone' => (string)($_POST['gateway_phone'] ?? ''),
    'smsgate_url' => trim((string)($_POST['smsgate_url'] ?? '')),
    'smsgate_user' => trim((string)($_POST['smsgate_user'] ?? '')),
    'smsgate_pass' => (string)($_POST['smsgate_pass'] ?? ''),
];

if (ame_sms_save($db, $url, $key, $sid, $status, $extra)) {
    log_activity('SMS Settings Updated', 'Settings', 'এসএমএস চ্যানেল=' . $channel);
    $_SESSION['sms_flash'] = 'এসএমএস সেটিংস সেভ হয়েছে।';
    header('Location: sms_config.php');
    exit;
}

echo 'সেভ হয়নি। আবার চেষ্টা করুন।';
