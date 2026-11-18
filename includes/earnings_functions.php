<?php
/**
 * Worker / Artisan / Service Provider Earnings engine.
 * Existing employees + engineers stay one identity. Old service_commissions remain.
 */

function ame_earnings_worker_types(): array
{
    return [
        'employee' => ame_e('earn_type_employee'),
        'artisan' => ame_e('earn_type_artisan'),
        'daily_worker' => ame_e('earn_type_daily_worker'),
        'service_provider' => ame_e('earn_type_service_provider'),
        'commission' => ame_e('earn_type_commission'),
        'order_based' => ame_e('earn_type_order_based'),
        'monthly_salary' => ame_e('earn_type_monthly_salary'),
        'custom' => ame_e('earn_type_custom'),
    ];
}

function ame_earnings_payment_types(): array
{
    return [
        'percentage' => ame_e('earn_pay_percentage'),
        'daily' => ame_e('earn_pay_daily'),
    ];
}

function ame_earnings_normalize_pay_type(string $type): string
{
    return strtolower(trim($type)) === 'daily' ? 'daily' : 'percentage';
}

function ame_earnings_methods(): array
{
    return [
        'cash' => ame_e('earn_m_cash'),
        'bank' => ame_e('earn_m_bank'),
        'mobile' => ame_e('earn_m_mobile'),
        'other' => ame_e('earn_m_other'),
    ];
}

function ame_earnings_compute(
    string $type,
    float $work_amount,
    float $qty,
    float $days,
    float $percent,
    float $fixed,
    float $daily,
    float $monthly,
    string $fixed_scope = 'per_job'
): array {
    $type = function_exists('ame_earnings_normalize_pay_type')
        ? ame_earnings_normalize_pay_type($type)
        : (strtolower(trim($type)) === 'daily' ? 'daily' : 'percentage');
    if ($type === 'daily') {
        $earning = round($daily * max(0.0, $days), 2);
    } else {
        $earning = round($work_amount * ($percent / 100), 2);
    }
    if ($type === 'daily' && $work_amount <= 0) {
        $company = 0.0;
    } else {
        $company = round($work_amount - $earning, 2);
    }
    return ['earning' => $earning, 'company' => $company];
}

function ame_earnings_rule_label(array $rule): string
{
    $type = function_exists('ame_earnings_normalize_pay_type')
        ? ame_earnings_normalize_pay_type((string)($rule['payment_type'] ?? 'percentage'))
        : ((($rule['payment_type'] ?? '') === 'daily') ? 'daily' : 'percentage');
    if ($type === 'daily') {
        return ame_e('earn_rule_daily') . ' ৳' . number_format((float)($rule['daily_rate'] ?? 0), 2);
    }
    $p = (float)($rule['percent_rate'] ?? 50);
    return rtrim(rtrim(number_format($p, 2), '0'), '.') . '%';
}

function ame_earnings_d(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '—';
    }
    $t = strtotime($date);
    return $t ? date('d/m/Y', $t) : $date;
}

function ame_earnings_m($n): string
{
    return number_format((float)$n, 2) . ' ৳';
}

function ame_earnings_h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function ame_earnings_status_class(string $status): string
{
    $status = strtolower($status);
    $map = [
        'unpaid' => 'st-unpaid',
        'partial' => 'st-partial',
        'partially paid' => 'st-partial',
        'paid' => 'st-paid',
        'reversed' => 'st-reversed',
        'cancelled' => 'st-cancelled',
        'pending_approval' => 'st-pending',
        'posted' => 'st-paid',
    ];
    return $map[$status] ?? 'st-unpaid';
}

function ame_earnings_boot_lang(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $code = function_exists('current_lang') ? (string)current_lang() : 'bn';
    $code = strtolower(substr($code, 0, 2));
    if ($code !== 'en') {
        $code = 'bn';
    }
    $file = __DIR__ . '/lang/earnings_' . $code . '.php';
    if (!is_file($file)) {
        $file = __DIR__ . '/lang/earnings_bn.php';
    }
    if (!is_file($file)) {
        return;
    }
    $extra = include $file;
    if (is_array($extra)) {
        $GLOBALS['__lang_data'] = array_merge($GLOBALS['__lang_data'] ?? [], $extra);
    }
}

function ame_e(string $key): string
{
    ame_earnings_boot_lang();
    if (function_exists('t')) {
        $v = t($key);
        if (is_string($v) && $v !== '' && $v !== $key) {
            return $v;
        }
    }
    $data = $GLOBALS['__lang_data'] ?? [];
    if (isset($data[$key]) && $data[$key] !== '') {
        return (string)$data[$key];
    }
    static $bn = null;
    if ($bn === null) {
        $f = __DIR__ . '/lang/earnings_bn.php';
        $loaded = is_file($f) ? include $f : [];
        $bn = is_array($loaded) ? $loaded : [];
    }
    return (string)($bn[$key] ?? $key);
}

function ame_earnings_status_label(string $status): string
{
    $status = strtolower(trim($status));
    $map = [
        'unpaid' => 'earn_st_unpaid',
        'partial' => 'earn_st_partial',
        'partially paid' => 'earn_st_partial',
        'paid' => 'earn_st_paid',
        'reversed' => 'earn_st_reversed',
        'cancelled' => 'earn_st_cancelled',
        'pending_approval' => 'earn_st_pending',
        'posted' => 'earn_st_posted',
        'active' => 'earn_st_active',
        'inactive' => 'earn_st_inactive',
    ];
    return isset($map[$status]) ? ame_e($map[$status]) : $status;
}

