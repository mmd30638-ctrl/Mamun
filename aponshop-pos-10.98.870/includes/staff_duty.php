<?php
/**
 * Daily staff duty — midnight reset in Asia/Dhaka, wake icon on the desk.
 * Permanent employee.status is not flipped. Absence is a daily attendance row.
 * 10.98.790 — the user who is ON today may turn other unique users ON.
 */

function ame_staff_duty_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('staff_duty')) {
        return;
    }
    if (function_exists('ensure_attendance_schema')) {
        ensure_attendance_schema($db);
    } elseif (is_file(__DIR__ . '/attendance_functions.php')) {
        require_once __DIR__ . '/attendance_functions.php';
        if (function_exists('ensure_attendance_schema')) {
            ensure_attendance_schema($db);
        }
    }
    $att = @$db->query("SHOW TABLES LIKE 'attendance'");
    if ($att && $att->num_rows > 0) {
        $cols = [];
        $res = $db->query('SHOW COLUMNS FROM attendance');
        if ($res) {
            while ($col = $res->fetch_assoc()) {
                $cols[$col['Field']] = true;
            }
        }
        if (!isset($cols['activated_at'])) {
            $db->query('ALTER TABLE attendance ADD COLUMN activated_at DATETIME NULL DEFAULT NULL');
        }
        if (!isset($cols['source'])) {
            $db->query("ALTER TABLE attendance ADD COLUMN source VARCHAR(40) NULL DEFAULT NULL");
        }
    }
    foreach (['employees', 'engineers'] as $table) {
        $have = @$db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
        if (!$have || $have->num_rows === 0) {
            continue;
        }
        $cols = [];
        $res = $db->query('SHOW COLUMNS FROM `' . $table . '`');
        if ($res) {
            while ($col = $res->fetch_assoc()) {
                $cols[$col['Field']] = true;
            }
        }
        if (!isset($cols['purged_at'])) {
            $db->query('ALTER TABLE `' . $table . '` ADD COLUMN purged_at DATETIME NULL DEFAULT NULL');
        }
        if (!isset($cols['removed_at'])) {
            $db->query('ALTER TABLE `' . $table . '` ADD COLUMN removed_at DATETIME NULL DEFAULT NULL');
        }
        if ($table === 'engineers' && !isset($cols['employee_id'])) {
            $db->query('ALTER TABLE `engineers` ADD COLUMN employee_id INT NULL DEFAULT NULL');
        }
    }
    $svc = @$db->query("SHOW TABLES LIKE 'sale_services'");
    if ($svc && $svc->num_rows > 0) {
        $col = @$db->query("SHOW COLUMNS FROM sale_services LIKE 'engineer_name'");
        if ($col && !$col->fetch_assoc()) {
            $db->query('ALTER TABLE sale_services ADD COLUMN engineer_name VARCHAR(150) NULL DEFAULT NULL');
        }
    }
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('staff_duty', true);
    }
}

function ame_staff_duty_set_clock(?DateTimeInterface $when): void
{
    $GLOBALS['ame_staff_duty_clock'] = $when;
}

function ame_staff_duty_now(): DateTime
{
    $pinned = $GLOBALS['ame_staff_duty_clock'] ?? null;
    if ($pinned instanceof DateTimeInterface) {
        $clock = DateTime::createFromInterface($pinned);
        try {
            $clock->setTimezone(new DateTimeZone('Asia/Dhaka'));
        } catch (Throwable $e) {
        }
        return $clock;
    }
    try {
        return new DateTime('now', new DateTimeZone('Asia/Dhaka'));
    } catch (Throwable $e) {
        return new DateTime('now');
    }
}

function ame_staff_duty_today(): string
{
    return ame_staff_duty_now()->format('Y-m-d');
}

function ame_staff_duty_stamp_path(): string
{
    $candidates = [];
    if (defined('AME_PRIVATE_DATA_DIR') && AME_PRIVATE_DATA_DIR !== '') {
        $candidates[] = AME_PRIVATE_DATA_DIR;
    }
    $candidates[] = dirname(__DIR__) . '/storage';
    $candidates[] = sys_get_temp_dir();
    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return rtrim($dir, '/') . '/staff_duty_day.txt';
        }
    }
    return rtrim(sys_get_temp_dir(), '/') . '/staff_duty_day.txt';
}

function ame_staff_is_on_duty(?string $status): bool
{
    return in_array((string)$status, ['Present', 'Late'], true);
}

/**
 * Who may press 🟢. Admin always. Anyone may turn themselves ON.
 * After that, the user who is ON may turn other unique users ON.
 */
function ame_staff_duty_wake_allowed(
    bool $isAdmin,
    bool $actorOnDuty,
    int $actorEmp,
    int $targetEmp,
    bool $targetAlreadyOn,
    int $actorUser = 0,
    int $targetUser = 0
): bool {
    if ($targetAlreadyOn) {
        return false;
    }
    if ($targetEmp <= 0 && $targetUser <= 0) {
        return false;
    }
    if ($isAdmin) {
        return true;
    }
    $self = ($actorEmp > 0 && $targetEmp > 0 && $actorEmp === $targetEmp)
        || ($actorUser > 0 && $targetUser > 0 && $actorUser === $targetUser);
    if ($self) {
        return true;
    }
    return $actorOnDuty;
}

