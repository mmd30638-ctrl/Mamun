<?php
require_once '../../includes/config.php';
require_once '../../includes/user_functions.php';
admin_only();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: stores.php');
    exit();
}

csrf_verify();
ensure_store_schema($db);
ensure_user_schema($db);

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['code'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$is_active = isset($_POST['is_active']) ? 1 : 0;
$is_default = isset($_POST['is_default']) ? 1 : 0;

if ($name === '' || $code === '') {
    header('Location: stores.php?error=' . rawurlencode('নাম ও কোড দিতে হবে।'));
    exit();
}

if ($id > 0 && $is_active === 0) {
    $active_count = 0;
    $res = $db->query('SELECT COUNT(*) c FROM stores WHERE is_active = 1 AND id <> ' . $id);
    if ($res) {
        $active_count = (int)($res->fetch_assoc()['c'] ?? 0);
    }
    if ($active_count <= 0) {
        header('Location: stores.php?error=' . rawurlencode('শেষ চালু স্টোর বন্ধ করা যাবে না।'));
        exit();
    }
}

if ($is_default === 1) {
    $db->query('UPDATE stores SET is_default = 0');
    $is_active = 1;
}

if ($id > 0) {
    $stmt = $db->prepare(
        'UPDATE stores SET code = ?, name = ?, phone = ?, address = ?, is_active = ?, is_default = ? WHERE id = ?'
    );
    $stmt->bind_param('ssssiii', $code, $name, $phone, $address, $is_active, $is_default, $id);
} else {
    $stmt = $db->prepare(
        'INSERT INTO stores (code, name, phone, address, is_active, is_default) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssii', $code, $name, $phone, $address, $is_active, $is_default);
}

try {
    $stmt->execute();
    if ($id <= 0) {
        $id = (int)$stmt->insert_id;
    }
    $stmt->close();
} catch (Throwable $e) {
    header('Location: stores.php?error=' . rawurlencode('এই কোড ইতিমধ্যে আছে, অন্য কোড দিন।'));
    exit();
}

if ($is_default === 1 && $id > 0) {
    $_SESSION['current_store_id'] = $id;
}

$login_username = trim((string)($_POST['login_username'] ?? ''));
$login_password = (string)($_POST['login_password'] ?? '');
$login_confirm = (string)($_POST['login_password_confirm'] ?? '');
$login_note = '';

if ($login_username !== '' || $login_password !== '') {
    if ($login_password !== $login_confirm) {
        header('Location: store_edit.php?id=' . $id . '&error=' . rawurlencode('দুটো পাসওয়ার্ড মিলছে না।'));
        exit();
    }
    [$login_ok, $login_msg] = ame_store_create_login($db, $id, $login_username, $login_password, $phone);
    if (!$login_ok) {
        header('Location: store_edit.php?id=' . $id . '&error=' . rawurlencode($login_msg));
        exit();
    }
    $login_note = '&login=' . rawurlencode($login_username);
}

log_activity('save', 'stores', 'Store saved: ' . $name);
header('Location: stores.php?saved=1' . $login_note);
exit();
