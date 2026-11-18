<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
admin_only();
require_post();
csrf_verify();
ensure_user_schema($db);

$label = clean_input($_POST['role_label'] ?? '');
if ($label === '') {
    header("Location: user_add.php");
    exit();
}

// role_key বানানো: ছোট হাতের অক্ষর, স্পেস আন্ডারস্কোর দিয়ে
$key = strtolower(trim($label));
$key = preg_replace('/[^a-z0-9\x{0980}-\x{09FF}]+/u', '_', $key);
$key = trim($key, '_');
if ($key === '') { $key = 'role_' . time(); }

$check = $db->prepare("SELECT id FROM user_roles WHERE role_key = ?");
$check->bind_param("s", $key);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    $stmt = $db->prepare("INSERT INTO user_roles (role_key, role_label) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $label);
    $stmt->execute();
}

header("Location: user_add.php?new_role=" . urlencode($key));
exit();
?>
