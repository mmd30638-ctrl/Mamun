<?php
/**
 * ইউজার রিসেট — এক্সিকিউট হ্যান্ডলার।
 *
 * নিরাপত্তা স্তর (একটাও বাদ দিলে এই ফাইলের কিছুই ঘটবে না):
 *   ১. admin_only() — শুধু অ্যাডমিন
 *   ২. CSRF টোকেন
 *   ৩. অ্যাডমিনের নিজের বর্তমান পাসওয়ার্ড আবার যাচাই (re-authentication) —
 *      সেশন হাইজ্যাক/অসতর্ক ক্লিক থেকে সুরক্ষা
 *   ৪. হুবহু "DELETE USERS" টাইপ করা — ভুল ক্লিকে যেন না হয়ে যায়
 *   ৫. role='admin' থাকা কাউকেই টার্গেট তালিকায় রাখা হয় না
 *   ৬. সবটা একটাই DB ট্রানজেকশনে — কোনো একজনের ক্ষেত্রে সমস্যা হলে
 *      পুরোটাই ROLLBACK, কেউই আধা-পরিবর্তিত অবস্থায় থাকবে না
 *
 * বাস্তবায়ন: প্রতিটা টার্গেট ইউজারের জন্য এই অ্যাপের আগে থেকে থাকা,
 * ভালোভাবে-পরীক্ষিত ame_remove_user() ফাংশনই কল করা হয় (users.php-এর
 * এক-এক করে "সরান" বাটনের পেছনের একই লজিক) — নতুন করে ডিলিট-লজিক লেখা
 * হয়নি, যাতে বিদ্যমান সিস্টেমের সাথে সামঞ্জস্য থাকে ও নতুন বাগ না ঢোকে।
 */
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
admin_only();
ensure_user_schema($db);
require_post();
csrf_verify();

function ame_user_reset_fail(string $msg): void
{
    $_SESSION['user_flash'] = $msg;
    $_SESSION['user_flash_ok'] = false;
    header('Location: user_reset.php');
    exit();
}

$admin_id = (int)($_SESSION['user_id'] ?? 0);
if ($admin_id <= 0) {
    ame_user_reset_fail('সেশন সঠিক নয়, আবার লগইন করুন। / Session invalid, please log in again.');
}

// ================= ৩. পাসওয়ার্ড পুনঃযাচাই =================
$admin_password = (string)($_POST['admin_password'] ?? '');
if ($admin_password === '') {
    ame_user_reset_fail('আপনার পাসওয়ার্ড দিন। / Enter your password.');
}
$stmt = $db->prepare('SELECT id, username, password, role FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$me || strtolower((string)($me['role'] ?? '')) !== 'admin') {
    ame_user_reset_fail('শুধুমাত্র অ্যাডমিন এই কাজ করতে পারবেন। / Only an admin can do this.');
}
if (!password_verify($admin_password, (string)$me['password'])) {
    ame_user_reset_fail('পাসওয়ার্ড সঠিক নয়। / Incorrect password.');
}

// ================= ৪. টাইপ করা নিশ্চিতকরণ যাচাই =================
$confirm_text = trim((string)($_POST['confirm_text'] ?? ''));
if ($confirm_text !== 'DELETE USERS') {
    ame_user_reset_fail('নিশ্চিতকরণ লেখাটা হুবহু মেলেনি — "DELETE USERS" ঠিকভাবে লিখুন। / Confirmation text did not match exactly — type "DELETE USERS".');
}

// ================= ৫. টার্গেট তালিকা (role='admin' বাদে সব একটিভ ইউজার) =================
$present = ame_sql_user_present();
$target_ids = [];
$target_res = $db->query("SELECT id FROM users WHERE {$present} AND LOWER(role) <> 'admin'");
if ($target_res) {
    while ($row = $target_res->fetch_assoc()) {
        $target_ids[] = (int)$row['id'];
    }
}

if (!$target_ids) {
    ame_user_reset_fail('অ্যাডমিন ছাড়া আর কোনো একটিভ ইউজার নেই — করার কিছু নেই। / No other active users besides admin — nothing to do.');
}

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

// ================= ৬. একটাই ট্রানজেকশনে বাল্ক সফট-রিমুভ =================
$db->begin_transaction();
try {
    $removed_ids = [];
    foreach ($target_ids as $uid) {
        $err = ame_remove_user($db, $uid);
        if ($err !== null) {
            throw new RuntimeException("ইউজার #{$uid} সরাতে ব্যর্থ: {$err}");
        }
        $removed_ids[] = $uid;
    }

    $details = sprintf(
        'USER_RESET | Executed by: %s (#%d) | Removed: %d users [%s] | IP: %s | UA: %s | Result: SUCCESS',
        (string)$me['username'],
        $admin_id,
        count($removed_ids),
        implode(',', $removed_ids),
        $ip,
        $ua
    );
    log_activity('user_reset', 'settings', $details);

    $db->commit();

    $_SESSION['user_flash'] = sprintf(
        '✅ %d জন Non-Admin ইউজার সরানো হয়েছে — অ্যাডমিন সুরক্ষিত ছিল, কোনো ব্যবসায়িক তথ্য মুছেনি। / %d non-admin users removed — admin was protected, no business data was deleted.',
        count($removed_ids),
        count($removed_ids)
    );
    $_SESSION['user_flash_ok'] = true;
} catch (Throwable $e) {
    $db->rollback();
    error_log('User reset failed: ' . $e->getMessage());
    $details = sprintf(
        'USER_RESET | Executed by: %s (#%d) | IP: %s | UA: %s | Result: FAILED | Error: %s',
        (string)$me['username'],
        $admin_id,
        $ip,
        $ua,
        $e->getMessage()
    );
    if (function_exists('log_activity')) {
        log_activity('user_reset_failed', 'settings', $details);
    }
    $_SESSION['user_flash'] = '❌ রিসেট ব্যর্থ হয়েছে — কোনো তথ্য পরিবর্তন হয়নি, রোলব্যাক সম্পন্ন। / Reset failed — nothing changed, rollback complete.';
    $_SESSION['user_flash_ok'] = false;
}

header('Location: users.php');
exit();