function ame_staff_duty_actor_is_admin(): bool
{
    if (function_exists('ame_worker_can_manage') && ame_worker_can_manage()) {
        return true;
    }
    if (function_exists('ame_is_super_admin') && ame_is_super_admin()) {
        return true;
    }
    if (!empty($_SESSION['is_super_admin'])) {
        return true;
    }
    $role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
    return in_array($role, ['admin', 'superadmin', 'super_admin', 'owner', 'accountant'], true);
}

function ame_staff_duty_actor_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function ame_staff_duty_actor_employee_id(mysqli $db): int
{
    $fromSession = (int) ($_SESSION['employee_id'] ?? 0);
    if ($fromSession > 0) {
        return $fromSession;
    }
    if (function_exists('ame_worker_self_id')) {
        return ame_worker_self_id($db);
    }
    $uid = ame_staff_duty_actor_user_id();
    if ($uid <= 0 || !ame_staff_has_table($db, 'users') || !ame_staff_has_col($db, 'users', 'employee_id')) {
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
    return (int) ($row['employee_id'] ?? 0);
}

/**
 * One row per employee and per login. Same person is not listed twice.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function ame_staff_duty_unique_rows(array $rows): array
{
    $out = [];
    $byEmp = [];
    $byUser = [];
    foreach ($rows as $row) {
        $emp = (int) ($row['employee_id'] ?? 0);
        $uid = (int) ($row['user_id'] ?? 0);
        if ($emp > 0 && isset($byEmp[$emp])) {
            continue;
        }
        if ($uid > 0 && isset($byUser[$uid])) {
            continue;
        }
        if ($emp > 0) {
            $byEmp[$emp] = true;
        }
        if ($uid > 0) {
            $byUser[$uid] = true;
        }
        $out[] = $row;
    }
    return $out;
}

function ame_staff_duty_can_wake(mysqli $db, int $targetEmp, int $targetUser = 0): bool
{
    $actorEmp = ame_staff_duty_actor_employee_id($db);
    $actorOn = $actorEmp > 0 && ame_staff_is_duty_active($db, $actorEmp);
    return ame_staff_duty_wake_allowed(
        ame_staff_duty_actor_is_admin(),
        $actorOn,
        $actorEmp,
        $targetEmp,
        false,
        ame_staff_duty_actor_user_id(),
        $targetUser
    );
}

/**
 * Login accounts that are not already an employee row — one unique user each.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function ame_staff_duty_merge_logins(mysqli $db, array $rows): array
{
    if (!ame_staff_has_table($db, 'users')) {
        return $rows;
    }
    $byEmp = [];
    foreach ($rows as $i => $row) {
        $emp = (int) ($row['employee_id'] ?? 0);
        if ($emp > 0) {
            $byEmp[$emp] = $i;
        }
    }
    $sql = 'SELECT id, username, employee_id';
    if (ame_staff_has_col($db, 'users', 'full_name')) {
        $sql .= ', full_name';
    }
    $sql .= ' FROM users';
    $where = [];
    if (ame_staff_has_col($db, 'users', 'removed_at')) {
        $where[] = 'removed_at IS NULL';
    }
    if (ame_staff_has_col($db, 'users', 'purged_at')) {
        $where[] = 'purged_at IS NULL';
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $res = @$db->query($sql);
    if (!$res) {
        return $rows;
    }
    while ($u = $res->fetch_assoc()) {
        $uid = (int) ($u['id'] ?? 0);
        $emp = (int) ($u['employee_id'] ?? 0);
        $uname = trim((string) ($u['username'] ?? ''));
        $fname = trim((string) ($u['full_name'] ?? ''));
        if ($uid <= 0) {
            continue;
        }
        if ($emp > 0 && isset($byEmp[$emp])) {
            $i = $byEmp[$emp];
            $rows[$i]['user_id'] = $uid;
            continue;
        }
        $rows[] = [
            'id' => 0,
            'name' => $fname !== '' ? $fname : ($uname !== '' ? $uname : ('ইউজার #' . $uid)),
            'emp_code' => $uname,
            'employee_id' => $emp,
            'user_id' => $uid,
            'present' => false,
            'activated_at' => '',
        ];
    }
    return $rows;
}

function ame_staff_close_day(mysqli $db, string $day): int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        return 0;
    }
    ame_staff_duty_schema($db);
    $n = 0;
    $sql = "INSERT INTO attendance (employee_id, att_date, status, source)
            SELECT e.id, ?, 'Absent', 'night_reset'
            FROM employees e
            WHERE e.removed_at IS NULL
              AND (e.purged_at IS NULL)
              AND NOT EXISTS (
                  SELECT 1 FROM attendance a
                  WHERE a.employee_id = e.id AND a.att_date = ?
              )";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $day, $day);
        $stmt->execute();
        $n = (int)$stmt->affected_rows;
        $stmt->close();
    }
    if (function_exists('log_activity') && $n > 0) {
        log_activity('Staff night reset', 'HRM', $day . ' · Absent ' . $n);
    }
    return $n;
}

function ame_staff_nightly_sleep(mysqli $db): int
{
    $yesterday = (clone ame_staff_duty_now())->modify('-1 day')->format('Y-m-d');
    return ame_staff_close_day($db, $yesterday);
}

function ame_staff_morning_stamp_path(): string
{
    return dirname(ame_staff_duty_stamp_path()) . '/staff_duty_morning.txt';
}

function ame_staff_is_morning_leader(string $role, int $isSuper = 0): bool
{
    if ($isSuper === 1) {
        return true;
    }
    $role = strtolower(trim($role));
    return in_array($role, ['manager', 'super_admin', 'superadmin', 'super-admin'], true);
}

function ame_staff_sleep(mysqli $db, int $employee_id, string $source = 'manual_off'): ?array
{
    ame_staff_duty_schema($db);
    if ($employee_id <= 0) {
        return null;
    }
    $chk = @$db->prepare('SELECT id, name FROM employees WHERE id = ? LIMIT 1');
    if (!$chk) {
        return null;
    }
    $chk->bind_param('i', $employee_id);
    $chk->execute();
    $emp = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$emp) {
        return null;
    }
    $today = ame_staff_duty_today();
    $existing = function_exists('get_attendance_row') ? get_attendance_row($db, $employee_id, $today) : null;
    if (!$existing || !ame_staff_is_on_duty((string) ($existing['status'] ?? ''))) {
        return [
            'employee_id' => $employee_id,
            'name' => (string) $emp['name'],
            'present' => false,
            'already' => true,
        ];
    }
    $src = substr(trim($source), 0, 40);
    $up = @$db->prepare("UPDATE attendance SET status = 'Absent', source = ? WHERE employee_id = ? AND att_date = ?");
    if (!$up) {
        return null;
    }
    $up->bind_param('sis', $src, $employee_id, $today);
    $up->execute();
    $up->close();
    if (function_exists('log_activity')) {
        log_activity('Staff duty off', 'HRM', (string) $emp['name'] . ' · ' . $today);
    }
    return [
        'employee_id' => $employee_id,
        'name' => (string) $emp['name'],
        'present' => false,
        'already' => false,
    ];
}

function ame_staff_duty_can_sleep(mysqli $db, int $targetEmp, int $targetUser = 0): bool
{
    if ($targetEmp <= 0 && $targetUser <= 0) {
        return false;
    }
    if (ame_staff_duty_actor_is_admin()) {
        return true;
    }
    $actorEmp = ame_staff_duty_actor_employee_id($db);
    $actorUser = ame_staff_duty_actor_user_id();
    return ($actorEmp > 0 && $targetEmp > 0 && $actorEmp === $targetEmp)
        || ($actorUser > 0 && $targetUser > 0 && $actorUser === $targetUser);
}

function ame_staff_morning_auto_on(mysqli $db): int
{
    $now = ame_staff_duty_now();
    if ((int) $now->format('G') < 9) {
        return 0;
    }
    $today = $now->format('Y-m-d');
    $file = ame_staff_morning_stamp_path();
    $last = is_file($file) ? trim((string) @file_get_contents($file)) : '';
    if ($last === $today) {
        return 0;
    }
    $n = 0;
    if (ame_staff_has_table($db, 'users')) {
        $hasSuper = ame_staff_has_col($db, 'users', 'is_super_admin');
        $hasStatus = ame_staff_has_col($db, 'users', 'status');
        $hasRemoved = ame_staff_has_col($db, 'users', 'removed_at');
        $hasPurged = ame_staff_has_col($db, 'users', 'purged_at');
        $hasEmp = ame_staff_has_col($db, 'users', 'employee_id');
        $sql = 'SELECT id, role';
        $sql .= $hasSuper ? ', is_super_admin' : ', 0 AS is_super_admin';
        $sql .= $hasEmp ? ', employee_id' : ', 0 AS employee_id';
        $sql .= ' FROM users';
        $where = [];
        if ($hasStatus) {
            $where[] = 'status = 1';
        }
        if ($hasRemoved) {
            $where[] = 'removed_at IS NULL';
        }
        if ($hasPurged) {
            $where[] = 'purged_at IS NULL';
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $res = @$db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $role = (string) ($row['role'] ?? '');
                $super = (int) ($row['is_super_admin'] ?? 0);
                if (!ame_staff_is_morning_leader($role, $super)) {
                    continue;
                }
                $emp = (int) ($row['employee_id'] ?? 0);
                $uid = (int) ($row['id'] ?? 0);
                if ($emp <= 0 && $uid > 0) {
                    if (!function_exists('ensure_employee_for_user') && is_file(__DIR__ . '/hrm_functions.php')) {
                        require_once __DIR__ . '/hrm_functions.php';
                    }
                    if (function_exists('ensure_employee_for_user')) {
                        $emp = (int) ensure_employee_for_user($db, $uid);
                    }
                }
                if ($emp <= 0 || ame_staff_is_duty_active($db, $emp)) {
                    continue;
                }
                $woke = ame_staff_wake($db, $emp, 0, 'morning_9am');
                if ($woke && empty($woke['already'])) {
                    $n++;
                }
            }
            $res->free();
        }
    }
    @file_put_contents($file, $today);
    if (function_exists('log_activity') && $n > 0) {
        log_activity('Staff morning auto on', 'HRM', $today . ' · ' . $n);
    }
    return $n;
}

function ame_staff_duty_tick(mysqli $db): void
{
    if (!empty($GLOBALS['ame_staff_duty_tick_ran'])) {
        return;
    }
    $GLOBALS['ame_staff_duty_tick_ran'] = true;
    ame_staff_duty_schema($db);
    $today = ame_staff_duty_today();
    $file = ame_staff_duty_stamp_path();
    $last = is_file($file) ? trim((string)@file_get_contents($file)) : '';
    if ($last !== $today) {
        if (function_exists('ame_worker_seed')) {
            ame_worker_seed($db);
        }
        if ($last !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $last) && $last < $today) {
            $cursor = DateTime::createFromFormat('Y-m-d', $last, new DateTimeZone('Asia/Dhaka'));
            $end = DateTime::createFromFormat('Y-m-d', $today, new DateTimeZone('Asia/Dhaka'));
            if ($cursor && $end) {
                $cursor->modify('+1 day');
                while ($cursor < $end) {
                    ame_staff_close_day($db, $cursor->format('Y-m-d'));
                    $cursor->modify('+1 day');
                }
            }
        }
        @file_put_contents($file, $today);
    }
    ame_staff_morning_auto_on($db);
}

function ame_staff_wake(mysqli $db, int $employee_id = 0, int $engineer_id = 0, string $source = 'pos_icon'): ?array
{
    ame_staff_duty_schema($db);
    if ($employee_id <= 0 && $engineer_id > 0 && function_exists('ensure_employee_for_engineer')) {
        $employee_id = (int)ensure_employee_for_engineer($db, $engineer_id);
    }
    if ($employee_id <= 0 && $engineer_id > 0) {
        $find = $db->prepare('SELECT employee_id, name FROM engineers WHERE id = ? LIMIT 1');
        if ($find) {
            $find->bind_param('i', $engineer_id);
            $find->execute();
            $row = $find->get_result()->fetch_assoc();
            $find->close();
            $employee_id = (int)($row['employee_id'] ?? 0);
            $eng_name = (string)($row['name'] ?? '');
        }
    }
    if ($employee_id <= 0) {
        return null;
    }
    $chk = $db->prepare('SELECT id, name, purged_at, removed_at FROM employees WHERE id = ? LIMIT 1');
    if (!$chk) {
        return null;
    }
    $chk->bind_param('i', $employee_id);
    $chk->execute();
    $emp = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$emp || trim((string)($emp['purged_at'] ?? '')) !== '' || trim((string)($emp['removed_at'] ?? '')) !== '') {
        return null;
    }
    $today = ame_staff_duty_today();
    $now = ame_staff_duty_now()->format('Y-m-d H:i:s');
    $clock = ame_staff_duty_now()->format('H:i:s');
    $existing = function_exists('get_attendance_row') ? get_attendance_row($db, $employee_id, $today) : null;
    if ($existing && ame_staff_is_on_duty((string)($existing['status'] ?? ''))) {
        if ($engineer_id <= 0) {
            $engineer_id = function_exists('ame_staff_engineer_id') ? ame_staff_engineer_id($db, $employee_id) : 0;
        }
        return [
            'employee_id' => $employee_id,
            'engineer_id' => $engineer_id,
            'name' => (string)$emp['name'],
            'present' => true,
            'activated_at' => (string)($existing['activated_at'] ?? $existing['check_in'] ?? $now),
            'already' => true,
        ];
    }
    $status = 'Present';
    $src = substr(trim($source), 0, 40);
    if ($existing) {
        $up = $db->prepare("UPDATE attendance SET status = ?, check_in = COALESCE(check_in, ?), activated_at = COALESCE(activated_at, ?), source = ? WHERE employee_id = ? AND att_date = ?");
        if ($up) {
            $up->bind_param('ssssis', $status, $clock, $now, $src, $employee_id, $today);
            $up->execute();
            $up->close();
        }
    } else {
        $ins = $db->prepare("INSERT INTO attendance (employee_id, att_date, check_in, status, activated_at, source) VALUES (?, ?, ?, ?, ?, ?)");
        if ($ins) {
            $ins->bind_param('isssss', $employee_id, $today, $clock, $status, $now, $src);
            $ins->execute();
            $ins->close();
        }
    }
    if ($engineer_id <= 0 && function_exists('ame_staff_engineer_id')) {
        $engineer_id = ame_staff_engineer_id($db, $employee_id);
    }
    if (function_exists('log_activity')) {
        log_activity('Staff duty wake', 'HRM', (string)$emp['name'] . ' · ' . $today . ' · ' . $now);
    }
    return [
        'employee_id' => $employee_id,
        'engineer_id' => $engineer_id,
        'name' => (string)$emp['name'],
        'present' => true,
        'activated_at' => $now,
        'already' => false,
    ];
}

function ame_staff_duty_roster(mysqli $db): array
{
    static $rosterCache = null;
    if (is_array($rosterCache)) {
        return $rosterCache;
    }
    ame_staff_duty_tick($db);
    ame_staff_duty_schema($db);
    $today = ame_staff_duty_today();
    $rows = [];
    $seenEmp = [];
    $seenEng = [];
    $hasCode = ame_staff_has_col($db, 'employees', 'employee_code');
    $hasRemoved = ame_staff_has_col($db, 'employees', 'removed_at');
    $hasPurged = ame_staff_has_col($db, 'employees', 'purged_at');
    $hasAttendance = ame_staff_has_table($db, 'attendance');
    $select = 'e.id AS employee_id, e.name AS emp_name';
    $select .= $hasCode ? ", COALESCE(e.employee_code, '') AS emp_code" : ", '' AS emp_code";
    $join = '';
    $types = '';
    $params = [];
    if ($hasAttendance) {
        $select .= ', a.status AS att_status';
        if (ame_staff_has_col($db, 'attendance', 'activated_at')) {
            $select .= ', a.activated_at';
        }
        if (ame_staff_has_col($db, 'attendance', 'check_in')) {
            $select .= ', a.check_in';
        }
        $join = ' LEFT JOIN attendance a ON a.employee_id = e.id AND a.att_date = ?';
        $types = 's';
        $params[] = $today;
    }
    $where = [];
    if ($hasRemoved) {
        $where[] = 'e.removed_at IS NULL';
    }
    if ($hasPurged) {
        $where[] = 'e.purged_at IS NULL';
    }
    $sql = 'SELECT ' . $select . ' FROM employees e' . $join;
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY e.name ASC';
    $loaded = false;
    $stmt = @$db->prepare($sql);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res) {
                $loaded = true;
                while ($row = $res->fetch_assoc()) {
                    $empId = (int)($row['employee_id'] ?? 0);
                    if ($empId <= 0 || isset($seenEmp[$empId])) {
                        continue;
                    }
                    $seenEmp[$empId] = true;
                    $rows[] = [
                        'id' => 0,
                        'name' => (string)($row['emp_name'] ?? ''),
                        'emp_code' => (string)($row['emp_code'] ?? ''),
                        'employee_id' => $empId,
                        'present' => ame_staff_is_on_duty((string)($row['att_status'] ?? '')),
                        'activated_at' => (string)($row['activated_at'] ?? $row['check_in'] ?? ''),
                    ];
                }
            }
        }
        $stmt->close();
    }
    if (!$loaded && ame_staff_has_table($db, 'employees')) {
        $plain = @$db->query('SELECT id AS employee_id, name AS emp_name FROM employees ORDER BY name ASC');
        if ($plain) {
            while ($row = $plain->fetch_assoc()) {
                $empId = (int)($row['employee_id'] ?? 0);
                if ($empId <= 0 || isset($seenEmp[$empId])) {
                    continue;
                }
                $seenEmp[$empId] = true;
                $rows[] = [
                    'id' => 0,
                    'name' => (string)($row['emp_name'] ?? ''),
                    'emp_code' => '',
                    'employee_id' => $empId,
                    'present' => false,
                    'activated_at' => '',
                ];
            }
            $plain->free();
        }
    }
    $engByEmp = [];
    if (ame_staff_has_table($db, 'engineers') && ame_staff_has_col($db, 'engineers', 'employee_id')) {
        $engWhere = 'employee_id IS NOT NULL AND employee_id > 0';
        if (ame_staff_has_col($db, 'engineers', 'purged_at')) {
            $engWhere .= ' AND purged_at IS NULL';
        }
        $eng = @$db->query('SELECT id, name, employee_id FROM engineers WHERE ' . $engWhere . ' ORDER BY id ASC');
        if ($eng) {
            while ($row = $eng->fetch_assoc()) {
                $empId = (int)($row['employee_id'] ?? 0);
                $engId = (int)($row['id'] ?? 0);
                if ($empId > 0 && $engId > 0 && !isset($engByEmp[$empId])) {
                    $engByEmp[$empId] = $engId;
                }
            }
            $eng->free();
        }
        $orphanWhere = '(employee_id IS NULL OR employee_id = 0)';
        if (ame_staff_has_col($db, 'engineers', 'purged_at')) {
            $orphanWhere .= ' AND purged_at IS NULL';
        }
        if (ame_staff_has_col($db, 'engineers', 'removed_at')) {
            $orphanWhere .= ' AND removed_at IS NULL';
        }
        $orphans = @$db->query('SELECT id, name FROM engineers WHERE ' . $orphanWhere . ' ORDER BY name ASC');
        if ($orphans) {
            while ($row = $orphans->fetch_assoc()) {
                $engId = (int)($row['id'] ?? 0);
                if ($engId <= 0 || isset($seenEng[$engId])) {
                    continue;
                }
                $seenEng[$engId] = true;
                $rows[] = [
                    'id' => $engId,
                    'name' => (string)($row['name'] ?? ''),
                    'emp_code' => '',
                    'employee_id' => 0,
                    'present' => false,
                    'activated_at' => '',
                ];
            }
            $orphans->free();
        }
    } elseif (ame_staff_has_table($db, 'engineers')) {
        $eng = @$db->query('SELECT id, name FROM engineers ORDER BY name ASC');
        if ($eng) {
            while ($row = $eng->fetch_assoc()) {
                $engId = (int)($row['id'] ?? 0);
                if ($engId <= 0 || isset($seenEng[$engId])) {
                    continue;
                }
                $seenEng[$engId] = true;
                $rows[] = [
                    'id' => $engId,
                    'name' => (string)($row['name'] ?? ''),
                    'emp_code' => '',
                    'employee_id' => 0,
                    'present' => false,
                    'activated_at' => '',
                ];
            }
            $eng->free();
        }
    }
    foreach ($rows as &$dutyRow) {
        $empId = (int)($dutyRow['employee_id'] ?? 0);
        if ($empId > 0 && isset($engByEmp[$empId])) {
            $dutyRow['id'] = $engByEmp[$empId];
            $seenEng[$engByEmp[$empId]] = true;
        }
    }
    unset($dutyRow);
    $rows = ame_staff_duty_merge_logins($db, $rows);
    $rows = ame_staff_duty_unique_rows($rows);
    if (function_exists('ame_engineer_bill_expand_roster')) {
        $rows = ame_engineer_bill_expand_roster($db, $rows);
    }
    $rosterCache = $rows;
    return $rows;
}

function ame_staff_has_table(mysqli $db, string $table): bool
{
    $tbl = $db->real_escape_string($table);
    $res = @$db->query("SHOW TABLES LIKE '{$tbl}'");
    return ($res instanceof mysqli_result) && $res->num_rows > 0;
}

function ame_staff_has_col(mysqli $db, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $esc = $db->real_escape_string($column);
    $tbl = $db->real_escape_string($table);
    $res = @$db->query("SHOW COLUMNS FROM `{$tbl}` LIKE '{$esc}'");
    $cache[$key] = ($res instanceof mysqli_result) && $res->num_rows > 0;
    return $cache[$key];
}

function ame_staff_role_is_gate(string $role): bool
{
    return in_array(strtolower(trim($role)), ['admin', 'superadmin', 'owner'], true);
}

function ame_staff_is_last_gate(mysqli $db, int $user_id, string $role): bool
{
    if ($user_id <= 0 || !ame_staff_role_is_gate($role)) {
        return false;
    }
    $want = strtolower(trim($role));
    if ($want === 'admin' && function_exists('ame_count_active_admins')) {
        return ame_count_active_admins($db, $user_id) < 1;
    }
    $res = @$db->query('SELECT id, role, status, removed_at FROM users');
    if (!($res instanceof mysqli_result)) {
        return false;
    }
    $n = 0;
    while ($row = $res->fetch_assoc()) {
        if ((int)($row['id'] ?? 0) === $user_id) {
            continue;
        }
        if (strtolower(trim((string)($row['role'] ?? ''))) !== $want) {
            continue;
        }
        if (function_exists('ame_user_is_removed') && ame_user_is_removed($row)) {
            continue;
        }
        if (function_exists('ame_user_row_active') && !ame_user_row_active($row)) {
            continue;
        }
        $n++;
    }
    return $n < 1;
}

/** @return list<array<string,mixed>> */
function ame_staff_linked_users(mysqli $db, int $employee_id, string $phone = ''): array
{
    $users = [];
    $seen = [];
    if (!ame_staff_has_table($db, 'users')) {
        return $users;
    }
    $add = static function (?array $row) use (&$users, &$seen): void {
        if (!$row) {
            return;
        }
        $uid = (int)($row['id'] ?? 0);
        if ($uid <= 0 || isset($seen[$uid])) {
            return;
        }
        $seen[$uid] = true;
        $users[] = $row;
    };
    if ($employee_id > 0 && ame_staff_has_col($db, 'users', 'employee_id')) {
        $stmt = @$db->prepare('SELECT id, username, role, status FROM users WHERE employee_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $employee_id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $add($row);
                    }
                }
            }
            $stmt->close();
        }
    }
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits !== '' && ame_staff_has_col($db, 'users', 'phone')) {
        $stmt = @$db->prepare("SELECT id, username, role, status FROM users WHERE REPLACE(REPLACE(REPLACE(IFNULL(phone,''), ' ', ''), '-', ''), '+', '') = ?");
        if ($stmt) {
            $stmt->bind_param('s', $digits);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $add($row);
                    }
                }
            }
            $stmt->close();
        }
    }
    return $users;
}

