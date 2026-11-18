<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
require_once '../../includes/user_profile.php';
if (is_file('../../includes/super_admin.php')) {
    require_once '../../includes/super_admin.php';
}
admin_only();
ensure_user_schema($db);
if (function_exists('ame_super_admin_boot')) {
    ame_super_admin_boot($db);
}
require_post();
csrf_verify();

$action = strtolower(trim((string) ($_POST['action'] ?? 'save')));
$userId = (int) ($_POST['user_id'] ?? 0);
$empId = (int) ($_POST['emp_id'] ?? 0);
$back = ame_profile_href($userId, $empId);

$flash = '';
$ok = false;

if ($action === 'save') {
    $result = ame_profile_save($db, $_POST, $_FILES);
    $ok = !empty($result['ok']);
    $flash = $ok ? 'প্রোফাইল সংরক্ষণ হয়েছে। নাম, যোগাযোগ ও লগইন একসাথে আপডেট।' : (string) ($result['error'] ?? 'সংরক্ষণ হয়নি।');
    $userId = (int) ($result['user_id'] ?? $userId);
    $empId = (int) ($result['emp_id'] ?? $empId);
    $back = ame_profile_href($userId, $empId);
} elseif ($action === 'revoke' || $action === 'grant') {
    if (!function_exists('ame_is_super_admin') || !ame_is_super_admin()) {
        $flash = 'শুধু সুপার অ্যাডমিন দায়িত্ব দিতে বা সরাতে পারেন।';
    } elseif (!ame_profile_actor_password_ok($db, (string) ($_POST['actor_password'] ?? ''))) {
        $flash = 'আপনার পাসওয়ার্ড মেলেনি। দায়িত্ব বদলাতে নিজের পাসওয়ার্ড দিন।';
    } else {
        $err = ame_super_admin_set($db, $userId, $action === 'grant');
        $ok = $err === '';
        $flash = $ok
            ? ($action === 'grant' ? 'সুপার অ্যাডমিন দায়িত্ব দেওয়া হয়েছে।' : 'সুপার অ্যাডমিন দায়িত্ব সরানো হয়েছে। এখন প্রোফাইল এডিট, সরানো বা মুছা যাবে।')
            : $err;
        if ($ok && function_exists('ame_super_admin_boot')) {
            ame_super_admin_boot($db);
        }
    }
} elseif ($action === 'remove') {
    $err = ame_remove_user($db, $userId);
    $ok = $err === null;
    $flash = $ok ? 'ইউজার তালিকা থেকে সরানো হয়েছে। লগইন বন্ধ। খাতা আছে।' : $err;
} elseif ($action === 'restore') {
    $err = ame_restore_user($db, $userId);
    $ok = $err === null;
    $flash = $ok ? 'ইউজার ফেরত এসেছে। আবার লগইন করতে পারবে।' : $err;
} elseif ($action === 'purge') {
    if (!function_exists('ame_is_super_admin') || !ame_is_super_admin()) {
        $flash = 'চিরতরে মুছতে সুপার অ্যাডমিন লাগবে।';
    } elseif (!ame_profile_actor_password_ok($db, (string) ($_POST['actor_password'] ?? ''))) {
        $flash = 'আপনার পাসওয়ার্ড মেলেনি।';
    } else {
        $err = ame_purge_user($db, $userId);
        $ok = $err === null;
        $flash = $ok ? 'ইউজার চিরতরে ডিলিট। লগইন নেই। বিক্রয় ও লেজার অক্ষত।' : $err;
        if ($ok) {
            $back = '/modules/settings/users.php?status=archive';
        }
    }
} else {
    $flash = 'অজানা কাজ।';
}

$_SESSION['user_flash'] = $flash;
$_SESSION['user_flash_ok'] = $ok;
header('Location: ' . $back);
exit;
