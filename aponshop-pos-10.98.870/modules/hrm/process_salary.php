<?php
require_once '../../includes/config.php';
require_once '../../includes/hrm_functions.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/employee_hub.php';
ensure_hrm_schema($db);
ame_emp_hub_schema($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: employee_list.php');
    exit;
}

$session_token = $_SESSION['csrf_token'] ?? '';
$posted_token = $_POST['csrf_token'] ?? '';
if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
    die('❌ নিরাপত্তা যাচাই ব্যর্থ হয়েছে। <a href="pay_salary.php">ফিরে যান</a>');
}
if (!ame_emp_hub_can_finance($db)) {
    http_response_code(403);
    die('❌ বেতন দেওয়ার অনুমতি নেই।');
}

$emp_id = (int)($_POST['employee_id'] ?? 0);
$pay_date = trim((string)($_POST['pay_date'] ?? $_POST['salary_date'] ?? ''));
$month = trim((string)($_POST['month_year'] ?? ''));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pay_date)) {
    $month = substr($pay_date, 0, 7);
} elseif (preg_match('/^\d{4}-\d{2}$/', $month)) {
    $pay_date = ($month === date('Y-m')) ? date('Y-m-d') : ($month . '-01');
} else {
    $pay_date = date('Y-m-d');
    $month = date('Y-m');
}
$acc_id = (int)($_POST['account_id'] ?? 0);

$fail = static function (string $msg) use ($emp_id, $month, $pay_date): void {
    header('Location: employee_list.php?' . http_build_query(array_filter([
        'emp' => $emp_id > 0 ? $emp_id : null,
        'open' => 'salary',
        'month' => $month,
        'd' => $pay_date,
        'err' => $msg,
    ])));
    exit;
};

$emp_stmt = $db->prepare('SELECT * FROM employees WHERE id = ? LIMIT 1');
$emp_stmt->bind_param('i', $emp_id);
$emp_stmt->execute();
$emp = $emp_stmt->get_result()->fetch_assoc();
$emp_stmt->close();
if (!$emp || (($emp['status'] ?? '') !== 'active') || trim((string)($emp['removed_at'] ?? '')) !== '') {
    $fail('সক্রিয় কর্মী পাওয়া যায়নি।');
}
if (is_file('../../includes/staff_duty.php')) {
    require_once '../../includes/staff_duty.php';
}
if (is_file('../../includes/attendance_functions.php')) {
    require_once '../../includes/attendance_functions.php';
}
$today = date('Y-m-d');
$pay_rule = ame_emp_hub_pay_rule_on($db, $emp, $pay_date);
$pay_mode_early = ame_emp_hub_valid_pay_mode((string)$pay_rule['payment_type']);
if ($pay_mode_early === 'daily') {
    $att = function_exists('get_attendance_row') ? get_attendance_row($db, $emp_id, $pay_date) : null;
    $present = $att && in_array((string)($att['status'] ?? ''), ['Present', 'Late'], true);
    if (!$present && $pay_date === $today && function_exists('ame_staff_is_duty_active')) {
        $present = ame_staff_is_duty_active($db, $emp_id);
    }
    if (!$present) {
        $fail('এই তারিখে হাজিরা চালু নেই। দৈনিক বেতন দিতে আগে সেই দিনের হাজিরা চালু করুন।');
    }
}

try {
    $account = ame_require_selectable_account($db, $acc_id);
} catch (Throwable $e) {
    $fail($e->getMessage());
}