function ame_earnings_role_label(?string $raw): string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '—';
    }
    $key = strtolower($raw);
    $map = [
        'employee' => 'earn_type_employee',
        'artisan' => 'earn_type_artisan',
        'daily_worker' => 'earn_type_daily_worker',
        'service_provider' => 'earn_type_service_provider',
        'commission' => 'earn_type_commission',
        'order_based' => 'earn_type_order_based',
        'monthly_salary' => 'earn_type_monthly_salary',
        'custom' => 'earn_type_custom',
        'manager' => 'earn_role_manager',
        'cashier' => 'earn_role_cashier',
        'accountant' => 'earn_role_accountant',
        'supervisor' => 'earn_role_supervisor',
        'admin' => 'earn_role_admin',
        'engineer' => 'earn_role_engineer',
        'technician' => 'earn_role_technician',
    ];
    return isset($map[$key]) ? ame_e($map[$key]) : $raw;
}

function ame_earnings_method_label(?string $method): string
{
    $method = strtolower(trim((string)$method));
    $all = ame_earnings_methods();
    return $all[$method] ?? ($method !== '' ? $method : '—');
}

function ame_earnings_action_label(?string $action): string
{
    $action = trim((string)$action);
    $key = 'earn_act_' . $action;
    $label = ame_e($key);
    return $label !== $key ? $label : $action;
}

function ame_earnings_month_label(string $from): string
{
    $t = strtotime($from);
    $n = $t ? (int)date('n', $t) : (int)date('n');
    $y = $t ? date('Y', $t) : date('Y');
    return ame_e('earn_mon_' . $n) . ' ' . $y;
}

function ame_earnings_role(): string
{
    return strtolower((string)($_SESSION['role'] ?? ''));
}

function ame_earnings_can(string $cap): bool
{
    $role = ame_earnings_role();
    if ($role === 'admin') {
        return true;
    }
    $map = [
        'view' => ['admin', 'manager', 'cashier', 'accountant', 'supervisor'],
        'pay' => ['admin', 'manager', 'accountant'],
        'rules' => ['admin'],
        'reverse' => ['admin'],
        'settings' => ['admin'],
        'audit' => ['admin', 'manager'],
        'approve' => ['admin', 'manager'],
    ];
    return in_array($role, $map[$cap] ?? [], true);
}

function ame_earnings_self_id(mysqli $db): int
{
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return 0;
    }
    $stmt = $db->prepare('SELECT employee_id FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['employee_id'] ?? 0);
}

function ame_earnings_boot(mysqli $db, string $cap = 'view'): void
{
    ame_earnings_boot_lang();
    if (function_exists('ensure_hrm_schema')) {
        ensure_hrm_schema($db);
    }
    ensure_earnings_schema($db);

    $self = ame_earnings_self_id($db);
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === 'earnings_me.php') {
        if ($self <= 0 && !ame_earnings_can('view')) {
            http_response_code(403);
            die('❌ নিজের আয় দেখতে লগইন একাউন্টের সাথে কর্মী প্রোফাইল লাগবে।');
        }
        return;
    }
    if (!ame_earnings_can($cap)) {
        if ($self > 0 && $cap === 'view') {
            header('Location: /modules/hrm/earnings_me.php');
            exit();
        }
        http_response_code(403);
        die('❌ এই হিসাব দেখার অনুমতি নেই।');
    }
}

function ame_earnings_has_table(mysqli $db, string $name): bool
{
    $esc = $db->real_escape_string($name);
    $res = @$db->query("SHOW TABLES LIKE '{$esc}'");
    return $res && $res->num_rows > 0;
}

function ame_earnings_has_col(mysqli $db, string $table, string $col): bool
{
    $res = @$db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    if (!$res) {
        return false;
    }
    while ($row = $res->fetch_assoc()) {
        if (($row['Field'] ?? '') === $col) {
            return true;
        }
    }
    return false;
}