/** @return list<array<string,mixed>> */
function ame_staff_linked_engineers(mysqli $db, int $employee_id, string $phone = ''): array
{
    $rows = [];
    $seen = [];
    if ($employee_id <= 0 || !ame_staff_has_table($db, 'engineers')) {
        return $rows;
    }
    $add = static function (?array $row) use (&$rows, &$seen): void {
        if (!$row) {
            return;
        }
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) {
            return;
        }
        $seen[$id] = true;
        $rows[] = $row;
    };
    if (ame_staff_has_col($db, 'engineers', 'employee_id')) {
        $stmt = @$db->prepare('SELECT id, name FROM engineers WHERE employee_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $employee_id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $add($row);
                    }
                }
            }
            $stmt->close();
        }
    }
    $phone = trim($phone);
    if ($phone !== '' && ame_staff_has_col($db, 'engineers', 'phone')) {
        $stmt = @$db->prepare('SELECT id, name FROM engineers WHERE phone = ? AND (employee_id IS NULL OR employee_id = 0 OR employee_id = ?)');
        if ($stmt) {
            $stmt->bind_param('si', $phone, $employee_id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $add($row);
                    }
                }
            }
            $stmt->close();
        }
    }
    return $rows;
}

function ame_staff_hide_row(mysqli $db, string $table, int $id): void
{
    if ($id <= 0 || !ame_staff_has_table($db, $table)) {
        return;
    }
    $sets = [];
    if (ame_staff_has_col($db, $table, 'status')) {
        $sets[] = "`status` = 'inactive'";
    }
    if (ame_staff_has_col($db, $table, 'removed_at')) {
        $sets[] = '`removed_at` = NOW()';
    }
    if (ame_staff_has_col($db, $table, 'purged_at')) {
        $sets[] = '`purged_at` = NOW()';
    }
    if (ame_staff_has_col($db, $table, 'archived_at')) {
        $sets[] = '`archived_at` = IFNULL(`archived_at`, NOW())';
    }
    if (!$sets) {
        return;
    }
    @$db->query('UPDATE `' . $db->real_escape_string($table) . '` SET ' . implode(', ', $sets) . ' WHERE id = ' . (int)$id . ' LIMIT 1');
}