$pay_mode = $pay_mode_early;
$days = (int)($_POST['days'] ?? 1);
if ($days < 1) {
    $days = 1;
}
if ($days > 31) {
    $days = 31;
}
if ($pay_mode === 'daily' && $days !== 1) {
    $fail('দৈনিক বেতন একবারে এক তারিখের জন্য দিন। এতে একই দিনের বেতন ডাবল হবে না।');
}
$manual_bonus = round(max(0, (float)($_POST['bonus_amount'] ?? 0)), 2);
$snack_allowance = round(max(0, (float)($_POST['snack_allowance'] ?? 0)), 2);
$allowance_extra = round(max(0,
    (float)($_POST['house_rent'] ?? 0)
    + (float)($_POST['medical'] ?? 0)
    + (float)($_POST['transport'] ?? 0)
    + (float)($_POST['food'] ?? 0)
    + (float)($_POST['overtime'] ?? 0)
    + (float)($_POST['other_allowance'] ?? 0)
    + (float)($_POST['snacks_amount'] ?? 0)
), 2);
$snacks_amount = $allowance_extra;
$other_cut = round(max(0,
    (float)($_POST['deduction_amount'] ?? 0)
    + (float)($_POST['loan_cut'] ?? 0)
    + (float)($_POST['late_cut'] ?? 0)
    + (float)($_POST['leave_cut'] ?? 0)
    + (float)($_POST['fine_cut'] ?? 0)
    + (float)($_POST['other_cut'] ?? 0)
), 2);
$want_adv_cut = ame_hrm_can_advance($db) && !empty($_POST['deduct_advance']);
$asked_adv_cut = round(max(0, (float)($_POST['advance_cut_amount'] ?? 0)), 2);
$pay_method = clean_input($_POST['payment_method'] ?? 'cash');
$acc_name = strtolower((string)($account['account_name'] ?? ''));
if (str_contains($acc_name, 'bank') || str_contains((string)($account['account_name'] ?? ''), 'ব্যাংক')) {
    $pay_method = 'bank';
} elseif (str_contains($acc_name, 'bkash') || str_contains($acc_name, 'nagad') || str_contains((string)($account['account_name'] ?? ''), 'বিকাশ')) {
    $pay_method = 'mobile';
} elseif (str_contains($acc_name, 'cash') || str_contains((string)($account['account_name'] ?? ''), 'ক্যাশ') || str_contains((string)($account['account_name'] ?? ''), 'নগদ')) {
    $pay_method = 'cash';
} else {
    $pay_method = 'other';
}
$reference_no = clean_input($_POST['reference_no'] ?? '');
$notes = clean_input($_POST['notes'] ?? '');
$mode_note = ame_emp_hub_pay_label($pay_mode) . ' · ' . $pay_date;
$notes = trim($mode_note . ' · ' . $pay_method . ($notes !== '' ? ' · ' . $notes : ''));
if (!in_array($pay_method, ['cash', 'bank', 'mobile', 'other'], true)) {
    $pay_method = 'cash';
}

$salary_lock_name = 'salary_' . $emp_id . '_' . (in_array($pay_mode, ['monthly', 'mixed'], true) ? $month : $pay_date);
$salary_lock_name = substr($salary_lock_name, 0, 60);
$salary_lock_stmt = $db->prepare('SELECT GET_LOCK(?, 10) locked');
$salary_lock_stmt->bind_param('s', $salary_lock_name);
$salary_lock_stmt->execute();
$salary_locked = (int)($salary_lock_stmt->get_result()->fetch_assoc()['locked'] ?? 0) === 1;
$salary_lock_stmt->close();
if (!$salary_locked) {
    $fail('বেতনের আরেকটি অনুরোধ চলছে। কয়েক সেকেন্ড পরে আবার চেষ্টা করুন।');
}

