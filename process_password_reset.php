<?php
require_once 'includes/config.php';
require_once 'includes/user_functions.php';
require_once 'includes/password_reset_otp.php';
ensure_user_schema($db);
ensure_password_reset_schema($db);

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!empty($_GET['restart'])) {
    ame_clear_password_reset_session();
    header('Location: forgot_password.php');
    exit();
}

if (!empty($_GET['resend'])) {
    if (empty($_SESSION['reset_user_id']) || empty($_SESSION['reset_channel'])) {
        $_SESSION['reset_error'] = 'আবার শুরু করুন।';
        header('Location: forgot_password.php');
        exit();
    }
    if (ame_reset_rate_limit_session()) {
        $_SESSION['reset_error'] = 'অনেকবার চেষ্টা হয়েছে। ৫ মিনিট পর আবার চেষ্টা করুন।';
        header('Location: forgot_password.php');
        exit();
    }
    $uid = (int)$_SESSION['reset_user_id'];
    $user = ame_reset_user_by_id($db, $uid);
    $channel = (string)$_SESSION['reset_channel'];
    if ($user && ame_user_is_active($user) && !ame_too_many_otp_requests($db, $uid)) {
        $send = ame_send_password_reset_otp($db, $user, $channel);
        if ($send['mock'] && !empty($send['otp'])) {
            $_SESSION['reset_otp_preview'] = $send['otp'];
            $_SESSION['reset_mock_notice'] = $send['message'];
        } else {
            unset($_SESSION['reset_otp_preview'], $_SESSION['reset_mock_notice']);
        }
        $_SESSION['reset_notice'] = $send['message'];
    } else {
        $_SESSION['reset_notice'] = 'নতুন OTP পাঠানো হয়েছে (যদি তথ্য সঠিক থাকে)।';
    }
    $_SESSION['reset_step'] = 'otp';
    header('Location: forgot_password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit();
}

$session_token = $_SESSION['csrf_token'] ?? '';
$posted_token = $_POST['csrf_token'] ?? '';
if ($session_token === '' || !hash_equals($session_token, $posted_token)) {
    $_SESSION['reset_error'] = 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
    header('Location: forgot_password.php');
    exit();
}

$step = (string)($_POST['step'] ?? '');
$generic_unknown = 'ইউজারনেম সঠিক না হলে বা ইমেইল/মোবাইল সংরক্ষিত না থাকলে OTP পাঠানো যাবে না।';

if ($step === 'lookup') {
    if (ame_reset_rate_limit_session()) {
        $_SESSION['reset_error'] = 'অনেকবার চেষ্টা হয়েছে। ৫ মিনিট পর আবার চেষ্টা করুন।';
        header('Location: forgot_password.php');
        exit();
    }
    $login = trim((string)($_POST['username'] ?? ''));
    if ($login === '') {
        $_SESSION['reset_error'] = 'ইউজারনেম, ইমেইল বা মোবাইল লিখুন।';
        header('Location: forgot_password.php');
        exit();
    }

    $user = ame_reset_find_user($db, $login);

    if (!$user || !ame_user_is_active($user)) {
        $_SESSION['reset_error'] = $generic_unknown;
        $_SESSION['reset_step'] = 'username';
        header('Location: forgot_password.php');
        exit();
    }

    $channels = ame_user_reset_channels($user);
    $_SESSION['reset_user_id'] = (int)$user['id'];
    $_SESSION['reset_username'] = (string)$user['username'];

    if (!$channels) {
        $_SESSION['reset_step'] = 'no_contact';
        $_SESSION['reset_error'] = 'এই একাউন্টে ইমেইল, মোবাইল বা WhatsApp নম্বর নেই। অ্যাডমিনকে বলুন সেটিংস → ইউজার থেকে যোগ করতে।';
    } else {
        $_SESSION['reset_step'] = 'channel';
        $_SESSION['reset_notice'] = 'একাউন্ট পাওয়া গেছে। OTP পাঠানোর মাধ্যম বেছে নিন।';
    }
    header('Location: forgot_password.php');
    exit();
}

if ($step === 'send_otp') {
    if (empty($_SESSION['reset_user_id'])) {
        header('Location: forgot_password.php');
        exit();
    }
    if (ame_reset_rate_limit_session()) {
        $_SESSION['reset_error'] = 'অনেকবার চেষ্টা হয়েছে। ৫ মিনিট পর আবার চেষ্টা করুন।';
        header('Location: forgot_password.php');
        exit();
    }

    $channel = (string)($_POST['channel'] ?? '');
    $uid = (int)$_SESSION['reset_user_id'];
    $user = ame_reset_user_by_id($db, $uid);

    if ($user && ame_user_is_active($user)) {
        $channels = ame_user_reset_channels($user);
        if (isset($channels[$channel]) && !ame_too_many_otp_requests($db, $uid)) {
            $send = ame_send_password_reset_otp($db, $user, $channel);
            if ($send['ok']) {
                $_SESSION['reset_channel'] = $channel;
                $_SESSION['reset_destination_mask'] = $channels[$channel]['hint'];
                $_SESSION['reset_step'] = 'otp';
                $_SESSION['reset_notice'] = $send['message'];
                if ($send['mock'] && !empty($send['otp'])) {
                    $_SESSION['reset_otp_preview'] = $send['otp'];
                    $_SESSION['reset_mock_notice'] = $send['message'];
                } else {
                    unset($_SESSION['reset_otp_preview'], $_SESSION['reset_mock_notice']);
                }
                try {
                    log_activity('Password Reset OTP Requested', 'Security', $user['username'] . ' via ' . $channel);
                } catch (Throwable $e) {
                }
                header('Location: forgot_password.php');
                exit();
            }
            $_SESSION['reset_error'] = $send['message'];
            header('Location: forgot_password.php');
            exit();
        }
    }

    $_SESSION['reset_error'] = 'OTP পাঠানো যায়নি। আবার চেষ্টা করুন।';
    header('Location: forgot_password.php');
    exit();
}

if ($step === 'verify_otp') {
    if (empty($_SESSION['reset_user_id'])) {
        header('Location: forgot_password.php');
        exit();
    }
    $otp = (string)($_POST['otp'] ?? '');
    $uid = (int)$_SESSION['reset_user_id'];
    $row = ame_verify_password_reset_otp($db, $uid, $otp);
    if (!$row) {
        $_SESSION['reset_error'] = 'OTP ভুল অথবা মেয়াদ শেষ। আবার চেষ্টা করুন।';
        header('Location: forgot_password.php');
        exit();
    }
    $_SESSION['reset_verified'] = 1;
    $_SESSION['reset_otp_id'] = (int)$row['id'];
    $_SESSION['reset_step'] = 'password';
    unset($_SESSION['reset_otp_preview'], $_SESSION['reset_mock_notice']);
    $_SESSION['reset_notice'] = 'OTP যাচাই হয়েছে। নতুন পাসওয়ার্ড সেট করুন।';
    header('Location: forgot_password.php');
    exit();
}

if ($step === 'set_password') {
    if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_user_id']) || empty($_SESSION['reset_otp_id'])) {
        $_SESSION['reset_error'] = 'সেশন শেষ হয়েছে। আবার শুরু করুন।';
        header('Location: forgot_password.php?restart=1');
        exit();
    }

    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['new_password_confirm'] ?? '');
    $pw_err = ame_validate_password($new_password);
    if ($pw_err !== null) {
        $_SESSION['reset_error'] = $pw_err;
        header('Location: forgot_password.php');
        exit();
    }
    if ($new_password !== $confirm) {
        $_SESSION['reset_error'] = 'দুটো পাসওয়ার্ড মিলছে না।';
        header('Location: forgot_password.php');
        exit();
    }

    $uid = (int)$_SESSION['reset_user_id'];
    $otp_id = (int)$_SESSION['reset_otp_id'];
    $transaction_started = false;

    try {
        $db->begin_transaction();
        $transaction_started = true;

        $check = $db->prepare('SELECT id FROM password_reset_otps WHERE id = ? AND user_id = ? AND used = 0 AND expires_at > NOW() FOR UPDATE');
        $check->bind_param('ii', $otp_id, $uid);
        $check->execute();
        $valid = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$valid) {
            throw new RuntimeException('OTP_EXPIRED');
        }

        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $db->prepare(
            'UPDATE users SET password = ?, must_change_password = 0, auth_version = auth_version + 1 WHERE id = ?'
        );
        $upd->bind_param('si', $hashed, $uid);
        $upd->execute();
        $upd->close();

        ame_mark_otp_used($db, $otp_id);
        ame_invalidate_active_otps($db, $uid);

        $uname = (string)($_SESSION['reset_username'] ?? '');
        try {
            log_activity('Password Reset Completed (OTP)', 'Security', $uname);
        } catch (Throwable $e) {
        }

        $db->commit();
        $transaction_started = false;
        ame_clear_password_reset_session();
        $_SESSION['login_notice'] = '✅ পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে। এখন লগইন করুন।';
        header('Location: login.php');
        exit();
    } catch (Throwable $e) {
        if ($transaction_started) {
            $db->rollback();
        }
        error_log('Password reset set_password failed: ' . $e->getMessage());
        $_SESSION['reset_error'] = $e->getMessage() === 'OTP_EXPIRED'
            ? 'OTP মেয়াদ শেষ। আবার শুরু করুন।'
            : 'পাসওয়ার্ড পরিবর্তনে সমস্যা হয়েছে।';
        header('Location: forgot_password.php');
        exit();
    }
}

header('Location: forgot_password.php');
exit();