function ame_staff_stamp_engineer_name(mysqli $db, int $engineer_id, string $name): void
{
    if ($engineer_id <= 0 || $name === '' || !ame_staff_has_table($db, 'sale_services')) {
        return;
    }
    if (!ame_staff_has_col($db, 'sale_services', 'engineer_id') || !ame_staff_has_col($db, 'sale_services', 'engineer_name')) {
        return;
    }
    $snap = @$db->prepare("UPDATE sale_services SET engineer_name = ? WHERE engineer_id = ? AND (engineer_name IS NULL OR engineer_name = '')");
    if ($snap) {
        $snap->bind_param('si', $name, $engineer_id);
        @$snap->execute();
        $snap->close();
    }
}

function ame_staff_kill_login(mysqli $db, array $user): ?string
{
    $uid = (int)($user['id'] ?? 0);
    if ($uid <= 0) {
        return null;
    }
    $me = (int)($_SESSION['user_id'] ?? 0);
    if ($uid === $me) {
        return 'নিজের লগইন মুছা যাবে না। অন্য ইউজার দিয়ে এই কর্মী ডিলিট করুন।';
    }
    $role = strtolower(trim((string)($user['role'] ?? '')));
    if (ame_staff_is_last_gate($db, $uid, $role)) {
        return 'শেষ অ্যাডমিনের লগইন মুছা যাবে না। অন্য অ্যাডমিন দিয়ে ডিলিট করুন।';
    }
    if (is_file(__DIR__ . '/user_functions.php')) {
        require_once __DIR__ . '/user_functions.php';
    }
    if (function_exists('ame_super_admin_user_ids')) {
        $sa = ame_super_admin_user_ids($db);
        if (in_array($uid, $sa, true)) {
            return 'সুপার অ্যাডমিন ইউজার চিরতরে মুছা যাবে না।';
        }
    }
    $uname = trim((string)($user['username'] ?? ''));
    if (function_exists('ame_remember_user_name') && $uname !== '') {
        ame_remember_user_name($db, $uid, $uname);
    }
    foreach (['user_permissions', 'password_resets', 'password_reset_otps'] as $table) {
        if (ame_staff_has_table($db, $table) && ame_staff_has_col($db, $table, 'user_id')) {
            @$db->query('DELETE FROM `' . $db->real_escape_string($table) . '` WHERE user_id = ' . $uid);
        }
    }
    @$db->query('SET FOREIGN_KEY_CHECKS=0');
    $gone = @$db->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
    $ok = false;
    if ($gone) {
        $gone->bind_param('i', $uid);
        $ok = (bool)@$gone->execute();
        $gone->close();
    }
    @$db->query('SET FOREIGN_KEY_CHECKS=1');
    if ($ok) {
        return null;
    }
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $parked = 'deleted.' . $uid;
    $now = date('Y-m-d H:i:s');
    $sets = ['status = 0', "username = '" . $db->real_escape_string($parked) . "'", "password = '" . $db->real_escape_string($hash) . "'"];
    if (ame_staff_has_col($db, 'users', 'removed_at')) {
        $sets[] = "removed_at = IFNULL(removed_at, '" . $now . "')";
    }
    if (ame_staff_has_col($db, 'users', 'purged_at')) {
        $sets[] = "purged_at = IFNULL(purged_at, '" . $now . "')";
    }
    @$db->query('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ' . $uid . ' LIMIT 1');
    if (function_exists('ame_bump_auth_version')) {
        ame_bump_auth_version($db, $uid);
    }
    return null;
}

