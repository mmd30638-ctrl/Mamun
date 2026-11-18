<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/invoice_functions.php';

require_post();
csrf_verify();

$allowed = (function_exists('ame_is_admin_like') && ame_is_admin_like())
    || (isset($_SESSION['role']) && in_array(strtolower((string)$_SESSION['role']), ['admin', 'superadmin', 'super_admin', 'owner'], true))
    || (isset($db) && $db instanceof mysqli && function_exists('has_permission') && has_permission($db, 'm-settings'));
if (!$allowed) {
    http_response_code(403);
    die('অনুমতি নেই।');
}

$raw = (string)($_POST['pos_discount_auto'] ?? '');
if ($raw !== '0' && $raw !== '1') {
    header('Location: /index.php');
    exit;
}
$on = $raw === '1' ? 1 : 0;
ensure_invoice_settings_schema($db);
$stmt = $db->prepare('UPDATE invoice_settings SET pos_discount_auto = ?, pos_discount_percent = IF(? = 1, 12, pos_discount_percent) WHERE id = 1');
if (!$stmt) {
    http_response_code(500);
    die('সেটিং সেভ হয়নি।');
}
$stmt->bind_param('ii', $on, $on);
if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    die('সেটিং সেভ হয়নি।');
}
$stmt->close();

if (function_exists('log_activity')) {
    log_activity($on ? 'Percent discount on' : 'Percent discount off', 'Settings', 'pos_discount_auto=' . $on);
}

if ($on === 1) {
    header('Location: /modules/sales/pos.php');
    exit;
}

$back = '/index.php';
$ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
$parts = parse_url($ref);
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$refHost = strtolower((string)($parts['host'] ?? ''));
$path = (string)($parts['path'] ?? '');
if ($path !== '' && str_starts_with($path, '/') && !str_contains($path, '//') && ($refHost === '' || $refHost === $host)) {
    $back = $path . (isset($parts['query']) ? ('?' . $parts['query']) : '');
}
header('Location: ' . $back);
exit;
