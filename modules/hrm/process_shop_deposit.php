<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';
ensure_hrm_schema($db);
require_post();
csrf_verify();

$emp_id = (int) ($_POST['employee_id'] ?? 0);
$account_id = (int) ($_POST['account_id'] ?? 0);
$amount = round((float) ($_POST['amount'] ?? 0), 2);
$kind = (string) ($_POST['kind'] ?? 'deposit');
if ($kind !== 'withdraw') {
    $kind = 'deposit';
}
$_SESSION['ame_shop_deposit_account_id'] = $account_id;

$go = static function (array $q) use ($emp_id): void {
    $q['id'] = $emp_id;
    header('Location: employee_list.php?open=deposit&' . http_build_query($q));
    exit;
};

try {
    $res = ame_post_employee_shop_deposit($db, [
        'employee_id' => $emp_id,
        'account_id' => $account_id,
        'amount' => $amount,
        'kind' => $kind,
        'deposit_date' => date('Y-m-d'),
        'note' => trim((string) ($_POST['note'] ?? '')),
        'idempotency_token' => (string) ($_POST['idempotency_token'] ?? ''),
    ]);
    if (function_exists('log_activity')) {
        log_activity(
            $kind === 'deposit' ? 'Worker shop deposit' : 'Worker shop deposit return',
            'HRM',
            'Employee ID ' . $emp_id . ' ৳' . number_format($amount, 2)
        );
    }
    $go([
        'ok' => $kind,
        'amt' => number_format($amount, 2),
        'now' => number_format((float) ($res['current'] ?? 0), 2),
    ]);
} catch (Throwable $e) {
    $go(['err' => $e->getMessage()]);
}
