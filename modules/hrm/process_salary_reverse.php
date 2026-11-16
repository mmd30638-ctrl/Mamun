<?php
/* 10.98.694 */
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/employee_hub.php';
ensure_hrm_schema($db);
require_post();
csrf_verify();
if (!ame_hrm_is_admin() && !(function_exists('ame_emp_hub_can_finance') && ame_emp_hub_can_finance($db))) {
    http_response_code(403);
    die('❌ বেতন মুছা/রিভার্স শুধু হিসাবের অনুমতি থাকলে করা যাবে।');
}
$salary_id = (int)($_POST['salary_id'] ?? 0);
$emp_id = (int)($_POST['employee_id'] ?? 0);
try {
    if ($emp_id <= 0) {
        $peek = $db->prepare('SELECT employee_id FROM salaries WHERE id = ? LIMIT 1');
        $peek->bind_param('i', $salary_id);
        $peek->execute();
        $emp_id = (int)($peek->get_result()->fetch_assoc()['employee_id'] ?? 0);
        $peek->close();
    }
    ame_emp_hub_delete_money($db, 'salary', $salary_id, 0, trim((string)($_POST['reason'] ?? '')));
    header('Location: employee_list.php?open=salary&emp=' . $emp_id);
    exit;
} catch (Throwable $e) {
    die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
