<?php
/**
 * বারবার ভুল পাসওয়ার্ড দেওয়ার কারণে কোনো ইউজারের লগইন সাময়িকভাবে (১৫
 * মিনিটের জন্য) বন্ধ হয়ে গেলে (includes/login_lockout.php-এর
 * ame_login_record()), অ্যাডমিন এখান থেকে এখনই সেই লক তুলে দিতে পারবেন —
 * ১৫ মিনিট অপেক্ষা করার দরকার নেই। এতদিন এই লকটা কোনো স্ক্রিনে দেখাই যেত
 * না, তাই সঠিক পাসওয়ার্ড দেওয়ার পরেও "ইউজারনেম অথবা পাসওয়ার্ড সঠিক নয়"
 * দেখাতে থাকতো এবং অ্যাডমিনও বুঝতে পারতেন না আসল কারণ কী।
 */
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
require_once '../../includes/login_lockout.php';
admin_only();
ensure_user_schema($db);
ame_ensure_login_lockout_schema($db);
require_post();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$back = 'users.php';
$from = (string)($_POST['from'] ?? '');
if ($from === 'view' && $id > 0) {
    $back = 'user_view.php?id=' . $id;
}

if ($id > 0) {
    // সরানো (soft-removed) ইউজারকে যেন ভুল করে "আনলক" দেখানো/করা না হয় —
    // বাকি মডিউলের মতোই removed_at যাচাই করা হচ্ছে।
    $present = ame_sql_user_present();
    $stmt = $db->prepare("UPDATE users SET locked_until = NULL WHERE id = ? AND {$present} LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['user_flash'] = 'লগইন লক এখনই খুলে দেওয়া হলো — এখন লগইন করতে পারবে। / Login lock cleared now — can sign in.';
        $_SESSION['user_flash_ok'] = true;
    } else {
        $_SESSION['user_flash'] = 'লক খোলা যায়নি, আবার চেষ্টা করুন। / Could not clear the lock, try again.';
        $_SESSION['user_flash_ok'] = false;
    }
}

header('Location: ' . $back);
exit();
