<?php
require_once '../../includes/config.php';
require_once '../../includes/attendance_functions.php';
require_once '../../includes/staff_duty.php';

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
$user_id = (int) ($_POST['user_id'] ?? 0);
if ($employee_id <= 0 && $user_id > 0 && function_exists('ensure_employee_for_user')) {
    require_once '../../includes/hrm_functions.php';
    $employee_id = (int) ensure_employee_for_user($db, $user_id);
}

if (!ame_staff_duty_can_sleep($db, $employee_id, $user_id)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'এই ডিউটি অফ করার অনুমতি নেই।'], JSON_UNESCAPED_UNICODE);
    exit;
}

$slept = ame_staff_sleep($db, $employee_id, 'manual_off');
if (!$slept) {
    echo json_encode(['ok' => false, 'err' => 'কর্মী পাওয়া যায়নি']);
    exit;
}

echo json_encode([
    'ok' => true,
    'present' => false,
    'employee_id' => (int) $slept['employee_id'],
    'user_id' => $user_id,
    'name' => (string) $slept['name'],
    'already' => !empty($slept['already']),
], JSON_UNESCAPED_UNICODE);
