<?php
require_once '../../includes/config.php';
require_once '../../includes/theme_functions.php';
admin_only();
require_post();
csrf_verify();
ensure_theme_schema($db);

$action = (string)($_POST['action'] ?? 'save');
$ajax = (string)($_POST['ajax'] ?? '') === '1'
    || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$moduleKey = ame_theme_normalize_module_key($_POST['module_key'] ?? '');
$redirectSuffix = $moduleKey !== '' ? ('&module=' . urlencode($moduleKey)) : '';

if ($action === 'delete_module') {
    // শুধু মডিউল-নির্দিষ্ট কাস্টম কালার মোছে — গ্লোবাল কালার অক্ষত থাকে
    if ($moduleKey !== '') {
        ame_delete_theme_module_override($db, $moduleKey);
        try { log_activity('theme', 'settings', $moduleKey . ' মডিউলের কাস্টম কালার মুছে ফেলা হয়েছে'); } catch (Throwable $e) {}
    }
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'module' => $moduleKey, 'removed' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: theme.php?saved=1' . $redirectSuffix);
    exit;
}

if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'retired' => true, 'error' => 'visual-design'], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Location: theme.php?retired=1');
exit;
