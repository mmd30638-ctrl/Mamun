<?php
/* 10.98.790 */
require_once '../../includes/config.php';
require_once '../../includes/attendance_functions.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/staff_duty.php';
require_once '../../includes/worker_desk.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'err' => 'POST লাগবে']);
    exit;
}

$session_token = (string) ($_SESSION['csrf_token'] ?? '');
$posted = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($session_token === '' || $posted === '' || !hash_equals($session_token, $posted)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'নিরাপত্তা যাচাই ব্যর্থ']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'err' => 'লগইন করুন']);
    exit;
}

$employee_id = (int) ($_POST['employee_id'] ?? 0);
$engineer_id = (int) ($_POST['engineer_id'] ?? 0);
$user_id = (int) ($_POST['user_id'] ?? 0);
$source = trim((string) ($_POST['source'] ?? 'pos_icon'));
if (!in_array($source, ['pos_icon', 'workshop', 'attendance_page', 'header_icon'], true)) {
    $source = 'header_icon';
}

if ($employee_id <= 0 && $user_id > 0 && function_exists('ensure_employee_for_user')) {
    $employee_id = (int) ensure_employee_for_user($db, $user_id);
}
if ($employee_id <= 0 && $engineer_id > 0) {
    $find = @$db->prepare('SELECT employee_id FROM engineers WHERE id = ? LIMIT 1');
    if ($find) {
        $find->bind_param('i', $engineer_id);
        $find->execute();
        $row = $find->get_result()->fetch_assoc();
        $find->close();
        $employee_id = (int) ($row['employee_id'] ?? 0);
    }
}

if (!ame_staff_duty_can_wake($db, $employee_id, $user_id)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'আগে নিজে ডিউটিতে অন থাকুন, তারপর অন্য ইউজারকে অন করতে পারবেন।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$woke = ame_staff_wake($db, $employee_id, $engineer_id, $source);
if (!$woke) {
    echo json_encode(['ok' => false, 'err' => 'কর্মী পাওয়া যায়নি']);
    exit;
}

echo json_encode([
    'ok' => true,
    'present' => true,
    'employee_id' => (int) $woke['employee_id'],
    'engineer_id' => (int) $woke['engineer_id'],
    'user_id' => $user_id,
    'name' => (string) $woke['name'],
    'activated_at' => (string) ($woke['activated_at'] ?? ''),
    'already' => !empty($woke['already']),
], JSON_UNESCAPED_UNICODE);
