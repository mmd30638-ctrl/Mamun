<?php
require_once '../../includes/config.php';
require_once '../../includes/permission_functions.php';
admin_only();
require_post();
csrf_verify();
ensure_permission_schema($db);

$user_id = (int)($_POST['user_id'] ?? 0);
if ($user_id <= 0) {
    header('Location: role_permissions.php');
    exit;
}

// BUG FIX: এই prepare()-টা try/catch-এর বাইরে ছিল, তাই ব্যর্থ হলে (false)
// পরের bind_param() কলে ফ্যাটাল এরর হয়ে পুরো সেভ-অ্যাকশনটাই ভেঙে পড়ত।
$u = $db->prepare("SELECT id, role FROM users WHERE id=? LIMIT 1");
if (!$u) {
    error_log('process_role_permissions.php: prepare(users) failed — ' . $db->error);
    header('Location: role_permissions.php');
    exit;
}
$u->bind_param('i', $user_id);
$u->execute();
$user = $u->get_result()->fetch_assoc();
$u->close();
if (!$user || strtolower((string)$user['role']) === 'admin') {
    header('Location: role_permissions.php');
    exit;
}

$valid = array_keys(get_all_modules());
$mods = $_POST['mods'] ?? [];
if (!is_array($mods)) {
    $mods = [];
}

$db->begin_transaction();
try {
    $del = $db->prepare("DELETE FROM user_permissions WHERE user_id=?");
    $del->bind_param('i', $user_id);
    $del->execute();
    $del->close();

    $ins = $db->prepare("INSERT INTO user_permissions (user_id, module_key) VALUES (?, ?)");
    foreach ($mods as $mod) {
        $mod = (string)$mod;
        if (!in_array($mod, $valid, true)) {
            continue;
        }
        $ins->bind_param('is', $user_id, $mod);
        $ins->execute();
    }
    $ins->close();

    $flag = $db->prepare("UPDATE users SET use_custom_perms=1 WHERE id=?");
    $flag->bind_param('i', $user_id);
    $flag->execute();
    $flag->close();

    $db->commit();
    try {
        log_activity('Permissions Updated', 'Settings', 'user #' . $user_id, [
            'action_type' => 'permission',
            'entity_type' => 'user',
            'entity_id' => (string)$user_id,
            'reference' => 'user #' . $user_id,
            'new_value' => implode(', ', array_values(array_filter(array_map('strval', $mods)))),
        ]);
    } catch (Throwable $e) {}
} catch (Throwable $e) {
    $db->rollback();
    die('সেভ হয়নি। আবার চেষ্টা করুন।');
}

header('Location: role_permissions.php?user=' . $user_id . '&saved=1');
exit;
