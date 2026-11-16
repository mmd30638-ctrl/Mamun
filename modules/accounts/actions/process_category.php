<?php
/* 10.98.789 */
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/account_functions.php';
require_once __DIR__ . '/../../../includes/txn_types.php';

require_post();
$is_ajax = !empty($_POST['ajax']);
if ($is_ajax) {
    $posted = (string) ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || $posted === '' || !hash_equals((string) $_SESSION['csrf_token'], $posted)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => 'নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    csrf_verify();
}
ensure_accounts_schema($db);

$back = '../categories.php';
$id = (int) ($_POST['id'] ?? 0);
$name = ame_txn_type_normalize_name((string) ($_POST['name'] ?? ''));

$flash = static function (string $message, bool $ok, int $id = 0, string $shown = '') use ($back, $is_ajax): void {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $ok, 'message' => $message, 'id' => $id, 'name' => $shown], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['user_flash'] = $message;
    $_SESSION['user_flash_ok'] = $ok;
    header('Location: ' . $back);
    exit;
};

if ($id > 0) {
    $result = ame_txn_type_rename($db, $id, $name);
    $flash($result['message'], $result['ok'], $result['id'], $result['name']);
}

if ($name === '') {
    $flash('ধরনের নাম দিন।', false);
}

$find = $db->prepare('SELECT id FROM expense_categories WHERE name = ? LIMIT 1');
if ($find) {
    $find->bind_param('s', $name);
    $find->execute();
    $exists = $find->get_result()->fetch_assoc();
    $find->close();
    if ($exists) {
        if ($is_ajax) {
            $flash('এই ধরন আগেই আছে।', true, (int) $exists['id'], $name);
        }
        $flash('এই নামে ধরন আগেই আছে।', false);
    }
}

$stmt = $db->prepare('INSERT INTO expense_categories (name) VALUES (?)');
if (!$stmt) {
    $flash('ধরন যোগ করা যায়নি: ' . $db->error, false);
}
$stmt->bind_param('s', $name);
$saved = $stmt->execute();
$err = $stmt->error;
$new_id = (int) $stmt->insert_id;
$stmt->close();

if (!$saved) {
    $flash('ধরন যোগ করা যায়নি: ' . $err, false);
}
$flash('ধরন যোগ হয়েছে।', true, $new_id, $name);
