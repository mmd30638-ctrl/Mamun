<?php
require_once '../../includes/config.php';
require_once '../../includes/clipboard.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$out = static function (array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

if (empty($_SESSION['user_id'])) {
    $out(['ok' => false, 'error' => 'লগইন করুন।'], 401);
}

ame_clip_schema($db);
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'search');

try {
    if ($action === 'search') {
        $kind = (string)($_GET['kind'] ?? 'product');
        $q = (string)($_GET['q'] ?? '');
        $out(['ok' => true, 'rows' => ame_clip_search($db, $kind === 'customer' ? 'customer' : 'product', $q)]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        $out(['ok' => false, 'error' => 'ফর্ম জমা দিন।'], 405);
    }
    $token = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || $token === '' || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        $out(['ok' => false, 'error' => 'নিরাপত্তা যাচাই ব্যর্থ।'], 403);
    }
    if ($action === 'draft') {
        $kind = (string)($_POST['kind'] ?? 'note');
        $ids = json_decode((string)($_POST['ids'] ?? '[]'), true);
        if (!is_array($ids)) {
            $ids = [];
        }
        $payload = ['ids' => array_map('intval', $ids), 'note' => trim((string)($_POST['note'] ?? ''))];
        if ($kind === 'note' && $payload['note'] === '') {
            throw new Exception('নোট লিখুন।');
        }
        if ($kind !== 'note' && !$payload['ids']) {
            throw new Exception('একসাথে কপি করতে অন্তত একটি সারি বাছুন।');
        }
        $id = ame_clip_save_draft($db, $kind, (string)($_POST['title'] ?? ''), $payload);
        $out(['ok' => true, 'id' => $id, 'message' => 'ড্রাফট রাখা হয়েছে।']);
    }
    if ($action === 'post') {
        $draftId = (int)($_POST['draft_id'] ?? 0);
        if ($draftId <= 0) {
            $kind = (string)($_POST['kind'] ?? 'note');
            $ids = json_decode((string)($_POST['ids'] ?? '[]'), true);
            if (!is_array($ids)) {
                $ids = [];
            }
            $payload = ['ids' => array_map('intval', $ids), 'note' => trim((string)($_POST['note'] ?? ''))];
            $draftId = ame_clip_save_draft($db, $kind, (string)($_POST['title'] ?? 'সরাসরি পোস্ট'), $payload);
        }
        $out(ame_clip_post($db, $draftId));
    }
    $out(['ok' => false, 'error' => 'অজানা কাজ।'], 400);
} catch (Throwable $e) {
    $out(['ok' => false, 'error' => $e->getMessage()], 400);
}
