<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/invoice_functions.php';

require_post();
csrf_verify();

$allowed = (function_exists('ame_is_admin_like') && ame_is_admin_like())
    || (isset($_SESSION['role']) && in_array(strtolower((string)$_SESSION['role']), ['admin', 'superadmin', 'super_admin', 'owner'], true))
    || (isset($db) && $db instanceof mysqli && function_exists('has_permission') && has_permission($db, 'm-settings'));
header('Content-Type: application/json; charset=utf-8');
if (!$allowed) {
    http_response_code(403);
    echo '{"ok":false}';
    exit;
}

$pct = round((float)($_POST['pos_discount_percent'] ?? 0), 2);
if ($pct < 0) {
    $pct = 0;
}
if ($pct > 100) {
    $pct = 100;
}
ensure_invoice_settings_schema($db);
$stmt = $db->prepare('UPDATE invoice_settings SET pos_discount_percent = ? WHERE id = 1');
if (!$stmt || !$stmt->bind_param('d', $pct) || !$stmt->execute()) {
    if ($stmt) {
        $stmt->close();
    }
    http_response_code(500);
    echo '{"ok":false}';
    exit;
}
$stmt->close();
echo '{"ok":true}';
