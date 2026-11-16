<?php
require_once '../../includes/config.php';
require_once '../../includes/page_layout.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$db = $GLOBALS['db'] ?? null;
if (!ame_page_layout_can_edit($db instanceof mysqli ? $db : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'অনুমতি নেই']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'data' => ame_page_layout_load()], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$action = (string) ($payload['action'] ?? 'save');

if ($action === 'toggle_icon') {
    $data = ame_page_layout_load();
    $data['iconOnAllPages'] = !empty($payload['iconOnAllPages']);
    $ok = ame_page_layout_save($data);
    echo json_encode(['ok' => $ok, 'data' => $data, 'message' => $ok ? 'আইকন সেটিং সেভ হয়েছে' : 'সেভ হয়নি']);
    exit;
}

if ($action === 'save' || $action === 'save_layout') {
    $current = ame_page_layout_load();
    if (isset($payload['iconOnAllPages'])) {
        $current['iconOnAllPages'] = !empty($payload['iconOnAllPages']);
    }
    if (isset($payload['front']) && is_array($payload['front'])) {
        $current['front'] = array_merge($current['front'], $payload['front']);
    }
    if (isset($payload['pages']) && is_array($payload['pages'])) {
        $current['pages'] = $payload['pages'];
    } elseif (isset($payload['pageKey'], $payload['page']) && is_string($payload['pageKey']) && is_array($payload['page'])) {
        $key = strtolower(trim($payload['pageKey']));
        if (preg_match('/^[a-z0-9_\/-]{1,80}$/', $key)) {
            $current['pages'][$key] = $payload['page'];
        }
    }
    $ok = ame_page_layout_save($current);
    echo json_encode([
        'ok' => $ok,
        'data' => $current,
        'css' => ame_page_layout_css($current),
        'message' => $ok ? 'লেআউট সংরক্ষিত হয়েছে' : 'সংরক্ষণ ব্যর্থ',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'reset') {
    $ok = ame_page_layout_save(ame_page_layout_defaults());
    echo json_encode(['ok' => $ok, 'data' => ame_page_layout_defaults(), 'message' => 'ডিফল্টে ফেরানো হয়েছে']);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'অজানা অনুরোধ']);