ensure_employee_advance_schema($db);
$db->begin_transaction();
try {
    if (in_array($pay_mode, ['monthly', 'mixed'], true)) {
        $dup = $db->prepare("SELECT id FROM salaries WHERE employee_id = ? AND month_year = ? AND (status IS NULL OR status NOT IN ('reversed','cancelled')) LIMIT 1 FOR UPDATE");
        $dup->bind_param('is', $emp_id, $month);
        $dup->execute();
        $dup_row = $dup->get_result()->fetch_assoc();
        $dup->close();
        if ($dup_row) {
            throw new Exception('এই কর্মীর এই মাসের বেতন আগেই দেওয়া আছে।');
        }
    } elseif (!in_array($pay_mode, ['percentage', 'work'], true)) {
        $dup = $db->prepare("SELECT id FROM salaries WHERE employee_id = ? AND payment_date = ? AND (status IS NULL OR status NOT IN ('reversed','cancelled')) LIMIT 1 FOR UPDATE");
        $dup->bind_param('is', $emp_id, $pay_date);
        $dup->execute();
        $dup_row = $dup->get_result()->fetch_assoc();
        $dup->close();
        if ($dup_row) {
            throw new Exception('এই কর্মীর এই তারিখে বেতন আগেই দেওয়া আছে। ভুল হলে এন্ট্রি রিভার্স করে আবার দিন।');
        }
    }

    $pay_scope = (($_POST['pay_scope'] ?? 'today') === 'all') ? 'all' : 'today';
    $only_day = $pay_scope === 'today' || $pay_mode === 'daily';
    $jobPending = ame_emp_hub_pending_job_share($db, $emp, $emp_id, $pay_date, $only_day, true);
    $workPending = [
        'ids' => $jobPending['work_ids'],
        'commission' => (float)$jobPending['manual_share'],
        'bonus' => (float)$jobPending['bonus'],
        'work_value' => (float)$jobPending['work_value'],
    ];
    $snackPending = ame_emp_hub_pending_snack_pay($db, $emp_id, $pay_date, $only_day, true);
    $servicePending = [
        'ids' => $jobPending['commission_ids'],
        'amount' => (float)$jobPending['service_share'],
    ];
    $base_amount = $pay_mode === 'daily'
        ? round((float)($pay_rule['daily_rate'] ?? 0), 2)
        : (in_array($pay_mode, ['monthly', 'mixed'], true)
            ? round((float)($pay_rule['monthly_salary'] ?? $emp['salary'] ?? 0), 2)
            : 0.0);
    if ($pay_scope === 'today' && $pay_mode !== 'daily') {
        $base_amount = 0.0;
    }
    $commission_amount = round((float)$jobPending['share'], 2);
    $snack_due = round((float) ($snackPending['amount'] ?? 0), 2);
    $bonus_amount = round($manual_bonus + $snack_allowance + $workPending['bonus'] + $snack_due, 2);
    if ($snack_due > 0.009) {
        $notes = trim($notes . ' · নাস্তা ৳' . number_format($snack_due, 2));
    }
    if ($snack_allowance > 0.009) {
        $notes = trim($notes . ' · অতিরিক্ত নাস্তা ৳' . number_format($snack_allowance, 2));
    }
    if ($pay_scope === 'today') {
        $notes = trim($notes . ' · শুধু আজকের বেতন');
    }
    $gross = round($base_amount + $commission_amount + $bonus_amount - $snacks_amount - $other_cut, 2);
    if ($gross <= 0.009) {
        throw new Exception('সর্বমোট বেতন শূন্যের বেশি হতে হবে।');
    }

    $draft_settled = 0.0;
    $draft_action = (string)($_POST['draft_action'] ?? '');
    if ($draft_action === 'settle' || $draft_action === 'post') {
        if (!ame_hrm_can_advance($db)) {
            throw new Exception('সকালের ড্রাফট বন্ধ করতে অগ্রিমের অনুমতি লাগে।');
        }
        $closed = ame_close_employee_advance_drafts($db, $emp_id, $draft_action, $acc_id, [
            'in_transaction' => true,
            'cap' => $gross,
        ]);
        if ($draft_action === 'settle') {
            $draft_settled = round((float)($closed['settled'] ?? 0), 2);
            if ($draft_settled > 0.009) {
                $notes = trim($notes . ' · সকালের ড্রাফট সমন্বয় ৳' . number_format($draft_settled, 2));
            }
        }
    }

    $advance_due = ame_employee_advance_balance($db, $emp_id);
    $loan_cut = 0.0;
    if ($want_adv_cut && $advance_due > 0.009) {
        $cap = round(min($advance_due, max(0, $gross)), 2);
        $loan_cut = $asked_adv_cut > 0.009 ? round(min($asked_adv_cut, $cap), 2) : $cap;
    }
    $draft_use = 0.0;
    if ($draft_settled > 0.009) {
        $room = round(max(0, $gross - $loan_cut), 2);
        $draft_use = round(min($draft_settled, $room), 2);
    }
    $adv_cut = round($loan_cut + $draft_use, 2);
    $cash = round(max(0, $gross - $adv_cut), 2);
    $deduction_amount = round($other_cut + $snacks_amount + $adv_cut, 2);

    $status = 'paid';
    $pay_rule_id = (int)($pay_rule['id'] ?? 0);
    $stmt = $db->prepare(
        "INSERT INTO salaries (employee_id, amount, month_year, account_id, payment_date, base_amount, bonus_amount, deduction_amount, snacks_amount, commission_amount, payment_method, reference_no, notes, status, advance_cut, pay_mode_snapshot, pay_rule_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("idsisdddddssssdsi", $emp_id, $cash, $month, $acc_id, $pay_date, $base_amount, $bonus_amount, $deduction_amount, $snacks_amount, $commission_amount, $pay_method, $reference_no, $notes, $status, $adv_cut, $pay_mode, $pay_rule_id);
    $stmt->execute();
    $salary_id = (int)$db->insert_id;

    if ($cash > 0.009) {
        account_debit(
            $acc_id,
            $cash,
            'salary_payment',
            $salary_id,
            "বেতন প্রদান: {$emp['name']} · $month (মূল: $base_amount, কমিশন: $commission_amount, বোনাস: $bonus_amount, ভাতা: $snacks_amount, কর্তন: $other_cut, অগ্রিম কর্তন: $adv_cut)"
        );
    }

    if ($loan_cut > 0.009) {
        ame_post_employee_advance($db, [
            'employee_id' => $emp_id,
            'account_id' => $acc_id,
            'amount' => $loan_cut,
            'kind' => 'salary_cut',
            'advance_date' => $pay_date,
            'note' => 'বেতন থেকে অগ্রিম কর্তন',
            'in_transaction' => true,
        ]);
    }

    ame_emp_hub_mark_work_paid($db, $workPending['ids'], $salary_id);
    ame_emp_hub_mark_snack_paid($db, $snackPending['ids'] ?? [], $salary_id);

    if ($servicePending['ids'] !== []) {
        $serviceIds = implode(',', array_map('intval', $servicePending['ids']));
        $markCommission = $db->prepare(
            "UPDATE service_commissions SET status = 'paid', salary_id = ?
             WHERE status = 'pending' AND id IN ({$serviceIds})"
        );
        if ($markCommission) {
            $markCommission->bind_param('i', $salary_id);
            $markCommission->execute();
            $markCommission->close();
        }
        if (function_exists('ame_earnings_sync_legacy_paid')) {
            ame_earnings_sync_legacy_paid($db, $emp_id);
        }
    }

    $db->commit();
    $release = $db->prepare('SELECT RELEASE_LOCK(?)');
    $release->bind_param('s', $salary_lock_name);
    $release->execute();
    $release->close();
    log_activity('Salary Paid', 'HRM', 'Employee ID: ' . $emp_id . ' cash ' . $cash . ' advance-cut ' . $adv_cut . ' (' . $pay_date . ')');
    header('Location: employee_list.php?open=salary&emp=' . (int)$emp_id);
    exit;
} catch (Throwable $e) {
    $db->rollback();
    $release = $db->prepare('SELECT RELEASE_LOCK(?)');
    if ($release) {
        $release->bind_param('s', $salary_lock_name);
        $release->execute();
        $release->close();
    }
    $fail($e->getMessage());
}
