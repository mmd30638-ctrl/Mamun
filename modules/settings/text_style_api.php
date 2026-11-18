<?php
require_once '../../includes/config.php';
require_once '../../includes/page_layout.php';
require_once '../../includes/page_text.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$db = $GLOBALS['db'] ?? null;
if (!ame_page_layout_can_edit($db instanceof mysqli ? $db : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'অনুমতি নেই']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'data' => ame_page_text_load()], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$action = (string) ($payload['action'] ?? 'save');

if ($action === 'save') {
    $current = ame_page_text_load();
    if (isset($payload['pages']) && is_array($payload['pages'])) {
        $clean = [];
        foreach ($payload['pages'] as $key => $page) {
            $key = strtolower(trim((string) $key));
            if (!preg_match('/^[a-z0-9_\/-]{1,80}$/', $key) || !is_array($page)) {
                continue;
            }
            $item = ['scale' => ame_page_text_clamp_scale($page['scale'] ?? 1)];
            $color = (string) ($page['color'] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
                $item['color'] = $color;
            }
            $clean[$key] = $item;
        }
        $current['pages'] = $clean;
    }
    $ok = ame_page_text_save($current);
    echo json_encode([
        'ok' => $ok,
        'data' => $current,
        'css' => ame_page_text_css($current),
        'message' => $ok ? 'লেখার সেটিং সংরক্ষিত হয়েছে' : 'সংরক্ষণ ব্যর্থ',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'reset') {
    $ok = ame_page_text_save(ame_page_text_defaults());
    echo json_encode(['ok' => $ok, 'data' => ame_page_text_defaults(), 'message' => 'ডিফল্টে ফেরানো হয়েছে']);
    exit;
}

if ($action === 'reset_page') {
    $current = ame_page_text_load();
    $key = strtolower(trim((string) ($payload['pageKey'] ?? '')));
    if (preg_match('/^[a-z0-9_\/-]{1,80}$/', $key)) {
        unset($current['pages'][$key]);
    }
    $ok = ame_page_text_save($current);
    echo json_encode(['ok' => $ok, 'data' => $current, 'message' => 'এই পেজ ডিফল্টে ফেরানো হয়েছে']);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'অজানা অনুরোধ']);
