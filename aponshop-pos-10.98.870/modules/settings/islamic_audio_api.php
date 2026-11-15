<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/islamic_audio.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id']) || !ame_islamic_audio_can_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'অ্যাডমিন অনুমতি লাগবে।'], JSON_UNESCAPED_UNICODE);
    exit;
}

ame_islamic_audio_schema($db);

$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'list');
$isPost = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';

$out = static function (array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

$needPost = static function () use ($isPost, $out): void {
    if (!$isPost) {
        $out(['ok' => false, 'error' => 'POST দিন।'], 405);
    }
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf_token']) || $token === '' || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        $out(['ok' => false, 'error' => 'CSRF যাচাই ব্যর্থ। পেজ রিফ্রেশ করুন।'], 403);
    }
};

try {
    if ($action === 'list') {
        $out(['ok' => true, 'tracks' => ame_islamic_audio_admin_list($db), 'categories' => ame_islamic_audio_categories()]);
    }

    if ($action === 'upload') {
        $needPost();
        @set_time_limit(300);
        $file = $_FILES['audio'] ?? $_FILES['audio_file'] ?? [];
        $meta = [
            'title' => (string)($_POST['title'] ?? ''),
            'speaker' => (string)($_POST['speaker'] ?? ''),
            'category' => (string)($_POST['category'] ?? 'general'),
            'is_published' => !isset($_POST['is_published']) || (string)$_POST['is_published'] !== '0',
        ];
        if (isset($_POST['display_serial']) && (string)$_POST['display_serial'] !== '') {
            $meta['display_serial'] = (int)$_POST['display_serial'];
        }
        $out(ame_islamic_audio_ingest_upload($db, $file, $meta) + ['tracks' => ame_islamic_audio_admin_list($db)]);
    }

    if ($action === 'import_url') {
        $needPost();
        @set_time_limit(300);
        $meta = [
            'audio_url' => (string)($_POST['audio_url'] ?? $_POST['url'] ?? ''),
            'title' => (string)($_POST['title'] ?? ''),
            'speaker' => (string)($_POST['speaker'] ?? ''),
            'category' => (string)($_POST['category'] ?? 'general'),
            'is_published' => !isset($_POST['is_published']) || (string)$_POST['is_published'] !== '0',
        ];
        $out(ame_islamic_audio_ingest_url($db, $meta) + ['tracks' => ame_islamic_audio_admin_list($db)]);
    }

    if ($action === 'update') {
        $needPost();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $out(['ok' => false, 'error' => 'আইডি দিন।'], 400);
        }
        $in = $_POST;
        unset($in['action'], $in['csrf_token'], $in['id']);
        $res = ame_islamic_audio_update($db, $id, $in);
        $out($res + ['tracks' => ame_islamic_audio_admin_list($db)]);
    }

    if ($action === 'delete') {
        $needPost();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $out(['ok' => false, 'error' => 'আইডি দিন।'], 400);
        }
        $res = ame_islamic_audio_delete($db, $id);
        $out($res + ['tracks' => ame_islamic_audio_admin_list($db)]);
    }

    if ($action === 'public_list') {
        $out(['ok' => true, 'tracks' => ame_islamic_audio_list_published($db)]);
    }

    $out(['ok' => false, 'error' => 'অজানা অ্যাকশন'], 400);
} catch (InvalidArgumentException $e) {
    $out(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('islamic_audio_api: ' . $e->getMessage());
    $out(['ok' => false, 'error' => $e->getMessage()], 500);
}