function ame_staff_hide_worker(mysqli $db, int $employee_id, string $name = '', string $phone = ''): void
{
    ame_staff_duty_schema($db);
    foreach (ame_staff_linked_engineers($db, $employee_id, $phone) as $eng) {
        $engId = (int)($eng['id'] ?? 0);
        $engName = trim((string)($eng['name'] ?? ''));
        ame_staff_stamp_engineer_name($db, $engId, $engName !== '' ? $engName : $name);
        ame_staff_hide_row($db, 'engineers', $engId);
    }
    ame_staff_hide_row($db, 'employees', $employee_id);
}

function ame_staff_purge(mysqli $db, int $employee_id, string $code = ''): ?string
{
    ame_staff_duty_schema($db);
    if ($employee_id <= 0 && $code !== '' && function_exists('ame_staff_find_by_code')) {
        $employee_id = ame_staff_find_by_code($db, $code);
    }
    if ($employee_id <= 0) {
        return 'সঠিক কর্মী বাছুন।';
    }
    $stmt = @$db->prepare('SELECT id, name, phone, purged_at FROM employees WHERE id = ? LIMIT 1');
    if (!$stmt) {
        $stmt = @$db->prepare('SELECT id, name, purged_at FROM employees WHERE id = ? LIMIT 1');
    }
    if (!$stmt) {
        return 'ডাটাবেজ ত্রুটি।';
    }
    $stmt->bind_param('i', $employee_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return 'ডাটাবেজ ত্রুটি।';
    }
    $emp = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$emp) {
        return 'কর্মী পাওয়া যায়নি।';
    }
    if (trim((string)($emp['purged_at'] ?? '')) !== '') {
        return null;
    }
    $name = trim((string)($emp['name'] ?? ''));
    $phone = trim((string)($emp['phone'] ?? ''));
    if (is_file(__DIR__ . '/user_functions.php')) {
        require_once __DIR__ . '/user_functions.php';
    }
    foreach (ame_staff_linked_users($db, $employee_id, $phone) as $user) {
        $kill = ame_staff_kill_login($db, $user);
        if ($kill !== null) {
            return $kill;
        }
    }
    ame_staff_hide_worker($db, $employee_id, $name, $phone);
    if (function_exists('log_activity')) {
        log_activity('Staff purged', 'HRM', $name !== '' ? $name : ('#' . $employee_id));
    }
    return null;
}

