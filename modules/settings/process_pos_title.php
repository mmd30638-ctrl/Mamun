<?php
require_once __DIR__ . '/../../includes/config.php';
admin_only();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: pos_title.php');
    exit;
}

$submitted = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || $submitted === '' || !hash_equals((string)$_SESSION['csrf_token'], $submitted)) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json'))) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'নিরাপত্তা যাচাই ব্যর্থ।'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    die('❌ নিরাপত্তা যাচাই ব্যর্থ। <a href="pos_title.php">ফিরে যান</a>');
}

$data = ame_sanitize_pos_title_post($_POST);
ame_save_pos_title($db, $data);
try {
    log_activity('save', 'pos_title_line', $data['line_text']);
} catch (Throwable $e) {
}

$wantsJson = !empty($_POST['ajax'])
    || (!empty($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json'));
if ($wantsJson) {
    $saved = ame_pos_title_row($db);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'line_text' => $saved['line_text'],
        'style' => ame_pos_title_style_attr($saved),
        'message' => 'লেখা সেভ হয়েছে।',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Location: pos_title.php?saved=1');
exit;
