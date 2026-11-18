<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';
// এখন থেকে এই পেজ admin_only() দিয়ে নয়, config.php-এর ame_enforce_page_permission() → m-hrm গ্রানুলার পারমিশন দিয়ে সুরক্ষিত (মালিকের অনুরোধে — "কর্মী" পারমিশন থাকা অন্যান্য ইউজারও এই পুরো মডিউল ব্যবহার করতে পারবেন)।
ensure_hrm_schema($db);
require_post();
csrf_verify();
if (!ame_hrm_can_advance($db)) {
    http_response_code(403);
    die('❌ বেতন অগ্রিম শুধু অ্যাডমিন, অথবা যাদের আলাদা অগ্রিম অনুমতি আছে। <a href="pay_salary.php">বেতন পাতা</a>');
}

$kind = (string)($_POST['kind'] ?? 'give');
if ($kind !== 'collect') {
    $kind = 'give';
}
$emp_id = (int)($_POST['employee_id'] ?? 0);
$account_id = (int)($_POST['account_id'] ?? 0);
$amount = round((float)($_POST['amount'] ?? 0), 2);
$advance_date = trim((string)($_POST['advance_date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $advance_date)) {
    $advance_date = date('Y-m-d');
}

$_SESSION['ame_advance_account_id'] = $account_id;

$go = static function (array $q) {
    header('Location: employee_list.php?open=advance&' . http_build_query($q));
    exit;
};

try {
    $res = ame_post_employee_advance($db, [
        'employee_id' => $emp_id,
        'account_id' => $account_id,
        'amount' => $amount,
        'kind' => $kind,
        'advance_date' => $advance_date,
        'note' => $kind === 'give' ? 'সকালের অগ্রিম' : 'অগ্রিম ফেরত',
        'idempotency_token' => (string)($_POST['idempotency_token'] ?? ''),
    ]);
    log_activity(
        $kind === 'give' ? 'Worker advance given' : 'Worker advance collected',
        'HRM',
        'Employee ID ' . $emp_id . ' ৳' . number_format($amount, 2)
    );
    $go([
        'ok' => $kind,
        'name' => $res['name'] ?? '',
        'amt' => number_format($amount, 2),
        'prev' => number_format((float)$res['previous'], 2),
        'now' => number_format((float)$res['current'], 2),
        'd' => $advance_date,
    ]);
} catch (Throwable $e) {
    $go(['err' => $e->getMessage(), 'd' => $advance_date]);
}
