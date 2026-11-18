<?php
// ফোল্ডার পথ: public_html/process_new_password.php
require_once 'includes/config.php';
require_once 'includes/user_functions.php';
require_once 'includes/mail_functions.php';
ensure_user_schema($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$session_token = $_SESSION['csrf_token'] ?? '';
$posted_token = $_POST['csrf_token'] ?? '';
$raw_token = trim($_POST['token'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$new_password_confirm = $_POST['new_password_confirm'] ?? '';

if (empty($session_token) || !hash_equals($session_token, $posted_token)) {
    die("<script>alert('নিরাপত্তা যাচাই ব্যর্থ হয়েছে।'); window.location='login.php';</script>");
}

// ================= প্রাথমিক ইনপুট যাচাই (পাসওয়ার্ড সংক্রান্ত — ডাটাবেজ লাগে না) =================
$pw_err = ame_validate_password((string)$new_password);
if ($pw_err !== null) {
    $_SESSION['reset_error'] = $pw_err;
    header('Location: reset_password.php?token=' . urlencode($raw_token));
    exit();
}
if ($new_password !== $new_password_confirm) {
    $_SESSION['reset_error'] = 'দুটো পাসওয়ার্ড মিলছে না।';
    header('Location: reset_password.php?token=' . urlencode($raw_token));
    exit();
}

// একটা প্রাথমিক (লক ছাড়া) চেক — যাতে সাথে সাথেই সাধারণ "মেয়াদ শেষ" বার্তা দেখানো যায়
if (!verify_reset_token($db, $raw_token)) {
    die("<script>alert('লিংকের মেয়াদ শেষ হয়ে গেছে। আবার চেষ্টা করুন।'); window.location='forgot_password.php';</script>");
}

$transaction_started = false;

try {
    $db->begin_transaction();
    $transaction_started = true;

    // ================= টোকেন পুনরায় যাচাই — এবার row-lock সহ, ট্রানজেকশনের ভেতরে =================
    // (একই লিংক দ্রুত দুইবার সাবমিট করলে যাতে দুটোই পাস না করে যায়, সেই
    //  race-condition বন্ধ করার জন্য FOR UPDATE লক ব্যবহার করা হয়েছে)
    $token_data = verify_reset_token($db, $raw_token, true);
    if (!$token_data) {
        throw new RuntimeException('TOKEN_ALREADY_USED');
    }

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    $upd = $db->prepare(
        "UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE id = ?"
    );
    $upd->bind_param('si', $hashed, $token_data['user_id']);
    $upd->execute();

    // ================= টোকেন ব্যবহৃত হিসেবে চিহ্নিত (দ্বিতীয়বার ব্যবহার ঠেকাতে) =================
    $mark_used = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
    $mark_used->bind_param('i', $token_data['id']);
    $mark_used->execute();

    log_activity('Password Reset Completed', 'Security', $token_data['username']);

    $db->commit();
    $transaction_started = false;

    echo "<script>alert('✅ পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে। এখন লগইন করুন।'); window.location='login.php';</script>";
} catch (Throwable $e) {
    if ($transaction_started) {
        $db->rollback();
    }
    if ($e->getMessage() === 'TOKEN_ALREADY_USED') {
        die("<script>alert('এই লিংকটা ইতিমধ্যে ব্যবহৃত হয়ে গেছে অথবা মেয়াদ শেষ হয়ে গেছে।'); window.location='forgot_password.php';</script>");
    }
    error_log('Password reset completion failed: ' . $e->getMessage());
    $_SESSION['reset_error'] = 'পাসওয়ার্ড পরিবর্তনে সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    header('Location: reset_password.php?token=' . urlencode($raw_token));
    exit();
}
