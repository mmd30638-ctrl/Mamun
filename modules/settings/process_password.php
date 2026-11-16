<?php
/*
 * AME process_password.php
 * শুধু এই পথে রাখুন: public_html/modules/settings/process_password.php
 * process_login.php, index.php বা header.php-এ বসাবেন না।
 */

require_once __DIR__ . '/../../includes/config.php';

$ame_pw_back = static function ($message, $is_error = true) {
    if ($is_error) {
        $_SESSION['error_message'] = $message;
        unset($_SESSION['success_message']);
    } else {
        $_SESSION['success_message'] = $message;
        unset($_SESSION['error_message']);
    }

    header('Location: change_password.php');
    exit();
};

$is_english = function_exists('current_lang')
    && current_lang() === 'en';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: change_password.php');
    exit();
}

$session_token = (string)($_SESSION['csrf_token'] ?? '');
$posted_token = (string)($_POST['csrf_token'] ?? '');

if (
    $session_token === ''
    || $posted_token === ''
    || !hash_equals($session_token, $posted_token)
) {
    $ame_pw_back(
        $is_english
            ? 'The form expired. Please try again.'
            : 'ফর্মের মেয়াদ শেষ। আবার চেষ্টা করুন।'
    );
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0) {
    header('Location: /login.php');
    exit();
}

$new_pass = (string)($_POST['new_pass'] ?? '');
$confirm_pass = (string)($_POST['confirm_pass'] ?? '');
$pw_err = ame_validate_password($new_pass);
if ($pw_err !== null) {
    $ame_pw_back($pw_err);
}

if ($new_pass !== $confirm_pass) {
    $ame_pw_back(
        $is_english
            ? 'The two passwords do not match.'
            : 'পাসওয়ার্ড দুটি মিলেনি।'
    );
}

if (!isset($db) || !($db instanceof mysqli)) {
    $ame_pw_back(
        $is_english
            ? 'The password could not be updated.'
            : 'পাসওয়ার্ড আপডেট করা যায়নি।'
    );
}

$hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
$stmt = $db->prepare(
    'UPDATE users SET password = ?, must_change_password = 0 WHERE id = ? LIMIT 1'
);

if (!$stmt) {
    $ame_pw_back(
        $is_english
            ? 'The password could not be updated.'
            : 'পাসওয়ার্ড আপডেট করা যায়নি।'
    );
}

$stmt->bind_param('si', $hashed_pass, $user_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    $ame_pw_back(
        $is_english
            ? 'The password could not be updated.'
            : 'পাসওয়ার্ড আপডেট করা যায়নি।'
    );
}

ame_bump_auth_version($db, $user_id);
$_SESSION['auth_version'] = (int)($_SESSION['auth_version'] ?? 0) + 1;

if (function_exists('log_activity')) {
    log_activity(
        'Password Changed',
        'Settings',
        $is_english
            ? 'Changed own login password'
            : 'নিজের পাসওয়ার্ড পরিবর্তন করলেন'
    );
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$was_required = !empty($_SESSION['must_change_password']) || !empty($_POST['required']);
unset($_SESSION['must_change_password']);

if ($was_required) {
    $_SESSION['success_message'] = $is_english
        ? 'Password updated. You can now use the system.'
        : 'পাসওয়ার্ড আপডেট হয়েছে। এখন সিস্টেম ব্যবহার করতে পারবেন।';
    header('Location: /index.php');
    exit();
}

$ame_pw_back(
    $is_english
        ? 'Password updated successfully.'
        : 'পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে।',
    false
);