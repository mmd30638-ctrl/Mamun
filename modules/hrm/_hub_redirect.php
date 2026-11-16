<?php
$from = basename(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
$map = [
    'attendance.php' => 'attendance',
    'attendance_history.php' => 'report',
    'pay_salary.php' => 'salary',
    'advance.php' => 'advance',
    'shop_deposit.php' => 'deposit',
    'employee_archive.php' => 'deleted',
    'employee_add.php' => 'new',
    'employee_edit.php' => 'edit',
    'employee_profile.php' => 'more',
    'leave.php' => 'more',
    'duty_report.php' => 'report',
    'work_ledger.php' => 'ledger',
    'worker_share.php' => 'salary',
    'report.php' => 'report',
    'index.php' => '',
    'people.php' => '',
    'earnings.php' => 'ledger',
    'earnings_due.php' => 'ledger',
    'earnings_rules.php' => 'more',
    'earnings_daily.php' => 'work',
    'earnings_me.php' => 'report',
    'worker_statement.php' => 'report',
    'reverse_salary.php' => 'salary',
    'salary_receipt.php' => 'salary',
    'employee_login.php' => 'more',
    'departments.php' => 'more',
    'work_descriptions.php' => 'work',
];
$open = $map[$from] ?? '';
$q = $_GET;
if ($open === 'deleted') {
    $q['archive'] = '1';
} elseif ($open !== '') {
    $q['open'] = $open;
}
if (!empty($q['id']) && empty($q['emp'])) {
    $q['emp'] = $q['id'];
}
$qs = http_build_query($q);
header('Location: /modules/hrm/employee_list.php' . ($qs !== '' ? ('?' . $qs) : ''), true, 302);
exit;