function ame_staff_hard_delete(mysqli $db, int $employee_id): ?string
{
    ame_staff_duty_schema($db);
    if ($employee_id <= 0) {
        return 'সঠিক কর্মী বাছুন।';
    }
    $stmt = @$db->prepare('SELECT id, name, phone, photo, purged_at FROM employees WHERE id = ? LIMIT 1');
    if (!$stmt) {
        $stmt = @$db->prepare('SELECT id, name, photo, purged_at FROM employees WHERE id = ? LIMIT 1');
    }
    if (!$stmt) {
        return 'ডাটাবেজ ত্রুটি।';
    }
    $stmt->bind_param('i', $employee_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return 'ডাটাবেজ ত্রুটি।';
    }
    $emp = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$emp) {
        return 'কর্মী পাওয়া যায়নি।';
    }
    $name = trim((string)($emp['name'] ?? ''));
    $phone = trim((string)($emp['phone'] ?? ''));
    if (is_file(__DIR__ . '/user_functions.php')) {
        require_once __DIR__ . '/user_functions.php';
    }
    if (is_file(__DIR__ . '/super_admin.php')) {
        require_once __DIR__ . '/super_admin.php';
    }
    $users = ame_staff_linked_users($db, $employee_id, $phone);
    foreach ($users as $user) {
        $block = ame_staff_kill_login($db, $user);
        if ($block !== null) {
            return $block;
        }
    }
    ame_staff_hide_worker($db, $employee_id, $name, $phone);
    if (ame_staff_has_col($db, 'employees', 'name_snapshot') && $name !== '') {
        $snap = @$db->prepare("UPDATE employees SET name_snapshot = IFNULL(NULLIF(name_snapshot,''), ?) WHERE id = ?");
        if ($snap) {
            $snap->bind_param('si', $name, $employee_id);
            @$snap->execute();
            $snap->close();
        }
    }
    $photo = trim((string)($emp['photo'] ?? ''));
    if ($photo !== '' && !str_contains($photo, '/') && !str_contains($photo, '\\')) {
        $path = dirname(__DIR__) . '/uploads/employees/' . $photo;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    if (function_exists('log_activity')) {
        log_activity('Staff hard delete', 'HRM', $name !== '' ? $name : ('#' . $employee_id));
    }
    return null;
}

