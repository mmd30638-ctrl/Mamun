<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/super_admin.php';
require_once __DIR__ . '/../../includes/staff_archive.php';

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'ডাটাবেজ নেই'], JSON_UNESCAPED_UNICODE);
    exit;
}

ame_super_admin_boot($db);
ame_staff_archive_boot($db);

function ame_staff_api_fail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $archivedRows = ame_staff_archive_rows($db, true);
    $purgedRows = ame_staff_purged_rows($db);
    $archivedIds = [];
    foreach ($archivedRows as $row) {
        $archivedIds[] = (int) ($row['id'] ?? 0);
    }
    echo json_encode([
        'ok' => true,
        'ids' => array_values(array_filter($archivedIds)),
        'purged' => ame_staff_purged_ids($db),
        'codes' => ame_staff_codes_of($archivedRows) + ame_staff_codes_of($purgedRows),
        'purgedCodes' => array_values(ame_staff_codes_of($purgedRows)),
        'superAdminUserIds' => ame_super_admin_user_ids($db),
        'canRestore' => ame_is_super_admin(),
        'canArchive' => ame_staff_can_archive($db),
        'canPurge' => ame_is_super_admin(),
        'isSuperAdmin' => ame_is_super_admin(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ame_staff_api_fail('Invalid request method', 405);
}

$session_token = (string) ($_SESSION['csrf_token'] ?? '');
$posted_token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($session_token !== '' && ($posted_token === '' || !hash_equals($session_token, $posted_token))) {
    ame_staff_api_fail('নিরাপত্তা যাচাই ব্যর্থ হয়েছে।', 403);
}

if (!ame_staff_can_archive($db)) {
    ame_staff_api_fail('অনুমতি নেই।', 403);
}

$do = trim((string) ($_POST['do'] ?? 'archive'));
$id = (int) ($_POST['id'] ?? $_POST['employee_id'] ?? 0);
$code = trim((string) ($_POST['code'] ?? $_POST['emp_code'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));
if ($id <= 0 && $code !== '') {
    $id = ame_staff_find_by_code($db, $code);
}

try {
    if ($do === 'archive' || $do === 'remove') {
        $err = ame_staff_archive($db, $id, $note !== '' ? $note : 'প্রজেক্ট থেকে সরানো — কোড ও খাতা রাখা হয়েছে');
        if ($err !== '') {
            ame_staff_api_fail($err);
        }
        echo json_encode([
            'ok' => true,
            'message' => 'কর্মী প্রজেক্ট থেকে সরানো হয়েছে। কোড, বেতন ও হাজিরা খাতা মুছে যায়নি।',
            'ids' => ame_staff_archived_ids($db),
            'purged' => ame_staff_purged_ids($db),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'restore') {
        $err = ame_staff_restore($db, $id);
        if ($err !== '') {
            ame_staff_api_fail($err, 403);
        }
        echo json_encode([
            'ok' => true,
            'message' => 'কর্মী আবার প্রজেক্টে ফিরেছে। কোড আগের মতোই।',
            'ids' => ame_staff_archived_ids($db),
            'purged' => ame_staff_purged_ids($db),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'purge' || $do === 'forever' || $do === 'remove_forever') {
        if (!ame_is_super_admin()) {
            ame_staff_api_fail('চিরতরে সরাতে সুপার অ্যাডমিন লাগবে।', 403);
        }
        $err = ame_staff_purge($db, $id, $code);
        if ($err !== null && $err !== '') {
            ame_staff_api_fail($err, 403);
        }
        echo json_encode([
            'ok' => true,
            'message' => 'কর্মী চিরতরে প্রজেক্ট তালিকা থেকে সরানো হয়েছে। কোড, বেতন ও হাজিরা খাতা মুছে যায়নি।',
            'ids' => ame_staff_archived_ids($db),
            'purged' => ame_staff_purged_ids($db),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ame_staff_api_fail('অজানা কাজ');
} catch (Throwable $e) {
    error_log('staff archive api: ' . $e->getMessage());
    ame_staff_api_fail('কাজ হয়নি। কোনো কোড মুছা হয়নি।', 500);
}