function ensure_earnings_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (ame_earnings_has_table($db, 'employees')) {
        if (!ame_earnings_has_col($db, 'employees', 'worker_type')) {
            @$db->query("ALTER TABLE employees ADD COLUMN worker_type VARCHAR(40) NOT NULL DEFAULT 'artisan'");
        }
        if (!ame_earnings_has_col($db, 'employees', 'payment_type')) {
            @$db->query("ALTER TABLE employees ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'percentage'");
        }
    }

    $db->query("CREATE TABLE IF NOT EXISTS worker_payment_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        worker_type VARCHAR(40) NOT NULL DEFAULT 'artisan',
        payment_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
        percent_rate DECIMAL(6,2) NOT NULL DEFAULT 50.00,
        fixed_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        fixed_scope VARCHAR(20) NOT NULL DEFAULT 'per_job',
        daily_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
        monthly_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
        custom_note VARCHAR(255) NULL,
        effective_from DATE NOT NULL,
        effective_to DATE NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY employee_id (employee_id),
        KEY effective_from (effective_from)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS worker_earnings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        engineer_id INT NULL,
        source_type VARCHAR(30) NOT NULL DEFAULT 'sale_service',
        source_id INT NULL,
        sale_id INT NULL,
        order_ref VARCHAR(80) NULL,
        customer_name VARCHAR(120) NULL,
        job_label VARCHAR(255) NULL,
        work_date DATE NOT NULL,
        quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
        work_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        payment_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
        rule_label VARCHAR(80) NOT NULL DEFAULT '',
        percent_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
        fixed_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        earning_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        company_share DECIMAL(12,2) NOT NULL DEFAULT 0,
        paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        pay_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
        locked TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY employee_id (employee_id),
        KEY work_date (work_date),
        KEY sale_id (sale_id),
        KEY pay_status (pay_status),
        UNIQUE KEY uniq_source (source_type, source_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS worker_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        method VARCHAR(20) NOT NULL DEFAULT 'cash',
        payment_date DATE NOT NULL,
        account_id INT NULL,
        reference VARCHAR(120) NULL,
        note VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'posted',
        approval_status VARCHAR(20) NOT NULL DEFAULT 'none',
        approved_by INT NULL,
        approved_at DATETIME NULL,
        reverses_id INT NULL,
        reversed_at DATETIME NULL,
        reverse_reason VARCHAR(255) NULL,
        reversed_by INT NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY employee_id (employee_id),
        KEY payment_date (payment_date),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS worker_payment_allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id INT NOT NULL,
        earning_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        reversed TINYINT(1) NOT NULL DEFAULT 0,
        KEY payment_id (payment_id),
        KEY earning_id (earning_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS worker_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        actor_id INT NULL,
        action VARCHAR(80) NOT NULL,
        entity_type VARCHAR(40) NOT NULL,
        entity_id INT NULL,
        employee_id INT NULL,
        old_value TEXT NULL,
        new_value TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY created_at (created_at),
        KEY employee_id (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS worker_earnings_settings (
        setting_key VARCHAR(60) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @$db->query("INSERT IGNORE INTO worker_earnings_settings (setting_key, setting_value) VALUES
        ('pay_direct_max', '5000'),
        ('pay_manager_max', '20000'),
        ('notify_due', '1')");

    ame_earnings_backfill($db);
}

function ame_earnings_setting(mysqli $db, string $key, string $default = ''): string
{
    $stmt = $db->prepare('SELECT setting_value FROM worker_earnings_settings WHERE setting_key = ?');
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (string)$row['setting_value'] : $default;
}

function ame_earnings_set_setting(mysqli $db, string $key, string $value): void
{
    $stmt = $db->prepare(
        'INSERT INTO worker_earnings_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    if ($stmt) {
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_earnings_audit(mysqli $db, string $action, string $entity, $entity_id, $employee_id, $old, $new): void
{
    $actor = (int)($_SESSION['user_id'] ?? 0);
    $eid = $entity_id !== null ? (int)$entity_id : 0;
    $emp = $employee_id !== null ? (int)$employee_id : 0;
    $old_s = is_string($old) ? $old : json_encode($old, JSON_UNESCAPED_UNICODE);
    $new_s = is_string($new) ? $new : json_encode($new, JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare(
        'INSERT INTO worker_audit_log (actor_id, action, entity_type, entity_id, employee_id, old_value, new_value)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('issiiss', $actor, $action, $entity, $eid, $emp, $old_s, $new_s);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_earnings_default_rule_from_employee(array $emp): array
{
    $salary_type = (string)($emp['salary_type'] ?? 'monthly');
    $ptype = function_exists('ame_earnings_normalize_pay_type')
        ? ame_earnings_normalize_pay_type((string)($emp['payment_type'] ?? ''))
        : 'percentage';
    if (($emp['payment_type'] ?? '') === '' && $salary_type === 'daily') {
        $ptype = 'daily';
    }
    return [
        'worker_type' => (string)($emp['worker_type'] ?? 'artisan'),
        'payment_type' => $ptype,
        'percent_rate' => (float)($emp['service_share_percent'] ?? 50),
        'fixed_amount' => 0.0,
        'fixed_scope' => 'per_job',
        'daily_rate' => $ptype === 'daily' ? (float)($emp['salary'] ?? 0) : 0.0,
        'monthly_salary' => 0.0,
        'custom_note' => '',
    ];
}

function ame_earnings_rule_on(mysqli $db, int $employee_id, string $date): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $stmt = $db->prepare(
        'SELECT * FROM worker_payment_rules
         WHERE employee_id = ? AND effective_from <= ?
           AND (effective_to IS NULL OR effective_to >= ?)
         ORDER BY effective_from DESC, id DESC LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('iss', $employee_id, $date, $date);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return $row;
        }
    }
    $emp = null;
    $es = $db->prepare('SELECT * FROM employees WHERE id = ?');
    if ($es) {
        $es->bind_param('i', $employee_id);
        $es->execute();
        $emp = $es->get_result()->fetch_assoc();
        $es->close();
    }
    $rule = ame_earnings_default_rule_from_employee($emp ?: []);
    $rule['employee_id'] = $employee_id;
    $rule['id'] = 0;
    return $rule;
}

function ame_earnings_current_rule(mysqli $db, int $employee_id): array
{
    return ame_earnings_rule_on($db, $employee_id, date('Y-m-d'));
}

function ame_earnings_quote(
    mysqli $db,
    int $employee_id,
    float $work_amount,
    float $qty = 1.0,
    float $days = 1.0,
    string $work_date = ''
): array
{
    $rule = ame_earnings_rule_on($db, $employee_id, $work_date !== '' ? $work_date : date('Y-m-d'));
    $calc = ame_earnings_compute(
        (string)$rule['payment_type'],
        $work_amount,
        $qty,
        $days,
        (float)$rule['percent_rate'],
        (float)$rule['fixed_amount'],
        (float)$rule['daily_rate'],
        (float)$rule['monthly_salary'],
        (string)($rule['fixed_scope'] ?? 'per_job')
    );
    $calc['rule'] = $rule;
    $calc['percent_rate'] = (float)$rule['percent_rate'];
    $calc['payment_type'] = (string)$rule['payment_type'];
    $calc['rule_label'] = ame_earnings_rule_label($rule);
    return $calc;
}

function ame_earnings_save_rule(mysqli $db, int $employee_id, array $rule): void
{
    $today = date('Y-m-d');
    $old = ame_earnings_current_rule($db, $employee_id);
    $actor = (int)($_SESSION['user_id'] ?? 0);
    $wtype = (string)($rule['worker_type'] ?? 'artisan');
    $ptype = function_exists('ame_earnings_normalize_pay_type')
        ? ame_earnings_normalize_pay_type((string)($rule['payment_type'] ?? 'percentage'))
        : ((($rule['payment_type'] ?? '') === 'daily') ? 'daily' : 'percentage');
    $percent = (float)($rule['percent_rate'] ?? 50);
    $fixed = 0.0;
    $scope = 'per_job';
    $daily = (float)($rule['daily_rate'] ?? 0);
    $monthly = 0.0;
    $note = '';

    $same_day = !empty($old['id']) && (($old['effective_from'] ?? '') === $today) && empty($old['effective_to']);
    if ($same_day) {
        $id = (int)$old['id'];
        $sql = 'UPDATE worker_payment_rules SET worker_type=?, payment_type=?, percent_rate=?, fixed_amount=?, fixed_scope=?, daily_rate=?, monthly_salary=?, custom_note=? WHERE id=?';
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ssddsddsi', $wtype, $ptype, $percent, $fixed, $scope, $daily, $monthly, $note, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        if (!empty($old['id'])) {
            $oid = (int)$old['id'];
            $end = date('Y-m-d', strtotime('-1 day'));
            if ($end < (string)$old['effective_from']) {
                $end = $today;
            }
            $cl = $db->prepare('UPDATE worker_payment_rules SET effective_to = ? WHERE id = ? AND effective_to IS NULL');
            $cl->bind_param('si', $end, $oid);
            $cl->execute();
            $cl->close();
        }
        $ins = $db->prepare(
            'INSERT INTO worker_payment_rules
                (employee_id, worker_type, payment_type, percent_rate, fixed_amount, fixed_scope, daily_rate, monthly_salary, custom_note, effective_from, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->bind_param('issddsddssi', $employee_id, $wtype, $ptype, $percent, $fixed, $scope, $daily, $monthly, $note, $today, $actor);
        $ins->execute();
        $ins->close();
    }

    if (ame_earnings_has_col($db, 'employees', 'service_share_percent')) {
        if ($ptype === 'daily') {
            $salary = $daily > 0 ? $daily : (float)($old['daily_rate'] ?? 0);
            $stype = 'daily';
            $up = $db->prepare(
                'UPDATE employees SET worker_type=?, payment_type=?, service_share_percent=?, salary=?, salary_type=? WHERE id=?'
            );
            if ($up) {
                $up->bind_param('ssddsi', $wtype, $ptype, $percent, $salary, $stype, $employee_id);
                $up->execute();
                $up->close();
            }
        } else {
            $stype = 'monthly';
            $up = $db->prepare(
                'UPDATE employees SET worker_type=?, payment_type=?, service_share_percent=?, salary_type=? WHERE id=?'
            );
            if ($up) {
                $up->bind_param('ssdsi', $wtype, $ptype, $percent, $stype, $employee_id);
                $up->execute();
                $up->close();
            }
        }
    }

    ame_earnings_audit($db, 'rule_change', 'worker_payment_rules', $employee_id, $employee_id, $old, $rule);
}

function ame_earnings_lock_row(mysqli $db, array $row): int
{
    $sql = 'INSERT INTO worker_earnings
        (employee_id, engineer_id, source_type, source_id, sale_id, order_ref, customer_name, job_label, work_date, quantity,
         work_amount, payment_type, rule_label, percent_rate, fixed_amount, earning_amount, company_share, paid_amount, pay_status, locked)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $pay_status = (string)($row['pay_status'] ?? 'unpaid');
    $employee_id = (int)$row['employee_id'];
    $engineer_id = (int)($row['engineer_id'] ?? 0);
    $source_type = (string)$row['source_type'];
    $source_id = (int)($row['source_id'] ?? 0);
    $sale_id = (int)($row['sale_id'] ?? 0);
    $order_ref = (string)($row['order_ref'] ?? '');
    $customer_name = (string)($row['customer_name'] ?? '');
    $job_label = (string)($row['job_label'] ?? '');
    $work_date = (string)$row['work_date'];
    $quantity = (float)($row['quantity'] ?? 1);
    $work_amount = (float)$row['work_amount'];
    $payment_type = (string)$row['payment_type'];
    $rule_label = (string)$row['rule_label'];
    $percent_rate = (float)$row['percent_rate'];
    $fixed_amount = (float)$row['fixed_amount'];
    $earning_amount = (float)$row['earning_amount'];
    $company_share = (float)$row['company_share'];
    $paid_amount = (float)$row['paid_amount'];
    $stmt->bind_param(
        'iiisissssddssddddds',
        $employee_id,
        $engineer_id,
        $source_type,
        $source_id,
        $sale_id,
        $order_ref,
        $customer_name,
        $job_label,
        $work_date,
        $quantity,
        $work_amount,
        $payment_type,
        $rule_label,
        $percent_rate,
        $fixed_amount,
        $earning_amount,
        $company_share,
        $paid_amount,
        $pay_status
    );
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    return $id;
}

function ame_earnings_lock_from_commission(mysqli $db, int $commission_id): void
{
    if ($commission_id <= 0) {
        return;
    }
    $stmt = $db->prepare('SELECT * FROM service_commissions WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $commission_id);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$c) {
        return;
    }
    $invoice = '';
    $cust = '';
    if (!empty($c['sale_id']) && ame_earnings_has_table($db, 'sales')) {
        $sid = (int)$c['sale_id'];
        $has_cust_tbl = ame_earnings_has_table($db, 'customers');
        if ($has_cust_tbl) {
            $s = $db->query(
                "SELECT s.invoice_number, COALESCE(NULLIF(c.name, ''), NULLIF(s.guest_customer_name, ''), '') AS customer_name
                 FROM sales s
                 LEFT JOIN customers c ON c.id = s.customer_id
                 WHERE s.id = {$sid}
                 LIMIT 1"
            );
        } else {
            $s = $db->query(
                "SELECT s.invoice_number, COALESCE(s.guest_customer_name, '') AS customer_name
                 FROM sales s
                 WHERE s.id = {$sid}
                 LIMIT 1"
            );
        }
        if ($s && ($sr = $s->fetch_assoc())) {
            $invoice = (string)($sr['invoice_number'] ?? '');
            $cust = (string)($sr['customer_name'] ?? '');
        }
    }
    $work_date = substr((string)($c['created_at'] ?? date('Y-m-d')), 0, 10);
    $paid = ((string)($c['status'] ?? '') === 'paid') ? (float)$c['commission_amount'] : 0.0;
    $status = $paid > 0 ? 'paid' : 'unpaid';
    ame_earnings_lock_row($db, [
        'employee_id' => (int)$c['employee_id'],
        'engineer_id' => (int)($c['engineer_id'] ?? 0),
        'source_type' => 'sale_service',
        'source_id' => $commission_id,
        'sale_id' => (int)($c['sale_id'] ?? 0),
        'order_ref' => $invoice,
        'customer_name' => $cust,
        'job_label' => (string)($c['service_type'] ?? 'Service'),
        'work_date' => $work_date,
        'quantity' => 1.0,
        'work_amount' => (float)$c['service_amount'],
        'payment_type' => 'percentage',
        'rule_label' => rtrim(rtrim(number_format((float)$c['commission_rate'], 2), '0'), '.') . '%',
        'percent_rate' => (float)$c['commission_rate'],
        'fixed_amount' => 0.0,
        'earning_amount' => (float)$c['commission_amount'],
        'company_share' => (float)($c['shop_amount'] ?? 0),
        'paid_amount' => $paid,
        'pay_status' => $status,
    ]);
}

function ame_earnings_backfill(mysqli $db): void
{
    if (ame_earnings_has_table($db, 'employees')) {
        $emps = $db->query('SELECT id FROM employees');
        if ($emps) {
            while ($e = $emps->fetch_assoc()) {
                $id = (int)$e['id'];
                $chk = $db->query('SELECT id FROM worker_payment_rules WHERE employee_id = ' . $id . ' LIMIT 1');
                if ($chk && $chk->num_rows === 0) {
                    $full = $db->query('SELECT * FROM employees WHERE id = ' . $id)->fetch_assoc();
                    $rule = ame_earnings_default_rule_from_employee($full ?: []);
                    $from = $full['joining_date'] ?? date('Y-m-d');
                    if (!$from || $from === '0000-00-00') {
                        $from = date('Y-m-d');
                    }
                    $ins = $db->prepare(
                        'INSERT INTO worker_payment_rules
                            (employee_id, worker_type, payment_type, percent_rate, fixed_amount, fixed_scope, daily_rate, monthly_salary, effective_from)
                         VALUES (?, ?, ?, ?, 0, \'per_job\', ?, ?, ?)'
                    );
                    if ($ins) {
                        $ins->bind_param(
                            'issddds',
                            $id,
                            $rule['worker_type'],
                            $rule['payment_type'],
                            $rule['percent_rate'],
                            $rule['daily_rate'],
                            $rule['monthly_salary'],
                            $from
                        );
                        $ins->execute();
                        $ins->close();
                    }
                }
            }
        }
    }

    if (ame_earnings_has_table($db, 'service_commissions')) {
        $cs = $db->query('SELECT id FROM service_commissions ORDER BY id ASC');
        if ($cs) {
            while ($c = $cs->fetch_assoc()) {
                ame_earnings_lock_from_commission($db, (int)$c['id']);
            }
        }
    }

    if (ame_earnings_has_table($db, 'workshop_jobs')) {
        $wj = $db->query(
            "SELECT j.id, j.job_no, j.customer_name, j.labor_charge, j.engineer_id, j.delivered_at, j.created_at, g.employee_id
             FROM workshop_jobs j
             LEFT JOIN engineers g ON g.id = j.engineer_id
             WHERE j.labor_charge > 0 AND j.engineer_id IS NOT NULL AND j.engineer_id > 0
               AND j.status IN ('delivered','completed','closed')"
        );
        if ($wj) {
            while ($j = $wj->fetch_assoc()) {
                $emp = (int)($j['employee_id'] ?? 0);
                if ($emp <= 0) {
                    continue;
                }
                $amount = (float)$j['labor_charge'];
                $quote = ame_earnings_quote($db, $emp, $amount, 1, 1);
                $wd = substr((string)($j['delivered_at'] ?: $j['created_at'] ?: date('Y-m-d')), 0, 10);
                ame_earnings_lock_row($db, [
                    'employee_id' => $emp,
                    'engineer_id' => (int)$j['engineer_id'],
                    'source_type' => 'workshop',
                    'source_id' => (int)$j['id'],
                    'sale_id' => 0,
                    'order_ref' => (string)$j['job_no'],
                    'customer_name' => (string)$j['customer_name'],
                    'job_label' => 'Workshop labor',
                    'work_date' => $wd,
                    'quantity' => 1.0,
                    'work_amount' => $amount,
                    'payment_type' => $quote['payment_type'],
                    'rule_label' => $quote['rule_label'],
                    'percent_rate' => $quote['percent_rate'],
                    'fixed_amount' => (float)($quote['rule']['fixed_amount'] ?? 0),
                    'earning_amount' => $quote['earning'],
                    'company_share' => $quote['company'],
                    'paid_amount' => 0.0,
                    'pay_status' => 'unpaid',
                ]);
            }
        }
    }
}

function ame_earnings_refresh_status(mysqli $db, int $earning_id): void
{
    $stmt = $db->prepare('SELECT earning_amount, paid_amount FROM worker_earnings WHERE id = ?');
    $stmt->bind_param('i', $earning_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return;
    }
    $earn = (float)$row['earning_amount'];
    $paid = (float)$row['paid_amount'];
    if ($paid <= 0.009) {
        $st = 'unpaid';
    } elseif ($paid + 0.009 >= $earn) {
        $st = 'paid';
        $paid = $earn;
    } else {
        $st = 'partial';
    }
    $up = $db->prepare('UPDATE worker_earnings SET paid_amount = ?, pay_status = ? WHERE id = ? AND locked = 1');
    $up->bind_param('dsi', $paid, $st, $earning_id);
    $up->execute();
    $up->close();
}

function ame_earnings_due(mysqli $db, int $employee_id): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(earning_amount - paid_amount), 0) d
         FROM worker_earnings
         WHERE employee_id = ? AND pay_status IN ('unpaid','partial')"
    );
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $d = (float)$stmt->get_result()->fetch_assoc()['d'];
    $stmt->close();
    return round($d, 2);
}

function ame_earnings_allocate(mysqli $db, int $payment_id, int $employee_id, float $amount): void
{
    $remain = round($amount, 2);
    $sql = "SELECT id, earning_amount, paid_amount
            FROM worker_earnings
            WHERE employee_id = ? AND pay_status IN ('unpaid','partial')
            ORDER BY work_date ASC, id ASC
            FOR UPDATE";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    foreach ($rows as $row) {
        if ($remain <= 0.009) {
            break;
        }
        $open = round((float)$row['earning_amount'] - (float)$row['paid_amount'], 2);
        if ($open <= 0) {
            continue;
        }
        $alloc = min($open, $remain);
        $eid = (int)$row['id'];
        $ins = $db->prepare('INSERT INTO worker_payment_allocations (payment_id, earning_id, amount, reversed) VALUES (?, ?, ?, 0)');
        $ins->bind_param('iid', $payment_id, $eid, $alloc);
        $ins->execute();
        $ins->close();
        $new_paid = round((float)$row['paid_amount'] + $alloc, 2);
        $up = $db->prepare('UPDATE worker_earnings SET paid_amount = ? WHERE id = ?');
        $up->bind_param('di', $new_paid, $eid);
        $up->execute();
        $up->close();
        ame_earnings_refresh_status($db, $eid);
        $remain = round($remain - $alloc, 2);
    }
}

function ame_earnings_mark_commissions_for_payment(mysqli $db, int $employee_id): void
{
    $db->query(
        "UPDATE service_commissions sc
         INNER JOIN worker_earnings we ON we.source_type = 'sale_service' AND we.source_id = sc.id
         SET sc.status = 'paid'
         WHERE sc.employee_id = {$employee_id} AND we.pay_status = 'paid' AND sc.status = 'pending'"
    );
}

function ame_earnings_post_payment(mysqli $db, array $in): int
{
    $employee_id = (int)$in['employee_id'];
    $amount = round((float)$in['amount'], 2);
    $due = ame_earnings_due($db, $employee_id);
    if ($amount <= 0) {
        throw new Exception('amount');
    }
    if ($amount > $due + 0.009) {
        throw new Exception('over');
    }

    $direct = (float)ame_earnings_setting($db, 'pay_direct_max', '5000');
    $mgr = (float)ame_earnings_setting($db, 'pay_manager_max', '20000');
    $role = ame_earnings_role();
    $approval = 'none';
    $status = 'posted';
    if ($role !== 'admin') {
        if ($amount > $mgr) {
            $approval = 'pending';
            $status = 'pending_approval';
        } elseif ($amount > $direct && $role !== 'manager') {
            $approval = 'pending';
            $status = 'pending_approval';
        }
    }

    $method = (string)($in['method'] ?? 'cash');
    if (!isset(ame_earnings_methods()[$method])) {
        $method = 'other';
    }
    $pdate = (string)($in['payment_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pdate)) {
        $pdate = date('Y-m-d');
    }
    $account_id = (int)($in['account_id'] ?? 0);
    $ref = substr(trim((string)($in['reference'] ?? '')), 0, 120);
    $note = substr(trim((string)($in['note'] ?? '')), 0, 255);
    $actor = (int)($_SESSION['user_id'] ?? 0);

    $ins = $db->prepare(
        'INSERT INTO worker_payments
            (employee_id, amount, method, payment_date, account_id, reference, note, status, approval_status, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->bind_param('idssissssi', $employee_id, $amount, $method, $pdate, $account_id, $ref, $note, $status, $approval, $actor);
    $ins->execute();
    $pid = (int)$db->insert_id;
    $ins->close();

    if ($status === 'posted') {
        if ($account_id > 0 && function_exists('account_debit')) {
            $empn = '';
            $en = $db->query('SELECT name FROM employees WHERE id = ' . $employee_id);
            if ($en && ($er = $en->fetch_assoc())) {
                $empn = (string)$er['name'];
            }
            account_debit($account_id, $amount, 'worker_earning', $pid, 'কর্মী আয় পরিশোধ: ' . $empn);
        }
        ame_earnings_allocate($db, $pid, $employee_id, $amount);
        ame_earnings_mark_commissions_for_payment($db, $employee_id);
    }

    ame_earnings_audit($db, $status === 'posted' ? 'payment' : 'payment_pending', 'worker_payments', $pid, $employee_id, null, $in);
    return $pid;
}

function ame_earnings_reverse_payment(mysqli $db, int $payment_id, string $reason): void
{
    $reason = trim($reason);
    if ($reason === '') {
        throw new Exception('reason');
    }
    $stmt = $db->prepare('SELECT * FROM worker_payments WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$p || ($p['status'] ?? '') !== 'posted') {
        throw new Exception('payment');
    }

    $als = $db->prepare('SELECT * FROM worker_payment_allocations WHERE payment_id = ? AND reversed = 0');
    $als->bind_param('i', $payment_id);
    $als->execute();
    $ar = $als->get_result();
    while ($a = $ar->fetch_assoc()) {
        $eid = (int)$a['earning_id'];
        $amt = (float)$a['amount'];
        $db->query('UPDATE worker_earnings SET paid_amount = GREATEST(paid_amount - ' . $amt . ', 0) WHERE id = ' . $eid);
        $aid = (int)$a['id'];
        $db->query('UPDATE worker_payment_allocations SET reversed = 1 WHERE id = ' . $aid);
        ame_earnings_refresh_status($db, $eid);
    }
    $als->close();

    $actor = (int)($_SESSION['user_id'] ?? 0);
    $now = date('Y-m-d H:i:s');
    $up = $db->prepare(
        'UPDATE worker_payments SET status = \'reversed\', reversed_at = ?, reverse_reason = ?, reversed_by = ? WHERE id = ?'
    );
    $up->bind_param('ssii', $now, $reason, $actor, $payment_id);
    $up->execute();
    $up->close();

    $account_id = (int)($p['account_id'] ?? 0);
    $amount = (float)$p['amount'];
    if ($account_id > 0 && $amount > 0 && function_exists('account_credit')) {
        account_credit($account_id, $amount, 'worker_earning_reverse', $payment_id, 'কর্মী পেমেন্ট রিভার্স: ' . $reason);
    }

    $emp = (int)$p['employee_id'];
    $db->query(
        "UPDATE service_commissions sc
         INNER JOIN worker_earnings we ON we.source_type = 'sale_service' AND we.source_id = sc.id
         SET sc.status = 'pending', sc.payout_id = NULL
         WHERE sc.employee_id = {$emp} AND we.pay_status IN ('unpaid','partial')"
    );

    ame_earnings_audit($db, 'payment_reverse', 'worker_payments', $payment_id, $emp, $p, $reason);
}

function ame_earnings_sync_legacy_paid(mysqli $db, int $employee_id): void
{
    $sql = "UPDATE worker_earnings we
            INNER JOIN service_commissions sc ON we.source_type = 'sale_service' AND we.source_id = sc.id
            SET we.paid_amount = we.earning_amount, we.pay_status = 'paid'
            WHERE we.employee_id = ? AND sc.status = 'paid' AND we.pay_status <> 'paid'";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $employee_id);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_earnings_today_kpis(mysqli $db): array
{
    $today = date('Y-m-d');
    $q = function ($sql) use ($db) {
        $r = $db->query($sql);
        return $r ? $r->fetch_assoc() : [];
    };
    $jobs = $q("SELECT COUNT(*) c, COALESCE(SUM(work_amount),0) w, COALESCE(SUM(earning_amount),0) e
                FROM worker_earnings WHERE work_date = '{$today}'");
    $paid = $q("SELECT COALESCE(SUM(amount),0) p FROM worker_payments WHERE payment_date = '{$today}' AND status = 'posted'");
    $due = $q("SELECT COALESCE(SUM(earning_amount - paid_amount),0) d FROM worker_earnings WHERE pay_status IN ('unpaid','partial')");
    $active = $q("SELECT COUNT(*) c FROM employees WHERE status = 'active'");
    $worked = $q("SELECT COUNT(DISTINCT employee_id) c FROM worker_earnings WHERE work_date = '{$today}'");
    return [
        'jobs' => (int)($jobs['c'] ?? 0),
        'sales' => (float)($jobs['w'] ?? 0),
        'earnings' => (float)($jobs['e'] ?? 0),
        'paid' => (float)($paid['p'] ?? 0),
        'due' => (float)($due['d'] ?? 0),
        'active' => (int)($active['c'] ?? 0),
        'worked' => (int)($worked['c'] ?? 0),
        'company' => round((float)($jobs['w'] ?? 0) - (float)($jobs['e'] ?? 0), 2),
    ];
}

function ame_earnings_worker_rows(mysqli $db, string $today, string $q = '', string $ptype = '', string $status = ''): array
{
    $sql = "SELECT e.id, e.name, e.phone, e.employee_code, e.designation, e.status, e.worker_type, e.payment_type,
                   e.service_share_percent, e.photo,
                   COALESCE(t.jobs, 0) AS today_jobs,
                   COALESCE(t.work_value, 0) AS today_work,
                   COALESCE(t.earning, 0) AS today_earn,
                   COALESCE(p.paid_today, 0) AS paid_today,
                   COALESCE(d.due_amt, 0) AS due_amt
            FROM employees e
            LEFT JOIN (
                SELECT employee_id, COUNT(*) jobs, SUM(work_amount) work_value, SUM(earning_amount) earning
                FROM worker_earnings WHERE work_date = ? GROUP BY employee_id
            ) t ON t.employee_id = e.id
            LEFT JOIN (
                SELECT employee_id, SUM(amount) paid_today FROM worker_payments
                WHERE payment_date = ? AND status = 'posted' GROUP BY employee_id
            ) p ON p.employee_id = e.id
            LEFT JOIN (
                SELECT employee_id, SUM(earning_amount - paid_amount) due_amt
                FROM worker_earnings WHERE pay_status IN ('unpaid','partial') GROUP BY employee_id
            ) d ON d.employee_id = e.id
            WHERE 1=1";
    $types = 'ss';
    $params = [$today, $today];
    if ($q !== '') {
        $sql .= ' AND (e.name LIKE ? OR e.phone LIKE ? OR e.employee_code LIKE ?)';
        $like = '%' . $q . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($ptype !== '') {
        $sql .= ' AND e.payment_type = ?';
        $types .= 's';
        $params[] = $ptype;
    }
    if ($status === 'active' || $status === 'inactive') {
        $sql .= ' AND e.status = ?';
        $types .= 's';
        $params[] = $status;
    }
    $sql .= ' ORDER BY e.name ASC';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rule = ame_earnings_current_rule($db, (int)$row['id']);
        $row['rule_label'] = ame_earnings_rule_label($rule);
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function ame_earnings_accounts(mysqli $db): array
{
    $out = [];
    $res = @$db->query("SELECT id, account_name, balance FROM accounts WHERE status='active' ORDER BY account_name ASC");
    if ($res) {
        while ($a = $res->fetch_assoc()) {
            $out[] = $a;
        }
    }
    return $out;
}

function ame_earnings_nav_items(): array
{
    return [
        'dash' => ['earnings.php', 'earn_nav_dash'],
        'workers' => ['earnings_workers.php', 'earn_nav_workers'],
        'daily' => ['earnings_daily.php', 'earn_nav_daily'],
        'pay' => ['earnings_pay.php', 'earn_nav_pay'],
        'due' => ['earnings_due.php', 'earn_nav_due'],
        'rules' => ['earnings_rules.php', 'earn_nav_rules'],
        'reports' => ['earnings_reports.php', 'earn_nav_reports'],
        'audit' => ['earnings_audit.php', 'earn_nav_audit'],
        'settings' => ['earnings_settings.php', 'earn_nav_settings'],
        'me' => ['earnings_me.php', 'earn_nav_me'],
    ];
}

function ame_earnings_rule_from_post(array $post): array
{
    $ptype = ame_earnings_normalize_pay_type((string)($post['payment_type'] ?? 'percentage'));
    $wtype = (string)($post['worker_type'] ?? 'artisan');
    if (!isset(ame_earnings_worker_types()[$wtype])) {
        $wtype = 'artisan';
    }
    $salary = (float)($post['salary'] ?? 0);
    return [
        'worker_type' => $wtype,
        'payment_type' => $ptype,
        'percent_rate' => (float)($post['percent_rate'] ?? $post['service_share_percent'] ?? 50),
        'fixed_amount' => 0.0,
        'fixed_scope' => 'per_job',
        'daily_rate' => (float)($post['daily_rate'] ?? ($ptype === 'daily' ? $salary : 0)),
        'monthly_salary' => 0.0,
        'custom_note' => '',
    ];
}

function ame_earnings_shell_open(string $title, string $sub, string $active): void
{
    global $db;
    ame_earnings_boot_lang();
    $ver = defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.87';
    $due = 0.0;
    if (isset($db) && $db instanceof mysqli) {
        $kpi = ame_earnings_today_kpis($db);
        $due = (float)$kpi['due'];
    }
    echo '<link rel="stylesheet" href="/assets/css/earnings.css?v=' . ame_earnings_h($ver) . '">';
    echo '<div class="earn-shell">';
    echo '<div class="earn-topbar">';
    echo '<div class="earn-brand"><img src="/assets/img/leaf-layout.jpg" alt=""><div><strong>' . ame_earnings_h(ame_e('earn_brand')) . '</strong><span>' . ame_earnings_h(ame_e('earn_brand_sub')) . '</span></div></div>';
    echo '<div class="earn-chip">' . ame_earnings_h(ame_e('earn_chip_due')) . ' ' . ame_earnings_h(ame_earnings_m($due)) . '</div>';
    echo '</div>';
    echo '<section class="earn-hero">';
    echo '<img class="earn-hero-art" src="/assets/img/leaf-layout.jpg" alt="">';
    echo '<div class="earn-hero-copy">';
    echo '<p class="earn-kicker">' . ame_earnings_h(ame_e('earn_kicker')) . '</p>';
    echo '<h1>' . ame_earnings_h($title) . '</h1>';
    echo '<p class="earn-sub">' . ame_earnings_h($sub) . '</p>';
    echo '</div>';
    echo '<aside class="earn-4p" aria-hidden="true"><b>' . ame_earnings_h(ame_e('earn_4p_people')) . '</b><b>' . ame_earnings_h(ame_e('earn_4p_product')) . '</b><b>' . ame_earnings_h(ame_e('earn_4p_planet')) . '</b><b>' . ame_earnings_h(ame_e('earn_4p_profit')) . '</b></aside></section>';
    echo '<nav class="earn-nav">';
    foreach (ame_earnings_nav_items() as $key => $item) {
        if ($key === 'rules' && !ame_earnings_can('rules') && $key !== 'me') {
            continue;
        }
        if ($key === 'audit' && !ame_earnings_can('audit')) {
            continue;
        }
        if ($key === 'settings' && !ame_earnings_can('settings')) {
            continue;
        }
        if ($key === 'pay' && !ame_earnings_can('pay') && $key !== 'me') {
            continue;
        }
        $cls = $active === $key ? 'on' : '';
        echo '<a class="' . $cls . '" href="/modules/hrm/' . ame_earnings_h($item[0]) . '">' . ame_earnings_h(ame_e($item[1])) . '</a>';
    }
    echo '</nav><div class="earn-body">';
}

function ame_earnings_shell_close(): void
{
    echo '</div></div>';
}

function ame_earnings_flash(): void
{
    $ok = (string)($_GET['ok'] ?? '');
    $err = (string)($_GET['err'] ?? '');
    $okm = [
        'paid' => 'পেমেন্ট সংরক্ষণ হয়েছে। বকেয়া আপডেট হয়েছে।',
        'rule' => 'নতুন পেমেন্ট রুল শুধু নতুন কাজের জন্য কার্যকর। পুরোনো হিসাব অপরিবর্তিত।',
        'reversed' => 'পেমেন্ট রিভার্স হয়েছে। মূল রেকর্ড মুছে যায়নি।',
        'settings' => 'অনুমোদন সীমা সেভ হয়েছে।',
        'approved' => 'পেমেন্ট অনুমোদিত ও পোস্ট হয়েছে।',
    ];
    $erm = [
        'csrf' => 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে।',
        'worker' => 'সঠিক কর্মী বেছে নিন।',
        'amount' => 'সঠিক পরিমাণ দিন।',
        'over' => 'বকেয়ার বেশি টাকা দেওয়া যাবে না।',
        'reason' => 'রিভার্সের কারণ আবশ্যক।',
        'payment' => 'এই পেমেন্ট রিভার্স করা যায়নি।',
        'perm' => 'এই কাজের অনুমতি নেই।',
        'balance' => 'অ্যাকাউন্টে পর্যাপ্ত টাকা নেই।',
        'save' => 'সেভ হয়নি। আবার চেষ্টা করুন।',
    ];
    if (isset($okm[$ok])) {
        echo '<div class="earn-flash ok">' . ame_earnings_h($okm[$ok]) . '</div>';
    }
    if (isset($erm[$err])) {
        echo '<div class="earn-flash err">' . ame_earnings_h($erm[$err]) . '</div>';
    }
}
