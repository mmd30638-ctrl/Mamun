<?php
require_once '../../includes/config.php';
require_once '../../includes/visual_design.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($db) || !($db instanceof mysqli) || !vd_can_edit($db)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => t('vd_forbidden')], JSON_UNESCAPED_UNICODE);
    exit;
}

require_post();
csrf_verify();

$action = (string)($_POST['action'] ?? '');
$pageKey = vd_valid_page_key((string)($_POST['page_key'] ?? ''));
if ($pageKey === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => t('vd_failed')], JSON_UNESCAPED_UNICODE);
    exit;
}
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    if ($action === 'save') {
        $scope = vd_normalize_scope((string)($_POST['scope'] ?? 'page'));
        $device = vd_normalize_device((string)($_POST['device'] ?? 'all'));
        $raw = (string)($_POST['changes'] ?? '');
        $changes = json_decode($raw, true);
        if (!is_array($changes)) {
            throw new RuntimeException('changes');
        }
        if (count($changes) > 80) {
            throw new RuntimeException('changes');
        }
        $saved = 0;
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $element = (string)($change['element'] ?? '');
            $property = (string)($change['property'] ?? '');
            $value = trim((string)($change['value'] ?? ''));
            if ($value === '') {
                vd_delete_one($db, $scope, $pageKey, $element, $device, $property);
                $saved++;
                continue;
            }
            if (vd_upsert($db, $scope, $pageKey, $element, $device, $property, $value, $userId)) {
                $saved++;
            }
        }
        if (function_exists('log_activity')) {
            try {
                log_activity('visual-design', 'settings', $scope . ' ' . $pageKey . ' ' . $saved);
            } catch (Throwable $e) {
            }
        }
        echo json_encode(['ok' => true, 'saved' => $saved, 'styles' => vd_rows($db, $pageKey)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reset') {
        $mode = (string)($_POST['mode'] ?? '');
        if (!in_array($mode, ['element', 'global-element', 'page', 'all'], true)) {
            throw new RuntimeException('mode');
        }
        vd_reset($db, $mode, $pageKey, (string)($_POST['element'] ?? ''));
        if (function_exists('log_activity')) {
            try {
                log_activity('visual-design', 'settings', 'reset ' . $mode . ' ' . $pageKey);
            } catch (Throwable $e) {
            }
        }
        echo json_encode(['ok' => true, 'styles' => vd_rows($db, $pageKey)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => t('vd_failed')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('visual design api: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => t('vd_failed')], JSON_UNESCAPED_UNICODE);
}