function ame_staff_visible_sql(string $alias = 'e'): string
{
    $p = $alias . '.purged_at';
    $r = $alias . '.removed_at';
    return '(' . $p . ' IS NULL AND ' . $r . ' IS NULL)';
}

/** Employee IDs woken from the sales header 🟢 today (Present / Late). */
function ame_staff_on_duty_ids(mysqli $db): array
{
    ame_staff_duty_schema($db);
    $today = ame_staff_duty_today();
    $ids = [];
    $stmt = @$db->prepare("SELECT employee_id FROM attendance WHERE att_date = ? AND status IN ('Present','Late')");
    if (!$stmt) {
        return $ids;
    }
    $stmt->bind_param('s', $today);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)($row['employee_id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }
    }
    $stmt->close();
    return array_values($ids);
}

function ame_staff_is_duty_active(mysqli $db, int $employee_id): bool
{
    if ($employee_id <= 0) {
        return false;
    }
    return in_array($employee_id, ame_staff_on_duty_ids($db), true);
}

if (is_file(__DIR__ . '/engineer_bill_lock.php')) {
    require_once __DIR__ . '/engineer_bill_lock.php';
    if (isset($db) && $db instanceof mysqli && function_exists('ame_engineer_bill_boot')) {
        ame_engineer_bill_boot($db);
    }
}
