<?php
/**
 * Unified worker desk — one profile, own ledger, duty gate.
 * Reads old employees, users, engineers, works and invoices.
 * Does not delete or truncate those rows.
 */

function ame_worker_can_manage(): bool
{
    if (function_exists('ame_is_admin_like') && ame_is_admin_like()) {
        return true;
    }
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    return in_array($role, ['admin', 'superadmin', 'super_admin', 'owner', 'accountant'], true);
}

function ame_worker_self_id(mysqli $db): int
{
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return 0;
    }
    $stmt = @$db->prepare('SELECT employee_id FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['employee_id'] ?? 0);
}

function ame_worker_scope_id(mysqli $db, int $asked): int
{
    if (ame_worker_can_manage()) {
        return max(0, $asked);
    }
    return ame_worker_self_id($db);
}

function ame_worker_table(mysqli $db, string $table): bool
{
    $res = @$db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
    return ($res instanceof mysqli_result) && $res->num_rows > 0;
}

/**
 * Link users and engineers who have no employee row yet.
 * Inserts a profile only when one is missing. Never deletes.
 *
 * @return array{employees_before:int,employees_after:int,users_linked:int,engineers_linked:int}
 */
function ame_worker_seed(mysqli $db): array
{
    static $done = false;
    $empty = ['employees_before' => 0, 'employees_after' => 0, 'users_linked' => 0, 'engineers_linked' => 0];
    if ($done) {
        return $empty;
    }
    $done = true;
    if (!function_exists('ensure_hrm_schema')) {
        require_once __DIR__ . '/hrm_functions.php';
    }
    if (function_exists('ensure_hrm_schema')) {
        ensure_hrm_schema($db);
    }
    $before = 0;
    if (ame_worker_table($db, 'employees')) {
        $count = @$db->query('SELECT COUNT(*) AS c FROM employees');
        $before = (int)($count ? ($count->fetch_assoc()['c'] ?? 0) : 0);
    }
    $usersLinked = 0;
    $engineersLinked = 0;
    if (ame_worker_table($db, 'users') && function_exists('ensure_employee_for_user')) {
        $where = '(employee_id IS NULL OR employee_id = 0)';
        $cols = [];
        $colRes = @$db->query('SHOW COLUMNS FROM users');
        if ($colRes) {
            while ($c = $colRes->fetch_assoc()) {
                $cols[$c['Field']] = true;
            }
        }
        if (isset($cols['removed_at'])) {
            $where .= " AND (removed_at IS NULL OR removed_at = '')";
        }
        if (isset($cols['purged_at'])) {
            $where .= " AND (purged_at IS NULL OR purged_at = '')";
        }
        $staff = @$db->query('SELECT id FROM users WHERE ' . $where);
        if ($staff) {
            while ($row = $staff->fetch_assoc()) {
                $id = ensure_employee_for_user($db, (int)$row['id']);
                if ($id > 0) {
                    $usersLinked++;
                }
            }
        }
    }
    if (ame_worker_table($db, 'engineers') && function_exists('ensure_employee_for_engineer')) {
        $orphans = @$db->query(
            "SELECT id FROM engineers
             WHERE (employee_id IS NULL OR employee_id = 0)
               AND (purged_at IS NULL OR purged_at = '')"
        );
        if ($orphans) {
            while ($row = $orphans->fetch_assoc()) {
                $id = ensure_employee_for_engineer($db, (int)$row['id']);
                if ($id > 0) {
                    $engineersLinked++;
                }
            }
        }
    }
    $after = $before;
    if (ame_worker_table($db, 'employees')) {
        $count = @$db->query('SELECT COUNT(*) AS c FROM employees');
        $after = (int)($count ? ($count->fetch_assoc()['c'] ?? 0) : $before);
    }
    return [
        'employees_before' => $before,
        'employees_after' => $after,
        'users_linked' => $usersLinked,
        'engineers_linked' => $engineersLinked,
    ];
}

