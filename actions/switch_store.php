<?php
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php', true, 303);
    exit();
}

csrf_verify();

$store_id = (int)($_POST['store_id'] ?? 0);
if ($store_id > 0 && ame_user_can_access_store($db, $store_id)) {
    $check = $db->prepare('SELECT id FROM stores WHERE id = ? AND is_active = 1 LIMIT 1');
    if ($check) {
        $check->bind_param('i', $store_id);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            $_SESSION['current_store_id'] = $store_id;
        }
        $check->close();
    }
}

$redirect_url = '/modules/sales/pos.php';
$previous_url = $_SERVER['HTTP_REFERER'] ?? '';
if ($previous_url !== '' && !preg_match('/[\r\n]/', $previous_url)) {
    $previous_host = parse_url($previous_url, PHP_URL_HOST);
    $current_host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
    if (is_string($previous_host) && strcasecmp($previous_host, $current_host) === 0) {
        $redirect_url = $previous_url;
    }
}

header('Location: ' . $redirect_url, true, 303);
exit();