function ame_worker_people(mysqli $db): array
{
    $rows = [];
    if (!ame_worker_table($db, 'employees')) {
        return $rows;
    }
    $res = @$db->query(
        "SELECT id, name, employee_code, phone, payment_type
         FROM employees
         WHERE removed_at IS NULL AND purged_at IS NULL
         ORDER BY name ASC, id ASC"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function ame_worker_guard_request(mysqli $db): void
{
    if (PHP_SAPI === 'cli' || ame_worker_can_manage()) {
        return;
    }
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    $gates = [
        '/modules/sales/pos.php',
        '/modules/sales/process_sale.php',
        '/modules/sales/draft_api.php',
        '/modules/workshop/index.php',
        '/modules/workshop/job_new.php',
        '/modules/workshop/job_process.php',
        '/modules/workshop/quote_new.php',
        '/modules/workshop/quote_process.php',
        '/modules/workshop/machine_process.php',
    ];
    $hit = false;
    foreach ($gates as $gate) {
        if (str_contains($script, $gate)) {
            $hit = true;
            break;
        }
    }
    if (!$hit || ame_worker_duty_open($db)) {
        return;
    }
    $msg = 'ডিউটিতে এন্ট্রি না দিলে বিক্রয় বা মেরামত করা যাবে না। মেনু বার থেকে আজকের ডিউটিতে এন্ট্রি দিন। রাত ১২টার পর আবার ছুটি হয়ে যায়।';
    http_response_code(403);
    if (str_contains($script, 'api')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="bn"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ডিউটি লাগবে</title></head><body style="font-family:sans-serif;padding:28px;max-width:520px;margin:auto"><h1>আজ ডিউটিতে নেই</h1><p>' . $safe . '</p><p><a href="/index.php">হোম পেজে যান</a></p></body></html>';
    exit;
}

function ame_worker_duty_open(mysqli $db): bool
{
    if (ame_worker_can_manage()) {
        return true;
    }
    $emp = ame_worker_self_id($db);
    if ($emp <= 0 || !function_exists('ame_staff_is_duty_active')) {
        return false;
    }
    return ame_staff_is_duty_active($db, $emp);
}

function ame_worker_load_hub(): void
{
    if (!function_exists('ame_emp_hub_schema')) {
        require_once __DIR__ . '/employee_hub.php';
    }
}

/**
 * Shop owes the worker when closing is positive.
 * Credit: unpaid-style earnings and money the worker left with the shop.
 * Debit: advance, extra cash, and salary taken.
 *
 * @return array{employee:array,lines:array,closing:float,mode:string,mode_label:string}
 */
function ame_worker_desk_statement(mysqli $db, int $empId): array
{
    ame_worker_load_hub();
    ame_emp_hub_schema($db);
    if (function_exists('ame_emp_hub_ensure_snack_columns')) {
        ame_emp_hub_ensure_snack_columns($db);
    }
    if (function_exists('ame_emp_hub_sync_work_snacks')) {
        ame_emp_hub_sync_work_snacks($db, $empId);
    }
    $emp = ame_emp_hub_row($db, $empId, true);
    if (!$emp) {
        return ['employee' => [], 'lines' => [], 'closing' => 0.0, 'mode' => 'percentage', 'mode_label' => 'পার্সেন্টেজ'];
    }
    $today = function_exists('ame_staff_duty_today') ? ame_staff_duty_today() : date('Y-m-d');
    $rule = ame_emp_hub_pay_rule_on($db, $emp, $today);
    $mode = ame_emp_hub_valid_pay_mode((string)($rule['payment_type'] ?? ''));
    $lines = [];
    $push = static function (string $date, string $desc, float $debit, float $credit) use (&$lines): void {
        if ($debit <= 0.009 && $credit <= 0.009) {
            return;
        }
        $lines[] = [
            'date' => $date,
            'description' => $desc,
            'debit' => round(max(0, $debit), 2),
            'credit' => round(max(0, $credit), 2),
        ];
    };

    if (in_array($mode, ['percentage', 'work', 'mixed'], true) && ame_worker_table($db, 'employee_works')) {
        $wk = @$db->prepare('SELECT work_date, customer_name, vehicle_no, service_type, invoice_ref, employee_amount, bonus_amount FROM employee_works WHERE employee_id = ? ORDER BY work_date ASC, id ASC');
        if ($wk) {
            $wk->bind_param('i', $empId);
            $wk->execute();
            $res = $wk->get_result();
            while ($r = $res->fetch_assoc()) {
                $label = trim((string)($r['service_type'] ?? 'কাজ') . ' · ' . (string)($r['customer_name'] ?? '') . ' ' . (string)($r['vehicle_no'] ?? '') . ' ' . (string)($r['invoice_ref'] ?? ''));
                $push((string)$r['work_date'], trim($label), 0, (float)$r['employee_amount']);
                $push((string)$r['work_date'], 'কাজের বোনাস', 0, (float)($r['bonus_amount'] ?? 0));
            }
            $wk->close();
        }
    }
    if (!function_exists('ame_engineer_statement_sync')) {
        $statementPost = __DIR__ . '/engineer_statement_post.php';
        if (is_file($statementPost)) {
            require_once $statementPost;
        }
    }
    if (function_exists('ame_engineer_statement_sync')) {
        ame_engineer_statement_sync($db, $empId);
    }
    if (ame_worker_table($db, 'service_commissions')) {
        $sc = @$db->prepare('SELECT created_at, service_type, commission_amount, service_amount FROM service_commissions WHERE employee_id = ? ORDER BY id ASC');
        if (!$sc) {
            $sc = @$db->prepare('SELECT created_at, service_type, commission_amount FROM service_commissions WHERE employee_id = ? ORDER BY id ASC');
        }
        if ($sc) {
            $sc->bind_param('i', $empId);
            $sc->execute();
            $res = $sc->get_result();
            while ($r = $res->fetch_assoc()) {
                $serviceAmount = (float)($r['service_amount'] ?? 0);
                $shareAmount = (float)($r['commission_amount'] ?? 0);
                $view = function_exists('ame_engineer_statement_credit')
                    ? ame_engineer_statement_credit($serviceAmount, $shareAmount)
                    : ['show' => $shareAmount > 0.009, 'credit' => $shareAmount, 'label' => 'কাজের ভাগ'];
                if (empty($view['show'])) {
                    continue;
                }
                $push(
                    substr((string)$r['created_at'], 0, 10),
                    (string)$view['label'] . ' · ' . (string)($r['service_type'] ?? ''),
                    0,
                    (float)$view['credit']
                );
            }
            $sc->close();
        }
    }
    if (ame_worker_table($db, 'employee_snacks')) {
        $sn = @$db->prepare("SELECT snack_date, kind, amount, note FROM employee_snacks WHERE employee_id = ? AND amount > 0.009 AND IFNULL(kind,'') <> 'none' ORDER BY snack_date ASC, id ASC");
        if ($sn) {
            $sn->bind_param('i', $empId);
            $sn->execute();
            $res = $sn->get_result();
            while ($r = $res->fetch_assoc()) {
                $kind = (string) ($r['kind'] ?? 'manual');
                $note = trim((string) ($r['note'] ?? ''));
                $label = 'নাস্তা (' . (function_exists('ame_emp_hub_snack_kind_label') ? ame_emp_hub_snack_kind_label($kind) : $kind) . ')';
                if ($note !== '') {
                    $label .= ' · ' . $note;
                }
                $push((string) $r['snack_date'], $label, 0, (float) $r['amount']);
            }
            $sn->close();
        }
    }
    if (ame_worker_table($db, 'employee_works')) {
        $wkSnack = @$db->prepare('SELECT id, work_date, snack FROM employee_works WHERE employee_id = ? AND snack > 0.009 ORDER BY work_date ASC, id ASC');
        if ($wkSnack) {
            $wkSnack->bind_param('i', $empId);
            $wkSnack->execute();
            $res = $wkSnack->get_result();
            $haveNotes = [];
            foreach ($lines as $ln) {
                $haveNotes[(string) ($ln['description'] ?? '')] = true;
            }
            while ($r = $res->fetch_assoc()) {
                $note = 'নাস্তা (অতিরিক্ত) · কাজের নাস্তা #' . (int) $r['id'];
                if (isset($haveNotes[$note])) {
                    continue;
                }
                $push((string) $r['work_date'], $note, 0, (float) $r['snack']);
            }
            $wkSnack->close();
        }
    }

    $months = [];
    if (ame_worker_table($db, 'attendance')) {
        $att = @$db->prepare("SELECT att_date FROM attendance WHERE employee_id = ? AND status IN ('Present','Late') ORDER BY att_date ASC");
        if ($att) {
            $att->bind_param('i', $empId);
            $att->execute();
            $res = $att->get_result();
            while ($r = $res->fetch_assoc()) {
                $day = (string)$r['att_date'];
                $dayRule = ame_emp_hub_pay_rule_on($db, $emp, $day);
                $dayMode = ame_emp_hub_valid_pay_mode((string)($dayRule['payment_type'] ?? ''));
                if ($dayMode === 'daily') {
                    $push($day, 'দৈনিক বেতন', 0, (float)($dayRule['daily_rate'] ?? 0));
                } elseif (in_array($dayMode, ['monthly', 'mixed'], true)) {
                    $months[substr($day, 0, 7)] = (float)($dayRule['monthly_salary'] ?? 0);
                }
            }
            $att->close();
        }
    }
    foreach ($months as $ym => $amount) {
        $push($ym . '-01', 'মাসিক বেতন ' . $ym, 0, $amount);
    }

    if (ame_worker_table($db, 'employee_shop_deposits')) {
        $dep = @$db->prepare('SELECT deposit_date, kind, amount, note FROM employee_shop_deposits WHERE employee_id = ? ORDER BY id ASC');
        if ($dep) {
            $dep->bind_param('i', $empId);
            $dep->execute();
            $res = $dep->get_result();
            while ($r = $res->fetch_assoc()) {
                $note = trim((string)($r['note'] ?? ''));
                if ((string)($r['kind'] ?? '') === 'withdraw') {
                    $push((string)$r['deposit_date'], 'জমা উত্তোলন' . ($note !== '' ? ' · ' . $note : ''), (float)$r['amount'], 0);
                } else {
                    $push((string)$r['deposit_date'], 'কর্মী জমা' . ($note !== '' ? ' · ' . $note : ''), 0, (float)$r['amount']);
                }
            }
            $dep->close();
        }
    }
    if (ame_worker_table($db, 'employee_advances')) {
        $adv = @$db->prepare('SELECT advance_date, kind, amount, note, status FROM employee_advances WHERE employee_id = ? ORDER BY id ASC');
        if (!$adv) {
            $adv = @$db->prepare('SELECT advance_date, kind, amount, note FROM employee_advances WHERE employee_id = ? ORDER BY id ASC');
        }
        if ($adv) {
            $adv->bind_param('i', $empId);
            $adv->execute();
            $res = $adv->get_result();
            while ($r = $res->fetch_assoc()) {
                if ((string)($r['status'] ?? 'posted') === 'draft') {
                    continue;
                }
                $note = trim((string)($r['note'] ?? ''));
                $kind = (string)($r['kind'] ?? '');
                if ($kind === 'salary_cut') {
                    /* 10.98.712 */
                    if (strpos($note, 'বেতনে সমন্বয় (সাবেক অগ্রিম)') !== false) {
                        continue;
                    }
                    $push((string)$r['advance_date'], 'অগ্রিম সমন্বয়' . ($note !== '' ? ' · ' . $note : ''), 0, (float)$r['amount']);
                } else {
                    $push((string)$r['advance_date'], 'অগ্রিম' . ($note !== '' ? ' · ' . $note : ''), (float)$r['amount'], 0);
                }
            }
            $adv->close();
        }
    }
    if (ame_worker_table($db, 'salaries')) {
        $sal = @$db->prepare("SELECT payment_date, month_year, amount, notes FROM salaries WHERE employee_id = ? AND (status IS NULL OR status NOT IN ('reversed','cancelled')) ORDER BY id ASC");
        if ($sal) {
            $sal->bind_param('i', $empId);
            $sal->execute();
            $res = $sal->get_result();
            while ($r = $res->fetch_assoc()) {
                $d = (string)($r['payment_date'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                    $d = (string)($r['month_year'] ?? '') . '-01';
                }
                $push($d, 'বেতন উত্তোলন' . (!empty($r['notes']) ? ' · ' . $r['notes'] : ''), (float)$r['amount'], 0);
            }
            $sal->close();
        }
    }

    if (function_exists('ame_emp_hub_adjust_schema')) {
        ame_emp_hub_adjust_schema($db);
        $adj = @$db->prepare("SELECT adj_date, delta, note FROM employee_money_adjustments WHERE employee_id = ? AND box = 'balance' ORDER BY id ASC");
        if ($adj) {
            $adj->bind_param('i', $empId);
            $adj->execute();
            $res = $adj->get_result();
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $delta = round((float)($r['delta'] ?? 0), 2);
                    $note = trim((string)($r['note'] ?? ''));
                    $label = 'ব্যালেন্স সমন্বয়' . ($note !== '' ? ' · ' . $note : '');
                    if ($delta > 0.009) {
                        $push((string)$r['adj_date'], $label, 0, $delta);
                    } elseif ($delta < -0.009) {
                        $push((string)$r['adj_date'], $label, abs($delta), 0);
                    }
                }
            }
            $adj->close();
        }
    }

    usort($lines, static function ($a, $b) {
        return strcmp((string)$a['date'], (string)$b['date']);
    });
    $bal = 0.0;
    foreach ($lines as &$ln) {
        $bal = round($bal + $ln['credit'] - $ln['debit'], 2);
        $ln['balance'] = $bal;
    }
    unset($ln);
    return [
        'employee' => [
            'id' => $empId,
            'name' => (string)($emp['name'] ?? ''),
            'code' => (string)($emp['employee_code'] ?? ''),
            'phone' => (string)($emp['phone'] ?? ''),
        ],
        'lines' => $lines,
        'closing' => round($bal, 2),
        'mode' => $mode,
        'mode_label' => ame_emp_hub_pay_label($mode),
    ];
}

function ame_worker_history_row_key(array $work): string
{
    if (function_exists('ame_emp_hub_work_fingerprint')) {
        return ame_emp_hub_work_fingerprint($work);
    }
    $invKey = function_exists('ame_emp_hub_work_invoice_key')
        ? ame_emp_hub_work_invoice_key(
            (string)($work['invoice_ref'] ?? ''),
            (string)($work['memo_no'] ?? '')
        )
        : strtoupper(trim((string)($work['invoice_ref'] ?? $work['memo_no'] ?? '')));
    $job = trim((string)($work['service_type'] ?? ''));
    $bill = number_format(round((float)($work['work_value'] ?? 0), 2), 2, '.', '');

    return $invKey . '|' . $job . '|' . $bill;
}

function ame_worker_history(mysqli $db, int $empId, string $from, string $to): array
{
    ame_worker_load_hub();
    ame_emp_hub_schema($db);
    $rows = [];
    $seen = [];
    foreach (ame_emp_hub_works($db, $empId, $from, $to) as $work) {
        $fp = ame_worker_history_row_key($work);
        if (isset($seen[$fp])) {
            continue;
        }
        $seen[$fp] = true;
        $invoice = trim((string)($work['invoice_ref'] ?? $work['memo_no'] ?? ''));
        $row = [
            'date' => (string)($work['work_date'] ?? ''),
            'customer' => (string)($work['customer_name'] ?? ''),
            'vehicle' => trim((string)($work['vehicle_no'] ?? $work['vehicle'] ?? '')),
            'invoice' => $invoice,
            'job' => (string)($work['service_type'] ?? ''),
            'bill' => round((float)($work['work_value'] ?? 0), 2),
            'earn' => round((float)($work['employee_amount'] ?? 0), 2),
        ];
        $rows[] = $row;
    }
    if (!ame_worker_table($db, 'sale_services') || !ame_worker_table($db, 'sales')) {
        return $rows;
    }
    $name = '';
    $emp = ame_emp_hub_row($db, $empId, true);
    if ($emp) {
        $name = trim((string)($emp['name'] ?? ''));
    }
    $sql = "SELECT DATE(s.created_at) AS work_date, s.invoice_number, s.vehicle_no,
                   COALESCE(NULLIF(cu.name,''), NULLIF(s.guest_customer_name,''), '') AS customer_name,
                   ss.service_type, ss.amount, ss.engineer_name
            FROM sale_services ss
            INNER JOIN sales s ON s.id = ss.sale_id
            LEFT JOIN customers cu ON cu.id = s.customer_id
            LEFT JOIN engineers en ON en.id = ss.engineer_id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
              AND (en.employee_id = ? OR ss.engineer_id IN (SELECT id FROM engineers WHERE employee_id = ?) OR (ss.engineer_name <> '' AND ss.engineer_name = ?))
            ORDER BY s.created_at ASC, ss.id ASC";
    $stmt = @$db->prepare($sql);
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('ssiis', $from, $to, $empId, $empId, $name);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $invoice = trim((string)($r['invoice_number'] ?? ''));
                $job = trim((string)($r['service_type'] ?? ''));
                $bill = round((float)($r['amount'] ?? 0), 2);
                $invKey = function_exists('ame_emp_hub_work_invoice_key')
                    ? ame_emp_hub_work_invoice_key($invoice, '')
                    : strtoupper($invoice);
                $key = $invKey . '|' . $job . '|' . number_format($bill, 2, '.', '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $rows[] = [
                    'date' => (string)($r['work_date'] ?? ''),
                    'customer' => (string)($r['customer_name'] ?? ''),
                    'vehicle' => (string)($r['vehicle_no'] ?? ''),
                    'invoice' => $invoice,
                    'job' => $job,
                    'bill' => $bill,
                    'earn' => 0.0,
                ];
            }
        }
    }
    $stmt->close();
    usort($rows, static function ($a, $b) {
        return strcmp((string)$b['date'], (string)$a['date']);
    });
    return $rows;
}

function ame_worker_accounts(mysqli $db): array
{
    if (!function_exists('ame_active_accounts')) {
        require_once __DIR__ . '/account_functions.php';
    }
    return function_exists('ame_active_accounts') ? ame_active_accounts($db) : [];
}

function ame_worker_pay_method(array $account): string
{
    $type = strtolower(trim((string)($account['account_type'] ?? '')));
    if (in_array($type, ['bank', 'mobile'], true)) {
        return $type;
    }
    if ($type === 'cash') {
        return 'cash';
    }
    $name = strtolower((string)($account['account_name'] ?? ''));
    if (str_contains($name, 'bank') || str_contains($name, 'ব্যাংক')) {
        return 'bank';
    }
    if (str_contains($name, 'bkash') || str_contains($name, 'nagad') || str_contains($name, 'বিকাশ') || str_contains($name, 'নগদ')) {
        return 'mobile';
    }
    return 'cash';
}
