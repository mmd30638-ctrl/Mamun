<?php
/**
 * Unified employee hub — schema, attendance, work, snack, ledger, reports.
 * One person, one profile. Historical rows keep names after purge.
 */

function ame_emp_hub_schema(mysqli $db): void
{
    $lockFile = __DIR__ . '/worker_work_lock.php';
    if (is_file($lockFile)) {
        require_once $lockFile;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('ensure_user_schema')) {
        ensure_user_schema($db);
    }
    if (function_exists('ensure_hrm_schema')) {
        ensure_hrm_schema($db);
    }
    if (function_exists('ensure_attendance_schema')) {
        ensure_attendance_schema($db);
    }
    if (function_exists('ame_staff_duty_schema')) {
        ame_staff_duty_schema($db);
    }
    if (function_exists('ame_staff_duty_tick')) {
        ame_staff_duty_tick($db);
    }

    $empCols = ame_emp_hub_cols($db, 'employees');
    $alters = [
        'payment_type' => "ALTER TABLE employees ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'percentage'",
        'daily_snack_amount' => 'ALTER TABLE employees ADD COLUMN daily_snack_amount DECIMAL(12,2) NOT NULL DEFAULT 0',
        'snack_mode' => "ALTER TABLE employees ADD COLUMN snack_mode VARCHAR(20) NOT NULL DEFAULT 'none'",
        'is_engineer' => 'ALTER TABLE employees ADD COLUMN is_engineer TINYINT(1) NOT NULL DEFAULT 0',
        'is_service' => 'ALTER TABLE employees ADD COLUMN is_service TINYINT(1) NOT NULL DEFAULT 0',
        'is_repair' => 'ALTER TABLE employees ADD COLUMN is_repair TINYINT(1) NOT NULL DEFAULT 0',
        'is_sales' => 'ALTER TABLE employees ADD COLUMN is_sales TINYINT(1) NOT NULL DEFAULT 0',
        'user_role' => "ALTER TABLE employees ADD COLUMN user_role VARCHAR(40) NULL DEFAULT NULL",
        'name_snapshot' => 'ALTER TABLE employees ADD COLUMN name_snapshot VARCHAR(150) NULL DEFAULT NULL',
    ];
    foreach ($alters as $col => $sql) {
        if (!isset($empCols[$col])) {
            @$db->query($sql);
        }
    }
    if (isset($empCols['salary_type'])) {
        @$db->query("ALTER TABLE employees MODIFY COLUMN salary_type VARCHAR(20) NOT NULL DEFAULT 'monthly'");
    }

    $attCols = ame_emp_hub_cols($db, 'attendance');
    if (!isset($attCols['changed_by'])) {
        @$db->query('ALTER TABLE attendance ADD COLUMN changed_by INT NULL DEFAULT NULL');
    }
    if (!isset($attCols['changed_at'])) {
        @$db->query('ALTER TABLE attendance ADD COLUMN changed_at DATETIME NULL DEFAULT NULL');
    }
    if (!isset($attCols['previous_status'])) {
        @$db->query("ALTER TABLE attendance ADD COLUMN previous_status VARCHAR(20) NULL DEFAULT NULL");
    }
    if (!isset($attCols['changed_by_name'])) {
        @$db->query('ALTER TABLE attendance ADD COLUMN changed_by_name VARCHAR(120) NULL DEFAULT NULL');
    }

    $db->query("CREATE TABLE IF NOT EXISTS employee_works (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        employee_name VARCHAR(150) NULL DEFAULT NULL,
        work_date DATE NOT NULL,
        customer_id INT NULL DEFAULT NULL,
        customer_name VARCHAR(150) NULL DEFAULT NULL,
        vehicle VARCHAR(120) NULL DEFAULT NULL,
        vehicle_no VARCHAR(60) NULL DEFAULT NULL,
        service_type VARCHAR(120) NULL DEFAULT NULL,
        description TEXT NULL,
        work_value DECIMAL(12,2) NOT NULL DEFAULT 0,
        employee_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
        employee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        snack DECIMAL(12,2) NOT NULL DEFAULT 0,
        other_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
        shop_contribution DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_by INT NULL DEFAULT NULL,
        created_by_name VARCHAR(120) NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY employee_id (employee_id),
        KEY work_date (work_date),
        KEY customer_id (customer_id),
        KEY vehicle_no (vehicle_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $workCols = ame_emp_hub_cols($db, 'employee_works');
    if (!isset($workCols['work_time'])) {
        @$db->query('ALTER TABLE employee_works ADD COLUMN work_time TIME NULL DEFAULT NULL');
    }
    if (!isset($workCols['updated_at'])) {
        @$db->query('ALTER TABLE employee_works ADD COLUMN updated_at DATETIME NULL DEFAULT NULL');
    }
    if (!isset($workCols['updated_by_name'])) {
        @$db->query('ALTER TABLE employee_works ADD COLUMN updated_by_name VARCHAR(120) NULL DEFAULT NULL');
    }
    $workAlters = [
        'customer_phone' => 'ALTER TABLE employee_works ADD COLUMN customer_phone VARCHAR(40) NULL DEFAULT NULL',
        'invoice_ref' => 'ALTER TABLE employee_works ADD COLUMN invoice_ref VARCHAR(100) NULL DEFAULT NULL',
        'memo_no' => 'ALTER TABLE employee_works ADD COLUMN memo_no VARCHAR(100) NULL DEFAULT NULL',
        'imei_serial' => 'ALTER TABLE employee_works ADD COLUMN imei_serial VARCHAR(150) NULL DEFAULT NULL',
        'bonus_amount' => 'ALTER TABLE employee_works ADD COLUMN bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0',
        'snack' => 'ALTER TABLE employee_works ADD COLUMN snack DECIMAL(12,2) NOT NULL DEFAULT 0',
        'source_type' => "ALTER TABLE employee_works ADD COLUMN source_type VARCHAR(30) NOT NULL DEFAULT 'manual'",
        'source_id' => 'ALTER TABLE employee_works ADD COLUMN source_id INT NULL DEFAULT NULL',
        'payout_status' => "ALTER TABLE employee_works ADD COLUMN payout_status VARCHAR(20) NOT NULL DEFAULT 'pending'",
        'salary_id' => 'ALTER TABLE employee_works ADD COLUMN salary_id INT NULL DEFAULT NULL',
    ];
    foreach ($workAlters as $col => $sql) {
        if (!isset($workCols[$col])) {
            @$db->query($sql);
        }
    }
    @$db->query('ALTER TABLE employee_works ADD INDEX idx_work_payout (employee_id, payout_status, work_date)');

    $salaryCols = ame_emp_hub_cols($db, 'salaries');
    if (!isset($salaryCols['pay_mode_snapshot'])) {
        @$db->query("ALTER TABLE salaries ADD COLUMN pay_mode_snapshot VARCHAR(20) NULL DEFAULT NULL");
    }
    if (!isset($salaryCols['pay_rule_id'])) {
        @$db->query('ALTER TABLE salaries ADD COLUMN pay_rule_id INT NULL DEFAULT NULL');
    }

    $db->query("CREATE TABLE IF NOT EXISTS employee_snacks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        employee_name VARCHAR(150) NULL DEFAULT NULL,
        snack_date DATE NOT NULL,
        kind VARCHAR(20) NOT NULL DEFAULT 'daily',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL DEFAULT NULL,
        created_by INT NULL DEFAULT NULL,
        created_by_name VARCHAR(120) NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        payout_status VARCHAR(20) NOT NULL DEFAULT 'pending',
        salary_id INT NULL DEFAULT NULL,
        KEY employee_id (employee_id),
        KEY snack_date (snack_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $snackCols = ame_emp_hub_cols($db, 'employee_snacks');
    if (!isset($snackCols['payout_status'])) {
        @$db->query("ALTER TABLE employee_snacks ADD COLUMN payout_status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }
    if (!isset($snackCols['salary_id'])) {
        @$db->query('ALTER TABLE employee_snacks ADD COLUMN salary_id INT NULL DEFAULT NULL');
    }
    @$db->query('ALTER TABLE employee_snacks ADD INDEX idx_snack_payout (employee_id, payout_status, snack_date)');

    $db->query("CREATE TABLE IF NOT EXISTS employee_hub_audit (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NULL DEFAULT NULL,
        employee_name VARCHAR(150) NULL DEFAULT NULL,
        action VARCHAR(60) NOT NULL,
        field_name VARCHAR(60) NULL DEFAULT NULL,
        old_value VARCHAR(255) NULL DEFAULT NULL,
        new_value VARCHAR(255) NULL DEFAULT NULL,
        actor_id INT NULL DEFAULT NULL,
        actor_name VARCHAR(120) NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY employee_id (employee_id),
        KEY action (action),
        KEY created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // One effective-dated compensation timeline per person. The legacy earnings
    // module already uses this table; the employee hub is now its canonical editor.
    $db->query("CREATE TABLE IF NOT EXISTS worker_payment_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        worker_type VARCHAR(40) NOT NULL DEFAULT 'employee',
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
        KEY effective_from (effective_from),
        KEY idx_worker_rule_range (employee_id, effective_from, effective_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (function_exists('ame_link_staff_users')) {
        ame_link_staff_users($db);
    }
    ame_emp_hub_absorb_users($db);
    if (function_exists('ensure_employee_advance_schema')) {
        ensure_employee_advance_schema($db);
    }
    if (function_exists('ame_worker_work_lock_ensure_schema')) {
        ame_worker_work_lock_ensure_schema($db);
    }
}

function ame_emp_hub_cols(mysqli $db, string $table): array
{
    $cols = [];
    $res = @$db->query('SHOW COLUMNS FROM `' . $db->real_escape_string($table) . '`');
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            $cols[$c['Field']] = true;
        }
    }
    return $cols;
}

/**
 * কাজের নাস্তা কলাম ও নাস্তা-খাতা নিশ্চিত করে — schema() আগে চলে গেলেও বাদ যায় না।
 */
function ame_emp_hub_ensure_snack_columns(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $workCols = ame_emp_hub_cols($db, 'employee_works');
    if ($workCols !== [] && !isset($workCols['snack'])) {
        @$db->query('ALTER TABLE employee_works ADD COLUMN snack DECIMAL(12,2) NOT NULL DEFAULT 0');
    }
    @$db->query("CREATE TABLE IF NOT EXISTS employee_snacks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        employee_name VARCHAR(150) NULL DEFAULT NULL,
        snack_date DATE NOT NULL,
        kind VARCHAR(20) NOT NULL DEFAULT 'daily',
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL DEFAULT NULL,
        created_by INT NULL DEFAULT NULL,
        created_by_name VARCHAR(120) NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        payout_status VARCHAR(20) NOT NULL DEFAULT 'pending',
        salary_id INT NULL DEFAULT NULL,
        KEY employee_id (employee_id),
        KEY snack_date (snack_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $snackCols = ame_emp_hub_cols($db, 'employee_snacks');
    if (!isset($snackCols['payout_status'])) {
        @$db->query("ALTER TABLE employee_snacks ADD COLUMN payout_status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }
    if (!isset($snackCols['salary_id'])) {
        @$db->query('ALTER TABLE employee_snacks ADD COLUMN salary_id INT NULL DEFAULT NULL');
    }
}

function ame_emp_hub_absorb_users(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('ame_link_staff_users')) {
        ame_link_staff_users($db);
    }
}

function ame_emp_hub_photo_url($file): string
{
    $file = trim((string)$file);
    if ($file === '') {
        return '/assets/img/user-placeholder.png';
    }
    if (str_starts_with($file, '/') || str_starts_with($file, 'http://') || str_starts_with($file, 'https://')) {
        return $file;
    }
    return '/uploads/employees/' . rawurlencode($file);
}

function ame_emp_hub_save_photo(array $files, int $empId): ?string
{
    if (empty($files['photo']) || !is_array($files['photo'])) {
        return null;
    }
    $f = $files['photo'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) {
        return null;
    }
    if ((int)($f['size'] ?? 0) <= 0 || (int)$f['size'] > 5 * 1024 * 1024) {
        throw new Exception('ছবি ৫ মেগাবাইটের মধ্যে দিন।');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $f['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        throw new Exception('শুধু JPG, PNG বা WEBP ছবি চলবে।');
    }
    $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($root === '') {
        $root = dirname(__DIR__);
    }
    $dir = $root . '/uploads/employees/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new Exception('ছবির ফোল্ডার তৈরি হয়নি।');
    }
    $name = 'emp_' . max(1, $empId) . '_' . time() . '_' . random_int(1000, 9999) . '.' . $map[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) {
        throw new Exception('ছবি রাখা যায়নি।');
    }
    return $name;
}

function ame_emp_hub_apply_photo(mysqli $db, int $empId, array $files): void
{
    $name = ame_emp_hub_save_photo($files, $empId);
    if ($name === null) {
        return;
    }
    $st = $db->prepare('UPDATE employees SET photo = ? WHERE id = ?');
    if ($st) {
        $st->bind_param('si', $name, $empId);
        $st->execute();
        $st->close();
    }
}

function ame_emp_hub_sync_login(mysqli $db, int $empId, array $in): void
{
    $emp = ame_emp_hub_row($db, $empId, true);
    if (!$emp) {
        return;
    }
    $username = trim((string)($in['login_username'] ?? $in['username'] ?? ''));
    $password = (string)($in['login_password'] ?? $in['password'] ?? '');
    $role = strtolower(trim((string)($in['login_role'] ?? $in['user_role'] ?? '')));
    $loginStatus = array_key_exists('login_status', $in)
        ? (((string)$in['login_status'] === '0' || $in['login_status'] === 0 || $in['login_status'] === false) ? 0 : 1)
        : null;

    $existing = function_exists('get_employee_login') ? get_employee_login($db, $empId) : null;
    if ($username === '' && $existing) {
        $username = (string)($existing['username'] ?? '');
    }
    if ($username === '') {
        return;
    }
    if ($role === '') {
        $role = strtolower(trim((string)($existing['role'] ?? $emp['user_role'] ?? 'staff')));
    }
    if ($role === '') {
        $role = 'staff';
    }

    $other = null;
    $chk = @$db->prepare('SELECT id, employee_id, role, status FROM users WHERE username = ? LIMIT 1');
    if ($chk) {
        $chk->bind_param('s', $username);
        $chk->execute();
        $other = $chk->get_result()->fetch_assoc();
        $chk->close();
    }
    if ($other) {
        $oid = (int)$other['id'];
        $ownId = (int)($existing['id'] ?? 0);
        if ($ownId > 0 && $oid !== $ownId) {
            throw new Exception('এই ইউজারনেম অন্য লগইনে আছে।');
        }
        $otherEmp = (int)($other['employee_id'] ?? 0);
        if ($otherEmp > 0 && $otherEmp !== $empId) {
            throw new Exception('এই ইউজারনেম অন্য প্রোফাইলে আছে।');
        }
        $existing = $existing ?: $other;
        if ($otherEmp !== $empId) {
            $link = $db->prepare('UPDATE users SET employee_id = ? WHERE id = ?');
            if ($link) {
                $link->bind_param('ii', $empId, $oid);
                $link->execute();
                $link->close();
            }
        }
    }

    if ($password !== '') {
        if (!function_exists('ame_validate_password')) {
            throw new Exception('পাসওয়ার্ড নিয়ম পাওয়া যায়নি।');
        }
        $pwErr = ame_validate_password($password);
        if ($pwErr !== null) {
            throw new Exception($pwErr);
        }
    }

    $phone = preg_replace('/\s+/', '', (string)($emp['phone'] ?? ''));
    $statusVal = $loginStatus === null ? (int)($existing['status'] ?? 1) : $loginStatus;
    if ($existing && $statusVal === 0 && function_exists('ame_count_active_admins')) {
        $erole = strtolower((string)($existing['role'] ?? $role));
        if ($erole === 'admin' && ame_count_active_admins($db, (int)$existing['id']) < 1) {
            throw new Exception('শেষ অ্যাডমিন লগইন বন্ধ করা যাবে না।');
        }
    }

    if ($existing) {
        $uid = (int)$existing['id'];
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $db->prepare('UPDATE users SET username = ?, password = ?, role = ?, phone = ?, status = ?, employee_id = ? WHERE id = ?');
            $st->bind_param('ssssiii', $username, $hash, $role, $phone, $statusVal, $empId, $uid);
        } else {
            $st = $db->prepare('UPDATE users SET username = ?, role = ?, phone = ?, status = ?, employee_id = ? WHERE id = ?');
            $st->bind_param('sssiii', $username, $role, $phone, $statusVal, $empId, $uid);
        }
        if (!$st || !$st->execute()) {
            throw new Exception('লগইন আপডেট হয়নি।');
        }
        $st->close();
        $bump = ($password !== '')
            || ((string)($existing['username'] ?? '') !== $username)
            || ((int)($existing['status'] ?? 1) !== $statusVal);
        if ($bump && function_exists('ame_bump_auth_version')) {
            ame_bump_auth_version($db, $uid);
        }
        if (function_exists('ame_remember_user_name')) {
            ame_remember_user_name($db, $uid, (string)$emp['name']);
        }
        if (function_exists('ame_sync_user_employee_identity')) {
            ame_sync_user_employee_identity($db, $uid, $empId);
        }
        return;
    }

    if ($password === '') {
        throw new Exception('নতুন লগইনের জন্য পাসওয়ার্ড দিন।');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = $db->prepare('INSERT INTO users (username, password, role, phone, employee_id, status) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$st) {
        throw new Exception('লগইন তৈরি হয়নি।');
    }
    $st->bind_param('ssssii', $username, $hash, $role, $phone, $empId, $statusVal);
    if (!$st->execute()) {
        $st->close();
        throw new Exception('লগইন তৈরি হয়নি: ' . $db->error);
    }
    $newUid = (int)$st->insert_id;
    $st->close();
    if (function_exists('ame_remember_user_name')) {
        ame_remember_user_name($db, $newUid, (string)$emp['name']);
    }
    if (function_exists('ame_sync_user_employee_identity')) {
        ame_sync_user_employee_identity($db, $newUid, $empId);
    }
}

function ame_emp_hub_enrich_person(mysqli $db, array $row): array
{
    $id = (int)($row['id'] ?? 0);
    $login = ($id > 0 && function_exists('get_employee_login')) ? get_employee_login($db, $id) : null;
    $row['user_id'] = (int)($login['id'] ?? 0);
    if (trim((string)($row['login_name'] ?? '')) === '' && $login) {
        $row['login_name'] = (string)($login['username'] ?? '');
    }
    $row['login_status'] = isset($login['status']) ? (int)$login['status'] : 1;
    $row['login_role'] = strtolower(trim((string)($login['role'] ?? $row['user_role'] ?? '')));
    $row['photo_url'] = ame_emp_hub_photo_url($row['photo'] ?? '');
    $row['is_engineer'] = (int)($row['is_engineer'] ?? 0);
    $row['is_service'] = (int)($row['is_service'] ?? 0);
    $row['is_repair'] = (int)($row['is_repair'] ?? 0);
    $row['is_sales'] = (int)($row['is_sales'] ?? 0);
    if ($id > 0 && function_exists('ame_emp_hub_pay_rule_on')) {
        $rule = ame_emp_hub_pay_rule_on($db, $row, ame_emp_hub_today());
        $mode = ame_emp_hub_valid_pay_mode((string)($rule['payment_type'] ?? $row['payment_type'] ?? 'percentage'));
        $row['payment_type'] = $mode;
        $row['salary_type'] = $mode === 'daily' ? 'daily' : 'monthly';
        $row['service_share_percent'] = (float)($rule['percent_rate'] ?? $row['service_share_percent'] ?? 50);
        if ($mode === 'daily') {
            $row['salary'] = (float)($rule['daily_rate'] ?? $row['salary'] ?? 0);
        } elseif (in_array($mode, ['monthly', 'mixed'], true)) {
            $row['salary'] = (float)($rule['monthly_salary'] ?? $row['salary'] ?? 0);
        }
    }
    return $row;
}

function ame_emp_hub_today(): string
{
    if (function_exists('ame_staff_duty_today')) {
        return ame_staff_duty_today();
    }
    try {
        return (new DateTime('now', new DateTimeZone('Asia/Dhaka')))->format('Y-m-d');
    } catch (Throwable $e) {
        return date('Y-m-d');
    }
}

function ame_emp_hub_now(): string
{
    try {
        return (new DateTime('now', new DateTimeZone('Asia/Dhaka')))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return date('Y-m-d H:i:s');
    }
}

function ame_emp_hub_actor(): array
{
    return [
        'id' => (int)($_SESSION['user_id'] ?? 0),
        'name' => trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['name'] ?? 'User')),
    ];
}

function ame_emp_hub_can_finance(?mysqli $db = null): bool
{
    if (function_exists('ame_hrm_is_admin') && ame_hrm_is_admin()) {
        return true;
    }
    if (function_exists('ame_is_admin_like') && ame_is_admin_like()) {
        return true;
    }
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if (in_array($role, ['admin', 'superadmin', 'owner', 'accountant'], true)) {
        return true;
    }
    return $db instanceof mysqli && function_exists('has_permission') && has_permission($db, 'm-acc');
}

function ame_emp_hub_can_advance(mysqli $db): bool
{
    return function_exists('ame_hrm_can_advance') ? ame_hrm_can_advance($db) : ame_emp_hub_can_finance($db);
}

function ame_emp_hub_can_delete(?mysqli $db = null): bool
{
    if (ame_emp_hub_can_finance($db)) {
        return true;
    }
    if (function_exists('ame_hrm_is_admin') && ame_hrm_is_admin()) {
        return true;
    }
    if (function_exists('ame_is_admin_like') && ame_is_admin_like()) {
        return true;
    }
    return $db instanceof mysqli && function_exists('has_permission') && has_permission($db, 'm-hrm');
}

function ame_emp_hub_self_id(mysqli $db): int
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

function ame_emp_hub_pay_mode(array $emp): string
{
    $p = strtolower(trim((string)($emp['payment_type'] ?? '')));
    $allowed = ['daily', 'monthly', 'percentage', 'work', 'mixed'];
    if (in_array($p, $allowed, true)) {
        return $p;
    }
    if (function_exists('ame_staff_pay_mode')) {
        $legacy = ame_staff_pay_mode($emp);
        return $legacy === 'daily' ? 'daily' : 'percentage';
    }
    return (($emp['salary_type'] ?? '') === 'daily') ? 'daily' : 'percentage';
}

function ame_emp_hub_snack_kind_label(string $kind): string
{
    return [
        'daily' => 'দৈনিক',
        'extra' => 'অতিরিক্ত',
        'manual' => 'হাতের এন্ট্রি',
        'none' => 'নাস্তা নেই',
        'deposit' => 'জমা',
        'withdraw' => 'উত্তোলন',
        'give' => 'অগ্রিম',
        'draft' => 'সকালের ড্রাফট',
        'collect' => 'ফেরত',
        'salary_cut' => 'বেতন সমন্বয়',
    ][$kind] ?? $kind;
}

function ame_emp_hub_pay_label(string $mode): string
{
    return [
        'daily' => 'দৈনিক বেতন',
        'monthly' => 'মাসিক বেতন',
        'percentage' => 'শতাংশ ভাগ',
        'work' => 'কাজ ভিত্তিক',
        'mixed' => 'মিশ্র বেতন',
    ][$mode] ?? $mode;
}

function ame_emp_hub_valid_pay_mode(string $mode): string
{
    $mode = strtolower(trim($mode));
    return in_array($mode, ['daily', 'monthly', 'percentage', 'work', 'mixed'], true)
        ? $mode
        : 'percentage';
}

function ame_emp_hub_seed_pay_rule(mysqli $db, array $emp): void
{
    $empId = (int)($emp['id'] ?? 0);
    if ($empId <= 0) {
        return;
    }
    $check = @$db->prepare('SELECT id FROM worker_payment_rules WHERE employee_id = ? LIMIT 1');
    if (!$check) {
        return;
    }
    $check->bind_param('i', $empId);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();
    if ($exists) {
        return;
    }

    $mode = ame_emp_hub_valid_pay_mode(ame_emp_hub_pay_mode($emp));
    $percent = round(max(0, min(100, (float)($emp['service_share_percent'] ?? 50))), 2);
    $salary = round(max(0, (float)($emp['salary'] ?? 0)), 2);
    $daily = $mode === 'daily' ? $salary : 0.0;
    $monthly = in_array($mode, ['monthly', 'mixed'], true) ? $salary : 0.0;
    $from = (string)($emp['joining_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = ame_emp_hub_today();
    }
    $workerType = trim((string)($emp['worker_type'] ?? 'employee')) ?: 'employee';
    $note = 'প্রাথমিক বেতনের নিয়ম';
    $actor = (int)($_SESSION['user_id'] ?? 0);
    $insert = @$db->prepare(
        'INSERT INTO worker_payment_rules
            (employee_id, worker_type, payment_type, percent_rate, fixed_amount, fixed_scope, daily_rate, monthly_salary, custom_note, effective_from, created_by)
         VALUES (?, ?, ?, ?, 0, \'per_job\', ?, ?, ?, ?, ?)'
    );
    if ($insert) {
        $insert->bind_param('issdddssi', $empId, $workerType, $mode, $percent, $daily, $monthly, $note, $from, $actor);
        $insert->execute();
        $insert->close();
    }
}

function ame_emp_hub_pay_rule_on(mysqli $db, array $emp, string $date): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = ame_emp_hub_today();
    }
    ame_emp_hub_seed_pay_rule($db, $emp);
    $empId = (int)($emp['id'] ?? 0);
    $stmt = @$db->prepare(
        'SELECT * FROM worker_payment_rules
         WHERE employee_id = ? AND effective_from <= ?
           AND (effective_to IS NULL OR effective_to >= ?)
         ORDER BY effective_from DESC, id DESC LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('iss', $empId, $date, $date);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $row['payment_type'] = ame_emp_hub_valid_pay_mode((string)$row['payment_type']);
            return $row;
        }
    }
    $mode = ame_emp_hub_valid_pay_mode(ame_emp_hub_pay_mode($emp));
    $salary = round(max(0, (float)($emp['salary'] ?? 0)), 2);
    return [
        'id' => 0,
        'employee_id' => $empId,
        'payment_type' => $mode,
        'percent_rate' => round(max(0, min(100, (float)($emp['service_share_percent'] ?? 50))), 2),
        'daily_rate' => $mode === 'daily' ? $salary : 0.0,
        'monthly_salary' => in_array($mode, ['monthly', 'mixed'], true) ? $salary : 0.0,
        'effective_from' => (string)($emp['joining_date'] ?? $date),
        'effective_to' => null,
        'custom_note' => '',
    ];
}

function ame_emp_hub_pay_history(mysqli $db, array $emp): array
{
    ame_emp_hub_seed_pay_rule($db, $emp);
    $rows = [];
    $empId = (int)($emp['id'] ?? 0);
    $stmt = @$db->prepare(
        'SELECT id, payment_type, percent_rate, daily_rate, monthly_salary, custom_note, effective_from, effective_to
         FROM worker_payment_rules WHERE employee_id = ? ORDER BY effective_from ASC, id ASC'
    );
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('i', $empId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $mode = ame_emp_hub_valid_pay_mode((string)$row['payment_type']);
        $rate = $mode === 'daily'
            ? (float)$row['daily_rate']
            : (in_array($mode, ['monthly', 'mixed'], true) ? (float)$row['monthly_salary'] : (float)$row['percent_rate']);
        $rows[] = [
            'id' => (int)$row['id'],
            'payment_type' => $mode,
            'pay_label' => ame_emp_hub_pay_label($mode),
            'rate' => round($rate, 2),
            'rate_label' => in_array($mode, ['percentage', 'work'], true)
                ? rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%'
                : '৳' . number_format($rate, 2),
            'effective_from' => (string)$row['effective_from'],
            'effective_to' => $row['effective_to'] !== null ? (string)$row['effective_to'] : '',
            'note' => (string)($row['custom_note'] ?? ''),
        ];
    }
    $stmt->close();
    return $rows;
}

function ame_emp_hub_save_pay_rule(
    mysqli $db,
    array $emp,
    string $mode,
    float $salary,
    float $percent,
    string $effectiveFrom,
    string $note = ''
): array {
    $empId = (int)($emp['id'] ?? 0);
    if ($empId <= 0) {
        throw new Exception('সঠিক কর্মী বাছুন।');
    }
    $mode = ame_emp_hub_valid_pay_mode($mode);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveFrom)) {
        throw new Exception('বেতন পরিবর্তনের কার্যকর তারিখ দিন।');
    }
    $join = (string)($emp['joining_date'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $join) && $effectiveFrom < $join) {
        throw new Exception('যোগদানের তারিখের আগে বেতনের নিয়ম চালু করা যাবে না।');
    }
    $salary = round(max(0, $salary), 2);
    $percent = round(max(0, min(100, $percent)), 2);
    if ($mode === 'daily' && $salary <= 0) {
        throw new Exception('দৈনিক বেতনের টাকার পরিমাণ দিন।');
    }
    if (in_array($mode, ['percentage', 'work'], true) && $percent <= 0) {
        throw new Exception('কাজের ভাগের শতাংশ দিন।');
    }
    $note = mb_substr(trim($note), 0, 255);
    $lockName = substr('employee_pay_rule_' . $empId, 0, 60);
    $lock = $db->prepare('SELECT GET_LOCK(?, 10) locked');
    $lock->bind_param('s', $lockName);
    $lock->execute();
    $locked = (int)($lock->get_result()->fetch_assoc()['locked'] ?? 0) === 1;
    $lock->close();
    if (!$locked) {
        throw new Exception('বেতনের আরেকটি পরিবর্তন চলছে। আবার চেষ্টা করুন।');
    }

    try {
        ame_emp_hub_seed_pay_rule($db, $emp);
        $same = $db->prepare(
            'SELECT id FROM worker_payment_rules WHERE employee_id = ? AND effective_from = ? ORDER BY id DESC LIMIT 1'
        );
        $same->bind_param('is', $empId, $effectiveFrom);
        $same->execute();
        $sameRow = $same->get_result()->fetch_assoc();
        $same->close();
        $daily = $mode === 'daily' ? $salary : 0.0;
        $monthly = in_array($mode, ['monthly', 'mixed'], true) ? $salary : 0.0;
        $workerType = trim((string)($emp['worker_type'] ?? 'employee')) ?: 'employee';
        $actor = (int)($_SESSION['user_id'] ?? 0);

        if ($sameRow) {
            $ruleId = (int)$sameRow['id'];
            $update = $db->prepare(
                'UPDATE worker_payment_rules
                 SET worker_type=?, payment_type=?, percent_rate=?, daily_rate=?, monthly_salary=?, custom_note=?, created_by=?
                 WHERE id=?'
            );
            $update->bind_param('ssdddsii', $workerType, $mode, $percent, $daily, $monthly, $note, $actor, $ruleId);
            $update->execute();
            $update->close();
        } else {
            $previousEnd = date('Y-m-d', strtotime($effectiveFrom . ' -1 day'));
            $close = $db->prepare(
                'UPDATE worker_payment_rules SET effective_to = ?
                 WHERE employee_id = ? AND effective_from < ?
                   AND (effective_to IS NULL OR effective_to >= ?)'
            );
            $close->bind_param('siss', $previousEnd, $empId, $effectiveFrom, $effectiveFrom);
            $close->execute();
            $close->close();

            $next = $db->prepare(
                'SELECT effective_from FROM worker_payment_rules
                 WHERE employee_id = ? AND effective_from > ? ORDER BY effective_from ASC, id ASC LIMIT 1'
            );
            $next->bind_param('is', $empId, $effectiveFrom);
            $next->execute();
            $nextRow = $next->get_result()->fetch_assoc();
            $next->close();
            $effectiveTo = $nextRow
                ? date('Y-m-d', strtotime((string)$nextRow['effective_from'] . ' -1 day'))
                : null;
            $insert = $db->prepare(
                'INSERT INTO worker_payment_rules
                    (employee_id, worker_type, payment_type, percent_rate, fixed_amount, fixed_scope, daily_rate, monthly_salary, custom_note, effective_from, effective_to, created_by)
                 VALUES (?, ?, ?, ?, 0, \'per_job\', ?, ?, ?, ?, ?, ?)'
            );
            $insert->bind_param('issdddsssi', $empId, $workerType, $mode, $percent, $daily, $monthly, $note, $effectiveFrom, $effectiveTo, $actor);
            $insert->execute();
            $insert->close();
        }

        $todayRule = ame_emp_hub_pay_rule_on($db, $emp, ame_emp_hub_today());
        $todayMode = ame_emp_hub_valid_pay_mode((string)$todayRule['payment_type']);
        $todaySalary = $todayMode === 'daily'
            ? (float)$todayRule['daily_rate']
            : (in_array($todayMode, ['monthly', 'mixed'], true) ? (float)$todayRule['monthly_salary'] : $salary);
        $todayPercent = (float)$todayRule['percent_rate'];
        $salaryType = $todayMode === 'daily' ? 'daily' : 'monthly';
        $sync = $db->prepare(
            'UPDATE employees SET payment_type=?, salary_type=?, salary=?, service_share_percent=? WHERE id=?'
        );
        $sync->bind_param('ssddi', $todayMode, $salaryType, $todaySalary, $todayPercent, $empId);
        $sync->execute();
        $sync->close();
        ame_emp_hub_audit(
            $db,
            $empId,
            'বেতনের নিয়ম',
            'payment_type',
            ame_emp_hub_pay_mode($emp),
            $mode . ' @ ' . $effectiveFrom,
            (string)($emp['name'] ?? '')
        );
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->bind_param('s', $lockName);
        $release->execute();
        $release->close();
    }
    return ['ok' => true, 'payment_type' => $mode, 'history' => ame_emp_hub_pay_history($db, $emp)];
}

function ame_emp_hub_role_label(array $emp): string
{
    $bits = [];
    $desig = trim((string)($emp['designation'] ?? ''));
    if ($desig !== '') {
        $bits[] = $desig;
    }
    if (!empty($emp['is_engineer'])) {
        $bits[] = 'ইঞ্জিনিয়ার';
    }
    if (!empty($emp['is_service'])) {
        $bits[] = 'সেবা';
    }
    if (!empty($emp['is_repair'])) {
        $bits[] = 'মেরামত';
    }
    if (!empty($emp['is_sales'])) {
        $bits[] = 'বিক্রয়';
    }
    if (trim((string)($emp['login_name'] ?? '')) !== '') {
        $bits[] = 'ব্যবহারকারী';
    }
    $bits = array_values(array_unique($bits));
    return $bits ? implode(' · ', $bits) : 'কর্মী';
}

function ame_emp_hub_audit(mysqli $db, int $empId, string $action, string $field = '', $old = '', $new = '', string $empName = ''): void
{
    $actor = ame_emp_hub_actor();
    $stmt = @$db->prepare(
        'INSERT INTO employee_hub_audit
            (employee_id, employee_name, action, field_name, old_value, new_value, actor_id, actor_name)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $oldS = (string)$old;
    $newS = (string)$new;
    $stmt->bind_param(
        'isssssis',
        $empId,
        $empName,
        $action,
        $field,
        $oldS,
        $newS,
        $actor['id'],
        $actor['name']
    );
    @$stmt->execute();
    $stmt->close();
    if (function_exists('log_activity')) {
        log_activity($action, 'HRM', trim($empName . ' ' . $field . ' ' . $oldS . ' → ' . $newS));
    }
}

function ame_emp_hub_date_range(string $preset, string $from = '', string $to = ''): array
{
    $today = ame_emp_hub_today();
    $tz = new DateTimeZone('Asia/Dhaka');
    $now = DateTime::createFromFormat('Y-m-d', $today, $tz) ?: new DateTime('now', $tz);
    if ($preset === 'yesterday') {
        $d = (clone $now)->modify('-1 day')->format('Y-m-d');
        return [$d, $d];
    }
    if ($preset === 'week') {
        $start = (clone $now)->modify('monday this week')->format('Y-m-d');
        return [$start, $today];
    }
    if ($preset === 'month') {
        return [$now->format('Y-m-01'), $today];
    }
    if ($preset === 'custom' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return [$from, $to];
    }
    return [$today, $today];
}

function ame_emp_hub_visible_sql(string $alias = 'e', bool $deleted = false): string
{
    $p = $alias . '.purged_at';
    $r = $alias . '.removed_at';
    if ($deleted) {
        return '(' . $r . ' IS NOT NULL AND ' . $p . ' IS NULL)';
    }
    return '(' . $p . ' IS NULL AND ' . $r . ' IS NULL)';
}

function ame_emp_hub_set_attendance(mysqli $db, int $empId, bool $on, string $date = ''): array
{
    if ($empId <= 0) {
        throw new Exception('সঠিক কর্মী বাছুন।');
    }
    $emp = ame_emp_hub_row($db, $empId, true);
    if (!$emp) {
        throw new Exception('কর্মী পাওয়া যায়নি বা প্রোফাইল মুছে গেছে।');
    }
    if (trim((string)($emp['purged_at'] ?? '')) !== '') {
        throw new Exception('মুছে যাওয়া প্রোফাইলে নতুন হাজিরা নেওয়া যায় না।');
    }
    $date = trim($date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = ame_emp_hub_today();
    }
    $now = ame_emp_hub_now();
    $clock = substr($now, 11);
    $actor = ame_emp_hub_actor();
    $existing = function_exists('get_attendance_row') ? get_attendance_row($db, $empId, $date) : null;
    $prev = (string)($existing['status'] ?? 'Absent');
    $status = $on ? 'Present' : 'Absent';
    $name = (string)$emp['name'];
    $actorName = $actor['name'];
    $actorId = $actor['id'];

    if ($existing) {
        if ($on) {
            $sql = "UPDATE attendance SET status = ?, check_in = COALESCE(check_in, ?), check_out = NULL,
                    activated_at = COALESCE(activated_at, ?), source = 'employee_hub',
                    previous_status = ?, changed_by = ?, changed_by_name = ?, changed_at = ?
                    WHERE employee_id = ? AND att_date = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ssssissis', $status, $clock, $now, $prev, $actorId, $actorName, $now, $empId, $date);
        } else {
            $sql = "UPDATE attendance SET status = ?, check_out = ?, source = 'employee_hub',
                    previous_status = ?, changed_by = ?, changed_by_name = ?, changed_at = ?
                    WHERE employee_id = ? AND att_date = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('sssissis', $status, $clock, $prev, $actorId, $actorName, $now, $empId, $date);
        }
    } else {
        $checkIn = $on ? $clock : null;
        $checkOut = $on ? null : $clock;
        $activated = $on ? $now : null;
        $sql = "INSERT INTO attendance (employee_id, att_date, check_in, check_out, status, activated_at, source, previous_status, changed_by, changed_by_name, changed_at)
                VALUES (?, ?, ?, ?, ?, ?, 'employee_hub', ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('issssssiss', $empId, $date, $checkIn, $checkOut, $status, $activated, $prev, $actorId, $actorName, $now);
    }
    $stmt->execute();
    $stmt->close();

    ame_emp_hub_audit($db, $empId, $on ? 'হাজিরা চালু' : 'হাজিরা বন্ধ', 'attendance', $prev, $status, $name);

    if ($on) {
        $mode = strtolower((string)($emp['snack_mode'] ?? 'none'));
        $snackAmt = round((float)($emp['daily_snack_amount'] ?? 0), 2);
        if ($mode === 'daily' && $snackAmt > 0) {
            ame_emp_hub_ensure_daily_snack($db, $emp, $date, $snackAmt);
        }
    }

    return ['ok' => true, 'present' => $on, 'status' => $status, 'time' => $now];
}

function ame_emp_hub_ensure_daily_snack(mysqli $db, array $emp, string $date, float $amount): void
{
    $id = (int)$emp['id'];
    $chk = $db->prepare("SELECT id FROM employee_snacks WHERE employee_id = ? AND snack_date = ? AND kind = 'daily' LIMIT 1");
    if (!$chk) {
        return;
    }
    $chk->bind_param('is', $id, $date);
    $chk->execute();
    $have = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($have) {
        return;
    }
    ame_emp_hub_add_snack($db, $id, $date, 'daily', $amount, 'দৈনিক নাস্তা');
}

function ame_emp_hub_add_snack(mysqli $db, int $empId, string $date, string $kind, float $amount, string $note = ''): array
{
    $emp = ame_emp_hub_row($db, $empId, true);
    if (!$emp) {
        throw new Exception('কর্মী পাওয়া যায়নি।');
    }
    $kind = in_array($kind, ['daily', 'extra', 'manual', 'none'], true) ? $kind : 'manual';
    $amount = round(max(0, $amount), 2);
    $actor = ame_emp_hub_actor();
    $name = (string)$emp['name'];
    $cols = ame_emp_hub_cols($db, 'employee_snacks');
    $status = 'pending';
    $actorId = (int) ($actor['id'] ?? 0);
    $actorName = (string) ($actor['name'] ?? '');
    $stmt = null;
    if (isset($cols['payout_status']) && isset($cols['created_by'])) {
        $stmt = @$db->prepare(
            'INSERT INTO employee_snacks (employee_id, employee_name, snack_date, kind, amount, note, created_by, created_by_name, payout_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('isssdsiss', $empId, $name, $date, $kind, $amount, $note, $actorId, $actorName, $status);
        }
    }
    if (!$stmt && isset($cols['payout_status'])) {
        $stmt = @$db->prepare(
            'INSERT INTO employee_snacks (employee_id, employee_name, snack_date, kind, amount, note, payout_status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('isssdss', $empId, $name, $date, $kind, $amount, $note, $status);
        }
    }
    if (!$stmt) {
        $stmt = @$db->prepare(
            'INSERT INTO employee_snacks (employee_id, employee_name, snack_date, kind, amount, note, created_by, created_by_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('isssdsis', $empId, $name, $date, $kind, $amount, $note, $actorId, $actorName);
        }
    }
    if (!$stmt) {
        throw new Exception('নাস্তা খাতায় লেখা যায়নি।');
    }
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    ame_emp_hub_audit($db, $empId, 'Snack', 'snack', '0', (string)$amount, $name);
    return ['ok' => true, 'id' => $id];
}

function ame_emp_hub_row(mysqli $db, int $id, bool $includeDeleted = false): ?array
{
    $vis = $includeDeleted ? '1=1' : ame_emp_hub_visible_sql('e', false);
    $sql = "SELECT e.*, d.dept_name,
                   (SELECT u.username FROM users u WHERE u.employee_id = e.id ORDER BY u.id ASC LIMIT 1) AS login_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.id = ? AND {$vis}
            LIMIT 1";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $stmt = $db->prepare('SELECT e.* FROM employees e WHERE e.id = ? LIMIT 1');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? ame_emp_hub_enrich_person($db, $row) : null;
}

function ame_emp_hub_today_work(mysqli $db, int $empId, string $day): array
{
    ame_emp_hub_ensure_snack_columns($db);
    ame_emp_hub_sync_work_snacks($db, $empId);
    $work = 0.0;
    $pay = 0.0;
    $bonus = 0.0;
    $snack = 0.0;
    $other = 0.0;
    $shop = 0.0;
    $commissionFps = ame_emp_hub_commission_fingerprints($db, $empId);
    $stmt = @$db->prepare(
        'SELECT invoice_ref, memo_no, service_type, work_value, employee_amount, bonus_amount, snack, other_cost, shop_contribution,
                source_type, source_id
         FROM employee_works WHERE employee_id = ? AND work_date = ?'
    );
    if ($stmt) {
        $stmt->bind_param('is', $empId, $day);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                if (ame_emp_hub_manual_work_is_commission_duplicate($r, $commissionFps)) {
                    continue;
                }
                $work += (float)($r['work_value'] ?? 0);
                $pay += (float)($r['employee_amount'] ?? 0);
                $bonus += (float)($r['bonus_amount'] ?? 0);
                $snack += (float)($r['snack'] ?? 0);
                $other += (float)($r['other_cost'] ?? 0);
                $shop += (float)($r['shop_contribution'] ?? 0);
            }
        }
        $stmt->close();
    }
    $sc = @$db->prepare(
        "SELECT COALESCE(SUM(service_amount),0) w, COALESCE(SUM(commission_amount),0) p, COALESCE(SUM(shop_amount),0) h
         FROM service_commissions WHERE employee_id = ? AND DATE(created_at) = ?"
    );
    if ($sc) {
        $sc->bind_param('is', $empId, $day);
        $sc->execute();
        $r = $sc->get_result()->fetch_assoc();
        $sc->close();
        $work += (float)($r['w'] ?? 0);
        $pay += (float)($r['p'] ?? 0);
        $shop += (float)($r['h'] ?? 0);
    }
    $sn = @$db->prepare(
        "SELECT COALESCE(SUM(amount),0) s FROM employee_snacks
         WHERE employee_id = ? AND snack_date = ?
           AND IFNULL(kind,'') <> 'none'
           AND (note IS NULL OR note NOT LIKE 'কাজের নাস্তা #%')"
    );
    if ($sn) {
        $sn->bind_param('is', $empId, $day);
        $sn->execute();
        $snack += (float)($sn->get_result()->fetch_assoc()['s'] ?? 0);
        $sn->close();
    }
    $snack = ame_emp_hub_fold_work_snack($db, $empId, $day, $day, $snack);
    return [
        'work_value' => round($work + ame_emp_hub_box_sum($db, $empId, 'work', $day), 2),
        'employee_amount' => round($pay, 2),
        'bonus' => round($bonus, 2),
        'snack' => round($snack, 2),
        'other_cost' => round($other, 2),
        'shop_contribution' => round($shop, 2),
    ];
}

function ame_emp_hub_today_salary(mysqli $db, array $emp, bool $present, array $todayWork, string $date = ''): float
{
    $date = $date !== '' ? $date : ame_emp_hub_today();
    $rule = ame_emp_hub_pay_rule_on($db, $emp, $date);
    $mode = ame_emp_hub_valid_pay_mode((string)$rule['payment_type']);
    $daily = round((float)($rule['daily_rate'] ?? $emp['salary'] ?? 0), 2);
    $extra = ame_emp_hub_box_sum($db, (int)($emp['id'] ?? 0), 'salary', $date);
    if ($mode === 'daily') {
        return round(($present ? $daily : 0.0) + $extra, 2);
    }
    if ($mode === 'monthly') {
        $monthly = round((float)($rule['monthly_salary'] ?? $emp['salary'] ?? 0), 2);
        $base = $present ? round($monthly / max(1, (int)date('t', strtotime($date))), 2) : 0.0;
        return round($base + $extra, 2);
    }
    if ($mode === 'percentage' || $mode === 'work') {
        return round((float)$todayWork['employee_amount'] + $extra, 2);
    }
    $fixed = $present ? $daily : 0.0;
    return round($fixed + (float)$todayWork['employee_amount'] + $extra, 2);
}

function ame_emp_hub_balance(array $emp, array $todayWork, float $todaySalary): float
{
    $advance = round((float)($emp['advance_balance'] ?? 0), 2);
    $deposit = round((float)($emp['shop_deposit_balance'] ?? 0), 2);
    return round($todaySalary + (float)($todayWork['bonus'] ?? 0) + (float)($todayWork['snack'] ?? 0) - $advance + $deposit, 2);
}

function ame_emp_hub_list(mysqli $db, array $filters): array
{
    ame_emp_hub_schema($db);
    $today = ame_emp_hub_today();
    $deleted = !empty($filters['deleted']);
    $search = trim((string)($filters['q'] ?? ''));
    $pay = strtolower(trim((string)($filters['pay'] ?? '')));
    $role = strtolower(trim((string)($filters['role'] ?? '')));
    $att = strtolower(trim((string)($filters['att'] ?? '')));
    $preset = (string)($filters['range'] ?? 'today');
    [$from, $to] = ame_emp_hub_date_range($preset, (string)($filters['from'] ?? ''), (string)($filters['to'] ?? ''));

    $sql = "SELECT e.*, d.dept_name,
                   a.status AS att_status, a.check_in, a.check_out, a.activated_at,
                   (SELECT u.username FROM users u WHERE u.employee_id = e.id ORDER BY u.id ASC LIMIT 1) AS login_name,
                   (SELECT u.phone FROM users u WHERE u.employee_id = e.id ORDER BY u.id ASC LIMIT 1) AS login_phone,
                   (SELECT u.id FROM users u WHERE u.employee_id = e.id ORDER BY u.id ASC LIMIT 1) AS user_id
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN attendance a ON a.employee_id = e.id AND a.att_date = ?
            WHERE " . ame_emp_hub_visible_sql('e', $deleted);
    $types = 's';
    $params = [$today];

    if ($search !== '') {
        $sql .= " AND (e.name LIKE ? OR e.phone LIKE ? OR e.employee_code LIKE ? OR e.designation LIKE ?
                  OR EXISTS (SELECT 1 FROM employee_works w WHERE w.employee_id = e.id AND (w.customer_name LIKE ? OR w.vehicle_no LIKE ?))
                  OR EXISTS (SELECT 1 FROM users u WHERE u.employee_id = e.id AND (u.username LIKE ? OR IFNULL(u.phone,'') LIKE ? OR CAST(u.id AS CHAR) LIKE ?)))";
        $like = '%' . $search . '%';
        $types .= 'sssssssss';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like);
        if (preg_match('/^#?(\d+)$/', $search, $mId)) {
            $sql = substr($sql, 0, -1) . ' OR e.id = ? OR EXISTS (SELECT 1 FROM users ux WHERE ux.employee_id = e.id AND ux.id = ?))';
            $types .= 'ii';
            $uid = (int)$mId[1];
            array_push($params, $uid, $uid);
        }
    }
    if ($pay !== '' && in_array($pay, ['daily', 'monthly', 'percentage', 'work', 'mixed'], true)) {
        $sql .= ' AND LOWER(COALESCE(e.payment_type, e.salary_type, \'\')) = ?';
        $types .= 's';
        $params[] = $pay;
    }
    if ($role === 'engineer') {
        $sql .= ' AND e.is_engineer = 1';
    } elseif ($role === 'repair') {
        $sql .= ' AND e.is_repair = 1';
    } elseif ($role === 'service') {
        $sql .= ' AND e.is_service = 1';
    } elseif ($role === 'sales') {
        $sql .= ' AND e.is_sales = 1';
    }
    if ($att === 'present') {
        $sql .= " AND a.status IN ('Present','Late')";
    } elseif ($att === 'absent') {
        $sql .= " AND (a.status IS NULL OR a.status NOT IN ('Present','Late'))";
    }
    if (($filters['status'] ?? '') === 'active') {
        $sql .= " AND e.status = 'active'";
    } elseif (($filters['status'] ?? '') === 'inactive') {
        $sql .= " AND e.status = 'inactive'";
    }
    $sql .= ' ORDER BY (a.status IN (\'Present\',\'Late\')) DESC, e.name ASC';

    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $canFin = ame_emp_hub_can_finance($db);
    $self = ame_emp_hub_self_id($db);
    $people = [];
    $summary = [
        'total' => 0, 'present' => 0, 'absent' => 0,
        'work' => 0.0, 'salary' => 0.0, 'commission' => 0.0, 'bonus' => 0.0, 'snack' => 0.0,
        'advance' => 0.0, 'deposit' => 0.0, 'labour' => 0.0, 'shop' => 0.0,
    ];
    while ($e = $res->fetch_assoc()) {
        $present = function_exists('ame_staff_is_on_duty')
            ? ame_staff_is_on_duty((string)($e['att_status'] ?? ''))
            : in_array((string)($e['att_status'] ?? ''), ['Present', 'Late'], true);
        $tw = ame_emp_hub_today_work($db, (int)$e['id'], $today);
        $todayRule = ame_emp_hub_pay_rule_on($db, $e, $today);
        $todaySalary = ame_emp_hub_today_salary($db, $e, $present, $tw, $today);
        $todayPay = round($todaySalary + (float) ($tw['snack'] ?? 0), 2);
        $mode = ame_emp_hub_valid_pay_mode((string)$todayRule['payment_type']);
        $seeMoney = $canFin || $self === (int)$e['id'];
        $loginName = (string)($e['login_name'] ?? '');
        $loginDigits = preg_replace('/\D+/', '', (string)($e['login_phone'] ?? '')) ?? '';
        $empDigits = preg_replace('/\D+/', '', (string)($e['phone'] ?? '')) ?? '';
        if (strlen($loginDigits) >= 10 && strlen($empDigits) >= 10 && substr($loginDigits, -10) !== substr($empDigits, -10)) {
            $loginName = '';
        }
        $e['login_name'] = $loginName;
        $advance = round((float)($e['advance_balance'] ?? 0), 2);
        $deposit = round((float)($e['shop_deposit_balance'] ?? 0), 2);
        $labour = round($todaySalary + $tw['bonus'] + $tw['snack'] + $tw['other_cost'], 2);
        $card = [
            'id' => (int)$e['id'],
            'name' => (string)($e['name'] ?? ''),
            'phone' => (string)($e['phone'] ?? ''),
            'code' => (string)($e['employee_code'] ?? ''),
            'photo' => !empty($e['photo']) ? '/uploads/employees/' . rawurlencode((string)$e['photo']) : '/assets/img/user-placeholder.png',
            'role' => ame_emp_hub_role_label($e),
            'designation' => (string)($e['designation'] ?? ''),
            'dept' => (string)($e['dept_name'] ?? ''),
            'pay_mode' => $mode,
            'pay_label' => ame_emp_hub_pay_label($mode),
            'salary_rate' => $seeMoney ? round((float)(
                $mode === 'daily'
                    ? ($todayRule['daily_rate'] ?? 0)
                    : (in_array($mode, ['monthly', 'mixed'], true) ? ($todayRule['monthly_salary'] ?? 0) : 0)
            ), 2) : null,
            'share_percent' => round((float)($todayRule['percent_rate'] ?? $e['service_share_percent'] ?? 50), 2),
            'present' => $present,
            'att_status' => $present ? 'Present' : 'Absent',
            'check_in' => (string)($e['check_in'] ?? $e['activated_at'] ?? ''),
            'today_work' => $seeMoney ? $tw['work_value'] : null,
            'today_salary' => $seeMoney ? $todaySalary : null,
            'today_pay' => $seeMoney ? $todayPay : null,
            'today_commission' => $seeMoney ? $tw['employee_amount'] : null,
            'today_bonus' => $seeMoney ? $tw['bonus'] : null,
            'today_snack' => $seeMoney ? round((float) ($tw['snack'] ?? 0), 2) : null,
            'advance' => $seeMoney ? $advance : null,
            'advance_previous' => $seeMoney ? ame_emp_hub_advance_split($db, (int)$e['id'], $today, $advance)['previous'] : null,
            'deposit' => $seeMoney ? $deposit : null,
            'balance' => $seeMoney ? ame_emp_hub_balance($e, $tw, $todaySalary) : null,
            'login_name' => (string)($e['login_name'] ?? ''),
            'user_id' => (int)($e['user_id'] ?? 0),
            'status' => (string)($e['status'] ?? 'active'),
            'snack_mode' => (string)($e['snack_mode'] ?? 'none'),
            'daily_snack' => round((float)($e['daily_snack_amount'] ?? 0), 2),
            'is_engineer' => (int)($e['is_engineer'] ?? 0),
            'is_service' => (int)($e['is_service'] ?? 0),
            'is_repair' => (int)($e['is_repair'] ?? 0),
            'is_sales' => (int)($e['is_sales'] ?? 0),
            'deleted' => $deleted,
            'can_money' => $seeMoney,
        ];
        $people[] = $card;
        $summary['total']++;
        if ($present) {
            $summary['present']++;
        } else {
            $summary['absent']++;
        }
        if ($seeMoney) {
            $summary['work'] += $tw['work_value'];
            $summary['salary'] += $todaySalary;
            $summary['commission'] += $tw['employee_amount'];
            $summary['bonus'] += $tw['bonus'];
            $summary['snack'] += $tw['snack'];
            $summary['advance'] += $advance;
            $summary['deposit'] += $deposit;
            $summary['labour'] += $labour;
            $summary['shop'] += $tw['shop_contribution'] > 0 ? $tw['shop_contribution'] : max(0, $tw['work_value'] - $labour);
        }
    }
    $stmt->close();

    $advToday = 0.0;
    $draftSkip = isset(ame_emp_hub_cols($db, 'employee_advances')['status']) ? " AND COALESCE(status,'posted') <> 'draft'" : '';
    $ad = @$db->prepare("SELECT COALESCE(SUM(amount),0) t FROM employee_advances WHERE kind = 'give' AND advance_date = ?" . $draftSkip);
    if ($ad) {
        $ad->bind_param('s', $today);
        $ad->execute();
        $advToday = (float)($ad->get_result()->fetch_assoc()['t'] ?? 0);
        $ad->close();
    }

    foreach ($summary as $k => $v) {
        if (is_float($v)) {
            $summary[$k] = round($v, 2);
        }
    }
    $summary['advance_today'] = round($advToday, 2);
    $summary['from'] = $from;
    $summary['to'] = $to;
    $summary['today'] = $today;

    return ['people' => $people, 'summary' => $summary];
}

function ame_emp_hub_parse_time(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw)) {
        return strlen($raw) === 5 ? ($raw . ':00') : $raw;
    }
    return substr(ame_emp_hub_now(), 11);
}

function ame_emp_hub_work_row(mysqli $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = @$db->prepare('SELECT * FROM employee_works WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function ame_emp_hub_save_work(mysqli $db, array $in): array
{
    $empId = (int)($in['employee_id'] ?? 0);
    $workId = (int)($in['id'] ?? $in['work_id'] ?? 0);
    $existing = $workId > 0 ? ame_emp_hub_work_row($db, $workId) : null;
    if ($existing) {
        if (($existing['payout_status'] ?? 'pending') === 'paid' || (int)($existing['salary_id'] ?? 0) > 0) {
            throw new Exception('এই কাজের বেতন পরিশোধ হয়েছে। বেতন রিভার্স না করে কাজ বদলানো যাবে না।');
        }
        $empId = (int)$existing['employee_id'];
    }
    $emp = ame_emp_hub_row($db, $empId, false);
    if (!$emp) {
        throw new Exception('সক্রিয় কর্মী পাওয়া যায়নি।');
    }
    $date = (string)($in['work_date'] ?? ame_emp_hub_today());
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = ame_emp_hub_today();
    }
    $time = ame_emp_hub_parse_time((string)($in['work_time'] ?? ''));
    $present = function_exists('ame_staff_is_duty_active') ? ame_staff_is_duty_active($db, $empId) : true;
    if (!$existing && !$present && $date === ame_emp_hub_today()) {
        throw new Exception('আজ হাজিরা বন্ধ। আগে হাজিরা চালু করুন।');
    }
    $value = round(max(0, (float)($in['work_value'] ?? 0)), 2);
    $payRule = ame_emp_hub_pay_rule_on($db, $emp, $date);
    $pct = max(0, min(100, (float)($payRule['percent_rate'] ?? $emp['service_share_percent'] ?? 50)));
    $mode = ame_emp_hub_valid_pay_mode((string)$payRule['payment_type']);
    $empAmt = round($value * $pct / 100, 2);
    if ($mode === 'daily' || $mode === 'monthly') {
        $empAmt = 0.0;
    }
    $snack = round(max(0, (float)($in['snack'] ?? 0)), 2);
    $bonus = round(max(0, (float)($in['bonus_amount'] ?? 0)), 2);
    $other = round(max(0, (float)($in['other_cost'] ?? 0)), 2);
    $shop = round($value - $empAmt - $bonus, 2);
    $actor = ame_emp_hub_actor();
    $custId = (int)($in['customer_id'] ?? 0);
    $custName = trim((string)($in['customer_name'] ?? ''));
    if ($custId > 0 && $custName === '') {
        $c = @$db->prepare('SELECT name, phone FROM customers WHERE id = ? LIMIT 1');
        if ($c) {
            $c->bind_param('i', $custId);
            $c->execute();
            $customer = $c->get_result()->fetch_assoc();
            $custName = (string)($customer['name'] ?? '');
            if (trim((string)($in['customer_phone'] ?? '')) === '') {
                $in['customer_phone'] = (string)($customer['phone'] ?? '');
            }
            $c->close();
        }
    }
    $name = (string)$emp['name'];
    $vehicle = trim((string)($in['vehicle'] ?? ''));
    $vehicleNo = trim((string)($in['vehicle_no'] ?? ''));
    $service = trim((string)($in['service_type'] ?? ''));
    $desc = trim((string)($in['description'] ?? ''));
    $custPhone = trim((string)($in['customer_phone'] ?? ''));
    $invoiceRef = trim((string)($in['invoice_ref'] ?? ''));
    $memoNo = trim((string)($in['memo_no'] ?? ''));
    $imei = trim((string)($in['imei_serial'] ?? ''));
    if ($custId <= 0) {
        $custId = 0;
    }
    $now = ame_emp_hub_now();

    $invKey = ame_emp_hub_work_invoice_key($invoiceRef, $memoNo);
    if ($invKey !== '' || $invoiceRef !== '') {
        if ($invKey !== '') {
            $dupLine = @$db->prepare(
                "SELECT id FROM employee_works
                 WHERE employee_id = ? AND id <> ?
                   AND TRIM(service_type) = ?
                   AND ABS(work_value - ?) < 0.009
                   AND (
                        UPPER(TRIM(invoice_ref)) = UPPER(?)
                        OR UPPER(TRIM(memo_no)) = UPPER(?)
                        OR UPPER(TRIM(invoice_ref)) LIKE CONCAT('%-', ?)
                        OR UPPER(TRIM(memo_no)) = ?
                   )
                 LIMIT 1"
            );
            if ($dupLine) {
                $dupLine->bind_param('iisdssss', $empId, $workId, $service, $value, $invoiceRef, $memoNo, $invKey, $invKey);
                $dupLine->execute();
                $duplicateLine = $dupLine->get_result()->fetch_assoc();
                $dupLine->close();
                if ($duplicateLine) {
                    throw new Exception('এই কর্মীর একই ইনভয়েসে একই ধরন ও একই টাকার কাজ আগেই আছে। ডাবল এন্ট্রি রাখা হয়নি।');
                }
            }
        }

        $autoWork = @$db->prepare(
            "SELECT sc.id
             FROM service_commissions sc
             LEFT JOIN sales s ON sc.source_type = 'sale_service' AND s.id = sc.sale_id
             LEFT JOIN workshop_jobs w ON sc.source_type = 'workshop_job' AND w.id = sc.source_id
             WHERE sc.employee_id = ?
               AND TRIM(sc.service_type) = ?
               AND ABS(sc.service_amount - ?) < 0.009
               AND (
                    UPPER(TRIM(s.invoice_number)) = UPPER(?)
                    OR UPPER(TRIM(w.job_no)) = UPPER(?)
                    OR UPPER(TRIM(s.invoice_number)) = UPPER(?)
                    OR (? <> '' AND UPPER(TRIM(s.invoice_number)) LIKE CONCAT('%-', ?))
               )
             LIMIT 1"
        );
        if ($autoWork) {
            $invoiceMatch = $invoiceRef !== '' ? $invoiceRef : ($memoNo !== '' ? $memoNo : $invKey);
            $autoWork->bind_param(
                'isdsssss',
                $empId,
                $service,
                $value,
                $invoiceRef,
                $invoiceRef,
                $invoiceMatch,
                $invKey,
                $invKey
            );
            $autoWork->execute();
            $automatic = $autoWork->get_result()->fetch_assoc();
            $autoWork->close();
            if ($automatic) {
                throw new Exception('বিক্রয়/মেরামত থেকে এই কাজের লাইন স্বয়ংক্রিয় কমিশনে আছে। হাতে আবার যোগ করা হয়নি।');
            }
        }
    } elseif (!$existing) {
        $recent = @$db->prepare(
            "SELECT id FROM employee_works
             WHERE employee_id = ? AND work_date = ?
               AND ABS(work_value - ?) < 0.009
               AND customer_name = ? AND vehicle_no = ? AND service_type = ?
               AND created_at >= (NOW() - INTERVAL 60 SECOND)
             ORDER BY id DESC LIMIT 1"
        );
        if ($recent) {
            $recent->bind_param('isdsss', $empId, $date, $value, $custName, $vehicleNo, $service);
            $recent->execute();
            $duplicate = $recent->get_result()->fetch_assoc();
            $recent->close();
            if ($duplicate) {
                throw new Exception('একই কাজ গত ১ মিনিটে রাখা হয়েছে। ডাবল সাবমিট গ্রহণ করা হয়নি।');
            }
        }
    }

    if ($existing) {
        $stmt = $db->prepare(
            'UPDATE employee_works SET work_date=?, work_time=?, customer_id=?, customer_name=?, customer_phone=?, vehicle=?, vehicle_no=?,
                service_type=?, description=?, invoice_ref=?, memo_no=?, imei_serial=?, work_value=?, employee_percent=?, employee_amount=?, bonus_amount=?, snack=?, other_cost=?,
                shop_contribution=?, updated_at=?, updated_by_name=?
             WHERE id=? AND employee_id=?'
        );
        $stmt->bind_param(
            'ssisssssssssdddddddssii',
            $date, $time, $custId, $custName, $custPhone, $vehicle, $vehicleNo, $service, $desc, $invoiceRef, $memoNo, $imei,
            $value, $pct, $empAmt, $bonus, $snack, $other, $shop, $now, $actor['name'], $workId, $empId
        );
        $stmt->execute();
        $stmt->close();
        $snackNote = 'কাজের নাস্তা #' . $workId;
        $snackRow = null;
        $snackFind = @$db->prepare('SELECT id FROM employee_snacks WHERE employee_id = ? AND note = ? ORDER BY id ASC LIMIT 1');
        if ($snackFind) {
            $snackFind->bind_param('is', $empId, $snackNote);
            $snackFind->execute();
            $snackRow = $snackFind->get_result()->fetch_assoc();
            $snackFind->close();
        }
        if ($snack > 0 && $snackRow) {
            $snackId = (int)$snackRow['id'];
            $snackUpdate = $db->prepare('UPDATE employee_snacks SET snack_date = ?, amount = ? WHERE id = ?');
            $snackUpdate->bind_param('sdi', $date, $snack, $snackId);
            $snackUpdate->execute();
            $snackUpdate->close();
        } elseif ($snack > 0) {
            try {
                ame_emp_hub_add_snack($db, $empId, $date, 'extra', $snack, $snackNote);
            } catch (Throwable $e) {
                error_log('work snack row: ' . $e->getMessage());
            }
        } elseif ($snackRow) {
            $snackId = (int)$snackRow['id'];
            $snackDelete = $db->prepare('DELETE FROM employee_snacks WHERE id = ? LIMIT 1');
            $snackDelete->bind_param('i', $snackId);
            $snackDelete->execute();
            $snackDelete->close();
        }
        ame_emp_hub_sync_work_snacks($db, $empId);
        ame_emp_hub_audit($db, $empId, 'কাজ সংশোধন', 'work_value', (string)($existing['work_value'] ?? '0'), (string)$value, $name);
        return ['ok' => true, 'id' => $workId, 'employee_amount' => $empAmt, 'shop_contribution' => $shop, 'updated' => true];
    }

    $stmt = $db->prepare(
        'INSERT INTO employee_works
            (employee_id, employee_name, work_date, work_time, customer_id, customer_name, customer_phone, vehicle, vehicle_no, service_type, description,
             invoice_ref, memo_no, imei_serial, work_value, employee_percent, employee_amount, bonus_amount, snack, other_cost, shop_contribution, created_by, created_by_name)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'isssisssssssssdddddddis',
        $empId,
        $name,
        $date,
        $time,
        $custId,
        $custName,
        $custPhone,
        $vehicle,
        $vehicleNo,
        $service,
        $desc,
        $invoiceRef,
        $memoNo,
        $imei,
        $value,
        $pct,
        $empAmt,
        $bonus,
        $snack,
        $other,
        $shop,
        $actor['id'],
        $actor['name']
    );
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
        if ($snack > 0) {
            try {
                ame_emp_hub_add_snack($db, $empId, $date, 'extra', $snack, 'কাজের নাস্তা #' . $id);
            } catch (Throwable $e) {
                error_log('work snack row: ' . $e->getMessage());
            }
        }
        ame_emp_hub_sync_work_snacks($db, $empId);
    ame_emp_hub_audit($db, $empId, 'কাজ', 'work_value', '0', (string)$value, $name);
    return ['ok' => true, 'id' => $id, 'employee_amount' => $empAmt, 'shop_contribution' => $shop];
}

function ame_emp_hub_delete_work(mysqli $db, int $workId): array
{
    $row = ame_emp_hub_work_row($db, $workId);
    if (!$row) {
        throw new Exception('কাজ পাওয়া যায়নি।');
    }
    if (($row['payout_status'] ?? 'pending') === 'paid' || (int)($row['salary_id'] ?? 0) > 0) {
        throw new Exception('এই কাজের বেতন পরিশোধ হয়েছে। আগে সংশ্লিষ্ট বেতন রিভার্স করুন।');
    }
    $empId = (int)$row['employee_id'];
    $note = 'কাজের নাস্তা #' . $workId;
    $sn = @$db->prepare("DELETE FROM employee_snacks WHERE employee_id = ? AND note = ?");
    if ($sn) {
        $sn->bind_param('is', $empId, $note);
        $sn->execute();
        $sn->close();
    }
    $del = $db->prepare('DELETE FROM employee_works WHERE id = ? LIMIT 1');
    $del->bind_param('i', $workId);
    $del->execute();
    $del->close();
    ame_emp_hub_audit($db, $empId, 'কাজ মুছা', 'work', (string)($row['work_value'] ?? '0'), '0', (string)($row['customer_name'] ?? ''));
    return ['ok' => true, 'employee_id' => $empId];
}

function ame_emp_hub_save_person(mysqli $db, array $in, array $files = []): array
{
    $id = (int)($in['id'] ?? 0);
    $name = trim((string)($in['name'] ?? ''));
    $phone = preg_replace('/\s+/', '', trim((string)($in['phone'] ?? '')));
    if ($name === '' || $phone === '') {
        throw new Exception('নাম ও মোবাইল আবশ্যক। এক ব্যক্তির এক নম্বর, এক প্রোফাইল।');
    }
    $dup = ame_emp_hub_duplicate($db, $phone, (string)($in['employee_code'] ?? ''), $id);
    if ($dup) {
        throw new Exception('এই মোবাইল/কর্মী আইডি ইতিমধ্যে আছে: ' . $dup['name'] . ' (' . ($dup['employee_code'] ?? '') . ')। একই ব্যক্তির দ্বিতীয় প্রোফাইল তৈরি করা যাবে না।');
    }
    $pay = strtolower((string)($in['payment_type'] ?? 'percentage'));
    if (!in_array($pay, ['daily', 'monthly', 'percentage', 'work', 'mixed'], true)) {
        $pay = 'percentage';
    }
    $salaryType = in_array($pay, ['daily'], true) ? 'daily' : 'monthly';
    $salary = round(max(0, (float)($in['salary'] ?? 0)), 2);
    $share = max(0, min(100, (float)($in['service_share_percent'] ?? 50)));
    $desig = trim((string)($in['designation'] ?? ''));
    $addr = trim((string)($in['address'] ?? ''));
    $dept = (int)($in['department_id'] ?? 0);
    $join = (string)($in['joining_date'] ?? ame_emp_hub_today());
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $join)) {
        $join = ame_emp_hub_today();
    }
    $status = (($in['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';
    $snackMode = (string)($in['snack_mode'] ?? 'none');
    if (!in_array($snackMode, ['daily', 'extra', 'manual', 'none'], true)) {
        $snackMode = 'none';
    }
    $dailySnack = round(max(0, (float)($in['daily_snack_amount'] ?? 0)), 2);
    $isEng = !empty($in['is_engineer']) ? 1 : 0;
    $isSvc = !empty($in['is_service']) ? 1 : 0;
    $isRep = !empty($in['is_repair']) ? 1 : 0;
    $isSales = !empty($in['is_sales']) ? 1 : 0;
    $userRole = trim((string)($in['user_role'] ?? $in['login_role'] ?? ''));

    if ($id > 0) {
        $old = ame_emp_hub_row($db, $id, true);
        if (!$old) {
            throw new Exception('কর্মী পাওয়া যায়নি।');
        }
        $oldRule = ame_emp_hub_pay_rule_on($db, $old, ame_emp_hub_today());
        $stmt = $db->prepare(
            "UPDATE employees SET name=?, phone=?, address=?, designation=?, salary=?, department_id=?, salary_type=?,
                payment_type=?, service_share_percent=?, snack_mode=?, daily_snack_amount=?,
                is_engineer=?, is_service=?, is_repair=?, is_sales=?, user_role=?, joining_date=?, status=?, name_snapshot=?
             WHERE id=?"
        );
        $stmt->bind_param(
            'ssssdissdsdiiiissssi',
            $name, $phone, $addr, $desig, $salary, $dept, $salaryType, $pay, $share, $snackMode, $dailySnack,
            $isEng, $isSvc, $isRep, $isSales, $userRole, $join, $status, $name, $id
        );
        $stmt->execute();
        $stmt->close();
        if (isset($in['employee_code']) && function_exists('resolve_employee_code')) {
            $code = trim((string)$in['employee_code']);
            if ($code !== '' && $code !== (string)($old['employee_code'] ?? '')) {
                $ok = resolve_employee_code($db, $code);
                if (!empty($ok['ok'])) {
                    $up = $db->prepare('UPDATE employees SET employee_code = ? WHERE id = ?');
                    $up->bind_param('si', $ok['code'], $id);
                    $up->execute();
                    $up->close();
                }
            }
        }
        ame_emp_hub_audit($db, $id, 'Profile update', 'salary', (string)($old['salary'] ?? ''), (string)$salary, $name);
        $oldMode = ame_emp_hub_valid_pay_mode((string)($oldRule['payment_type'] ?? ame_emp_hub_pay_mode($old)));
        $oldSalary = $oldMode === 'daily'
            ? (float)($oldRule['daily_rate'] ?? $old['salary'] ?? 0)
            : (in_array($oldMode, ['monthly', 'mixed'], true) ? (float)($oldRule['monthly_salary'] ?? $old['salary'] ?? 0) : 0.0);
        $oldPercent = (float)($oldRule['percent_rate'] ?? $old['service_share_percent'] ?? 50);
        if (
            $pay !== $oldMode
            || (in_array($pay, ['daily', 'monthly', 'mixed'], true) && abs($salary - $oldSalary) > 0.009)
            || (in_array($pay, ['percentage', 'work', 'mixed'], true) && abs($share - $oldPercent) > 0.009)
        ) {
            $changedEmp = $old;
            $changedEmp['name'] = $name;
            ame_emp_hub_save_pay_rule(
                $db,
                $changedEmp,
                $pay,
                $salary,
                $share,
                (string)($in['pay_effective_from'] ?? ame_emp_hub_today()),
                (string)($in['pay_change_note'] ?? '')
            );
        }
        ame_emp_hub_apply_photo($db, $id, $files);
        ame_emp_hub_sync_login($db, $id, $in);
        if (function_exists('sync_employee_as_engineer')) {
            sync_employee_as_engineer($db, $id);
        }
        return ['ok' => true, 'id' => $id];
    }

    $code = '';
    if (function_exists('resolve_employee_code')) {
        $res = resolve_employee_code($db, (string)($in['employee_code'] ?? ''));
        if (empty($res['ok'])) {
            throw new Exception((string)($res['error'] ?? 'কর্মী আইডি ব্যবহার করা যায়নি।'));
        }
        $code = (string)$res['code'];
    } elseif (function_exists('generate_employee_code')) {
        $code = generate_employee_code($db);
    }
    $stmt = $db->prepare(
        "INSERT INTO employees
            (name, phone, address, designation, salary, department_id, salary_type, payment_type, employee_code, joining_date, status,
             service_share_percent, snack_mode, daily_snack_amount, is_engineer, is_service, is_repair, is_sales, user_role, name_snapshot)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'ssssdisssssdsdiiiiss',
        $name, $phone, $addr, $desig, $salary, $dept, $salaryType, $pay, $code, $join, $status,
        $share, $snackMode, $dailySnack, $isEng, $isSvc, $isRep, $isSales, $userRole, $name
    );
    if (!$stmt->execute()) {
        throw new Exception('কর্মী সেভ হয়নি: ' . $db->error);
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();
    ame_emp_hub_audit($db, $newId, 'Employee create', 'profile', '', $name, $name);
    $createdEmp = ame_emp_hub_row($db, $newId, true);
    if ($createdEmp) {
        ame_emp_hub_save_pay_rule(
            $db,
            $createdEmp,
            $pay,
            $salary,
            $share,
            (string)($in['pay_effective_from'] ?? $join),
            (string)($in['pay_change_note'] ?? 'প্রাথমিক বেতনের নিয়ম')
        );
    }
    ame_emp_hub_apply_photo($db, $newId, $files);
    ame_emp_hub_sync_login($db, $newId, $in);
    if (function_exists('sync_employee_as_engineer')) {
        sync_employee_as_engineer($db, $newId);
    }
    if (function_exists('remember_position') && $desig !== '') {
        remember_position($db, $desig);
    }
    return ['ok' => true, 'id' => $newId, 'code' => $code];
}

function ame_emp_hub_duplicate(mysqli $db, string $phone, string $code, int $exceptId = 0): ?array
{
    $phone = preg_replace('/\s+/', '', $phone);
    $code = trim($code);
    if ($phone !== '') {
        $sql = 'SELECT id, name, employee_code, phone FROM employees WHERE REPLACE(phone, \' \', \'\') = ? AND id <> ? AND (purged_at IS NULL) LIMIT 1';
        $stmt = @$db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $phone, $exceptId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }
    }
    if ($code !== '') {
        $stmt = @$db->prepare('SELECT id, name, employee_code, phone FROM employees WHERE employee_code = ? AND id <> ? AND (purged_at IS NULL) LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('si', $code, $exceptId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return $row;
            }
        }
    }
    return null;
}

function ame_emp_hub_pending_work_pay(mysqli $db, int $empId, string $throughDate, bool $dailyOnly = false, bool $lock = true): array
{
    $emp = ame_emp_hub_row($db, $empId, true);
    $lockFile = __DIR__ . '/worker_work_lock.php';
    if (is_file($lockFile)) {
        require_once $lockFile;
        ame_worker_work_lock_ensure_schema($db);
    }
    $commissionFps = ame_emp_hub_commission_fingerprints($db, $empId);
    $sql = "SELECT id, work_date, work_value, employee_amount, bonus_amount,
                   invoice_ref, memo_no, service_type, source_type, source_id
            FROM employee_works
            WHERE employee_id = ? AND payout_status = 'pending'";
    if ($dailyOnly) {
        $sql .= ' AND work_date = ?';
    } else {
        $sql .= ' AND work_date <= ?';
    }
    $sql .= ' ORDER BY id ASC';
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = @$db->prepare($sql);
    if (!$stmt) {
        return ['ids' => [], 'commission' => 0.0, 'bonus' => 0.0, 'work_value' => 0.0];
    }
    $stmt->bind_param('is', $empId, $throughDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    $commission = 0.0;
    $bonus = 0.0;
    $workValue = 0.0;
    while ($row = $result->fetch_assoc()) {
        if (ame_emp_hub_manual_work_is_commission_duplicate($row, $commissionFps)) {
            continue;
        }
        $ids[] = (int)$row['id'];
        $workMode = $emp
            ? ame_emp_hub_valid_pay_mode((string)ame_emp_hub_pay_rule_on($db, $emp, (string)$row['work_date'])['payment_type'])
            : 'percentage';
        if (function_exists('ame_worker_mode_uses_manual_work')) {
            if (!ame_worker_mode_uses_manual_work($workMode)) {
                $bonus += (float)($row['bonus_amount'] ?? 0);
                continue;
            }
        } elseif (!in_array($workMode, ['percentage', 'work', 'mixed'], true)) {
            $bonus += (float)($row['bonus_amount'] ?? 0);
            continue;
        }
        if (in_array($workMode, ['percentage', 'work', 'mixed'], true)) {
            $commission += (float)($row['employee_amount'] ?? 0);
            $workValue += (float)($row['work_value'] ?? 0);
        }
        $bonus += (float)($row['bonus_amount'] ?? 0);
    }
    $stmt->close();
    return [
        'ids' => $ids,
        'commission' => round($commission, 2),
        'bonus' => round($bonus, 2),
        'work_value' => round($workValue, 2),
    ];
}

/**
 * কাজের এন্ট্রির নাস্তা খাতায় না থাকলে যোগ করে — কাজ বেতন হয়ে গেলেও বাদ যায় না।
 */
function ame_emp_hub_fold_work_snack(mysqli $db, int $empId, string $fromDate, string $toDate, float $already): float
{
    ame_emp_hub_ensure_snack_columns($db);
    $workCols = ame_emp_hub_cols($db, 'employee_works');
    if (!isset($workCols['snack'])) {
        return round($already, 2);
    }
    $wStmt = @$db->prepare(
        'SELECT COALESCE(SUM(snack),0) s FROM employee_works
         WHERE employee_id = ? AND snack > 0.009 AND work_date >= ? AND work_date <= ?'
    );
    $workSnack = 0.0;
    if ($wStmt) {
        $wStmt->bind_param('iss', $empId, $fromDate, $toDate);
        $wStmt->execute();
        $workSnack = round((float) ($wStmt->get_result()->fetch_assoc()['s'] ?? 0), 2);
        $wStmt->close();
    }
    $noted = 0.0;
    $nStmt = @$db->prepare(
        "SELECT COALESCE(SUM(amount),0) s FROM employee_snacks
         WHERE employee_id = ? AND snack_date >= ? AND snack_date <= ? AND note LIKE 'কাজের নাস্তা #%'"
    );
    if ($nStmt) {
        $nStmt->bind_param('iss', $empId, $fromDate, $toDate);
        $nStmt->execute();
        $noted = round((float) ($nStmt->get_result()->fetch_assoc()['s'] ?? 0), 2);
        $nStmt->close();
    }
    if ($workSnack > $noted + 0.009) {
        return round($already + ($workSnack - $noted), 2);
    }
    return round($already, 2);
}

/**
 * কাজের এন্ট্রির অতিরিক্ত নাস্তা বেতনের নাস্তা-খাতায় তোলে, যাতে পেমেন্টে ৫০০+১০০=৬০০ দেখায়।
 * কাজ আগে বেতন হয়ে গেলেও অপরিশোধিত নাস্তা তোলে — তাই পরের বেতনে যোগ হয়।
 */
function ame_emp_hub_sync_work_snacks(mysqli $db, int $empId): void
{
    $empId = (int) $empId;
    if ($empId <= 0) {
        return;
    }
    ame_emp_hub_ensure_snack_columns($db);
    $workCols = ame_emp_hub_cols($db, 'employee_works');
    $snackCols = ame_emp_hub_cols($db, 'employee_snacks');
    if ($workCols === [] || $snackCols === [] || !isset($workCols['snack'])) {
        return;
    }
    $sql = 'SELECT id, work_date, snack FROM employee_works WHERE employee_id = ? AND snack > 0.009';
    $stmt = @$db->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $empId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();
    if ($rows === []) {
        return;
    }
    $emp = ame_emp_hub_row($db, $empId, true);
    $name = (string) ($emp['name'] ?? '');
    $statusCol = isset($snackCols['payout_status']);
    foreach ($rows as $row) {
        $workId = (int) ($row['id'] ?? 0);
        $amount = round((float) ($row['snack'] ?? 0), 2);
        $date = (string) ($row['work_date'] ?? '');
        if ($workId <= 0 || $amount <= 0.009 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            continue;
        }
        $note = 'কাজের নাস্তা #' . $workId;
        $found = null;
        $findSql = $statusCol
            ? 'SELECT id, amount, snack_date, payout_status FROM employee_snacks WHERE employee_id = ? AND note = ? ORDER BY id ASC LIMIT 1'
            : 'SELECT id, amount, snack_date FROM employee_snacks WHERE employee_id = ? AND note = ? ORDER BY id ASC LIMIT 1';
        $find = @$db->prepare($findSql);
        if ($find) {
            $find->bind_param('is', $empId, $note);
            $find->execute();
            $found = $find->get_result()->fetch_assoc();
            $find->close();
        }
        if ($found) {
            $paid = $statusCol && strtolower((string) ($found['payout_status'] ?? '')) === 'paid';
            if ($paid) {
                continue;
            }
            $snackId = (int) $found['id'];
            if (abs((float) $found['amount'] - $amount) > 0.009 || (string) $found['snack_date'] !== $date) {
                $upd = @$db->prepare('UPDATE employee_snacks SET snack_date = ?, amount = ?, kind = ? WHERE id = ?');
                if ($upd) {
                    $kind = 'extra';
                    $upd->bind_param('sdsi', $date, $amount, $kind, $snackId);
                    $upd->execute();
                    $upd->close();
                }
            }
            continue;
        }
        if ($statusCol) {
            $ins = @$db->prepare(
                'INSERT INTO employee_snacks (employee_id, employee_name, snack_date, kind, amount, note, payout_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if ($ins) {
                $kind = 'extra';
                $status = 'pending';
                $ins->bind_param('isssdss', $empId, $name, $date, $kind, $amount, $note, $status);
                $ins->execute();
                $ins->close();
                continue;
            }
        }
        $ins = @$db->prepare(
            'INSERT INTO employee_snacks (employee_id, employee_name, snack_date, kind, amount, note)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        if ($ins) {
            $kind = 'extra';
            $ins->bind_param('isssds', $empId, $name, $date, $kind, $amount, $note);
            $ins->execute();
            $ins->close();
        }
    }
}

/**
 * অপরিশোধিত নাস্তা — কাজের এন্ট্রি ও আলাদা নাস্তা, পরে যোগ করলেও পরের পেমেন্টে যোগ হয়।
 *
 * @return array{ids:list<int>,amount:float}
 */
function ame_emp_hub_pending_snack_pay(mysqli $db, int $empId, string $throughDate, bool $onlyDay = false, bool $lock = false): array
{
    $empty = ['ids' => [], 'amount' => 0.0];
    ame_emp_hub_ensure_snack_columns($db);
    ame_emp_hub_sync_work_snacks($db, $empId);
    $cols = ame_emp_hub_cols($db, 'employee_snacks');
    if ($cols === []) {
        $from = $onlyDay ? $throughDate : '1970-01-01';
        return [
            'ids' => [],
            'amount' => ame_emp_hub_fold_work_snack($db, $empId, $from, $throughDate, 0.0),
        ];
    }
    $cmp = $onlyDay ? '=' : '<=';
    $statusSql = isset($cols['payout_status'])
        ? " AND (payout_status IS NULL OR payout_status = '' OR payout_status = 'pending')"
        : '';
    $sql = "SELECT id, amount FROM employee_snacks
            WHERE employee_id = ? AND snack_date {$cmp} ? AND amount > 0.009
              AND IFNULL(kind,'') <> 'none'{$statusSql}
            ORDER BY id ASC";
    if ($lock && isset($cols['payout_status'])) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = @$db->prepare($sql);
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('is', $empId, $throughDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    $amount = 0.0;
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['id'];
        $amount += (float) ($row['amount'] ?? 0);
    }
    $stmt->close();
    $from = $onlyDay ? $throughDate : '1970-01-01';
    $workCols = ame_emp_hub_cols($db, 'employee_works');
    if (isset($workCols['snack'])) {
        $leftover = @$db->prepare(
            "SELECT w.id, w.work_date, w.snack
             FROM employee_works w
             WHERE w.employee_id = ? AND w.snack > 0.009 AND w.work_date {$cmp} ?
               AND NOT EXISTS (
                   SELECT 1 FROM employee_snacks s
                   WHERE s.employee_id = w.employee_id
                     AND s.note = CONCAT('কাজের নাস্তা #', w.id)
               )"
        );
        if ($leftover) {
            $leftover->bind_param('is', $empId, $throughDate);
            $leftover->execute();
            $leftRes = $leftover->get_result();
            $rows = [];
            if ($leftRes) {
                while ($leftRow = $leftRes->fetch_assoc()) {
                    $rows[] = $leftRow;
                }
            }
            $leftover->close();
            foreach ($rows as $row) {
                $workId = (int) ($row['id'] ?? 0);
                $snackAmt = round((float) ($row['snack'] ?? 0), 2);
                $snackDate = (string) ($row['work_date'] ?? $throughDate);
                if ($workId <= 0 || $snackAmt <= 0.009) {
                    continue;
                }
                try {
                    $made = ame_emp_hub_add_snack($db, $empId, $snackDate, 'extra', $snackAmt, 'কাজের নাস্তা #' . $workId);
                    $newId = (int) ($made['id'] ?? 0);
                    if ($newId > 0) {
                        $ids[] = $newId;
                        $amount += $snackAmt;
                    }
                } catch (Throwable $e) {
                    error_log('pending work snack: ' . $e->getMessage());
                }
            }
        }
    }
    $amount = ame_emp_hub_fold_work_snack($db, $empId, $from, $throughDate, $amount);
    return [
        'ids' => array_values(array_unique(array_filter($ids))),
        'amount' => round($amount, 2),
    ];
}

/**
 * অপরিশোধিত সার্ভিস কমিশন। $onlyDay হলে শুধু ওই তারিখ।
 *
 * @return array{ids:list<int>,amount:float,service_value:float}
 */
function ame_emp_hub_pending_service_pay(mysqli $db, array $emp, int $empId, string $throughDate, bool $onlyDay = false, bool $lock = false): array
{
    $empty = ['ids' => [], 'amount' => 0.0, 'service_value' => 0.0];
    $cmp = $onlyDay ? '=' : '<=';
    $sql = "SELECT id, commission_amount, service_amount, DATE(created_at) AS work_date
            FROM service_commissions
            WHERE employee_id = ? AND status = 'pending' AND DATE(created_at) {$cmp} ?
            ORDER BY id ASC";
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = @$db->prepare($sql);
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('is', $empId, $throughDate);
    $stmt->execute();
    $rows = $stmt->get_result();
    $ids = [];
    $amount = 0.0;
    $value = 0.0;
    while ($row = $rows->fetch_assoc()) {
        $ids[] = (int)$row['id'];
        $rule = ame_emp_hub_pay_rule_on($db, $emp, (string)($row['work_date'] ?? $throughDate));
        $mode = ame_emp_hub_valid_pay_mode((string)$rule['payment_type']);
        if (in_array($mode, ['percentage', 'work', 'mixed'], true)) {
            $amount += (float)($row['commission_amount'] ?? 0);
            $value += (float)($row['service_amount'] ?? 0);
        }
    }
    $stmt->close();
    return [
        'ids' => $ids,
        'amount' => round($amount, 2),
        'service_value' => round($value, 2),
    ];
}

/**
 * সাবেক পাওনা = আজকের আগে পর্যন্ত কর্মীর কাছে দোকানের অগ্রিম পাওনা।
 * বর্তমান অগ্রিম ব্যালেন্স থেকে আজকের পোস্টেড লেনদেনের নিট (দেওয়া − ফেরত − বেতনে সমন্বয়) বাদ দিয়ে হিসাব —
 * ব্যালেন্স বা কোনো লেনদেন বদলায় না, শুধু দেখার হিসাব। ড্রাফট ধরা হয় না (ড্রাফটে ব্যালেন্স নড়ে না)।
 *
 * @return array{previous:float,today_given:float,today_settled:float,total:float}
 */
function ame_emp_hub_advance_split(mysqli $db, int $empId, string $date, float $balance): array
{
    $given = 0.0;
    $settled = 0.0;
    $draftSkip = isset(ame_emp_hub_cols($db, 'employee_advances')['status']) ? " AND COALESCE(status,'posted') <> 'draft'" : '';
    $st = @$db->prepare("SELECT kind, COALESCE(SUM(amount),0) t FROM employee_advances WHERE employee_id = ? AND advance_date = ?" . $draftSkip . ' GROUP BY kind');
    if ($st) {
        $st->bind_param('is', $empId, $date);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            if ((string)$r['kind'] === 'give') {
                $given += (float)$r['t'];
            } else {
                $settled += (float)$r['t'];
            }
        }
        $st->close();
    }
    $balance = round($balance, 2);
    return [
        'previous' => round(max(0.0, $balance - $given + $settled), 2),
        'today_given' => round($given, 2),
        'today_settled' => round($settled, 2),
        'total' => $balance,
    ];
}

/**
 * বেতন সাবমিটের আগে যে টাকা দেখাবে — process_salary যে অঙ্ক কাটবে সেটাই।
 * একই দিনে বেতন সারি থাকলেও pending কাজ/কমিশন লুকোয় না।
 *
 * @return array{pay_mode:string,pay_label:string,work_value:float,share:float,base:float,work_bonus:float,snack:float,payable:float,remaining:float,advance_due:float,present:bool,today_salary:float,note:string}
 */
function ame_emp_hub_pending_pay_preview(mysqli $db, int $empId, string $date): array
{
    $empty = [
        'pay_mode' => 'percentage',
        'pay_label' => '',
        'work_value' => 0.0,
        'share' => 0.0,
        'base' => 0.0,
        'work_bonus' => 0.0,
        'snack' => 0.0,
        'payable' => 0.0,
        'remaining' => 0.0,
        'advance_due' => 0.0,
        'advance_previous' => 0.0,
        'advance_today' => 0.0,
        'present' => true,
        'today_salary' => 0.0,
        'today_work_value' => 0.0,
        'today_share' => 0.0,
        'today_base' => 0.0,
        'today_bonus' => 0.0,
        'today_snack' => 0.0,
        'today_payable' => 0.0,
        'note' => '',
    ];
    $emp = ame_emp_hub_row($db, $empId, true);
    if (!$emp) {
        return $empty;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = ame_emp_hub_today();
    }
    $rule = ame_emp_hub_pay_rule_on($db, $emp, $date);
    $mode = ame_emp_hub_valid_pay_mode((string)$rule['payment_type']);
    $work = ame_emp_hub_pending_work_pay($db, $empId, $date, $mode === 'daily', false);
    $servicePack = in_array($mode, ['percentage', 'work', 'mixed'], true)
        ? ame_emp_hub_pending_service_pay($db, $emp, $empId, $date, false, false)
        : ['amount' => 0.0, 'service_value' => 0.0];
    $serviceAmount = (float)$servicePack['amount'];
    $serviceValue = (float)$servicePack['service_value'];
    $todayWorkPay = ame_emp_hub_pending_work_pay($db, $empId, $date, true, false);
    $todayService = in_array($mode, ['percentage', 'work', 'mixed'], true)
        ? ame_emp_hub_pending_service_pay($db, $emp, $empId, $date, true, false)
        : ['amount' => 0.0, 'service_value' => 0.0];
    $snackPay = ame_emp_hub_pending_snack_pay($db, $empId, $date, $mode === 'daily', false);
    $todaySnackPay = ame_emp_hub_pending_snack_pay($db, $empId, $date, true, false);
    $present = true;
    if ($mode === 'daily') {
        $att = function_exists('get_attendance_row') ? get_attendance_row($db, $empId, $date) : null;
        $present = $att && in_array((string)($att['status'] ?? ''), ['Present', 'Late'], true);
        if (!$present && $date === ame_emp_hub_today() && function_exists('ame_staff_is_duty_active')) {
            $present = ame_staff_is_duty_active($db, $empId);
        }
    }
    $base = 0.0;
    if ($mode === 'daily') {
        $base = $present ? round((float)($rule['daily_rate'] ?? 0), 2) : 0.0;
    } elseif (in_array($mode, ['monthly', 'mixed'], true)) {
        $base = round((float)($rule['monthly_salary'] ?? $emp['salary'] ?? 0), 2);
    }
    $share = in_array($mode, ['percentage', 'work', 'mixed'], true)
        ? round((float)$work['commission'] + $serviceAmount, 2)
        : 0.0;
    $workBonus = round((float)$work['bonus'], 2);
    $snackDue = round((float)$snackPay['amount'], 2);
    $todayShare = in_array($mode, ['percentage', 'work', 'mixed'], true)
        ? round((float)$todayWorkPay['commission'] + (float)$todayService['amount'], 2)
        : 0.0;
    $todayBase = ($mode === 'daily' && $present) ? round((float)($rule['daily_rate'] ?? 0), 2) : 0.0;
    $todayBonus = round((float)$todayWorkPay['bonus'], 2);
    $todayWork = ame_emp_hub_today_work($db, $empId, $date);
    $workSnack = round((float) ($todayWork['snack'] ?? 0), 2);
    $todaySnack = round(max((float) $todaySnackPay['amount'], $workSnack), 2);
    $snackDue = round(max($snackDue, $todaySnack), 2);
    $payable = round($base + $share + $workBonus + $snackDue, 2);
    $todayPayable = round($todayBase + $todayShare + $todayBonus + $todaySnack, 2);
    $todayWorkValue = round((float)$todayWorkPay['work_value'] + (float)$todayService['service_value'], 2);
    $todaySalary = ame_emp_hub_today_salary($db, $emp, $present, $todayWork, $date);
    $note = '';
    if ($mode === 'daily' && !$present) {
        $note = 'এই তারিখে হাজিরা চালু নেই। দৈনিক বেতন দিতে আগে হাজিরা চালু করুন।';
    } elseif ($todayPayable <= 0.009 && $payable > 0.009) {
        $note = 'আজকের অপরিশোধিত বেতন নেই। আগের অপরিশোধিত ভাগ আলাদা অপশনে আছে।';
    } elseif ($payable <= 0.009) {
        $note = 'এই তারিখ পর্যন্ত অপরিশোধিত বেতন বা কাজের ভাগ নেই।';
    }
    $advance = function_exists('ame_employee_advance_balance') ? round((float)ame_employee_advance_balance($db, $empId), 2) : 0.0;
    $advSplit = ame_emp_hub_advance_split($db, $empId, ame_emp_hub_today(), $advance);
    return [
        'pay_mode' => $mode,
        'pay_label' => ame_emp_hub_pay_label($mode),
        'work_value' => round((float)$work['work_value'] + $serviceValue, 2),
        'share' => $share,
        'base' => $base,
        'work_bonus' => $workBonus,
        'snack' => $snackDue,
        'payable' => $payable,
        'remaining' => round($share + $workBonus + $snackDue, 2),
        'advance_due' => $advance,
        'advance_previous' => $advSplit['previous'],
        'advance_today' => $advSplit['today_given'],
        'present' => $present,
        'today_salary' => $todaySalary,
        'today_work_value' => $todayWorkValue,
        'today_share' => $todayShare,
        'today_base' => $todayBase,
        'today_bonus' => $todayBonus,
        'today_snack' => $todaySnack,
        'today_payable' => $todayPayable,
        'note' => $note,
    ];
}

function ame_emp_hub_mark_snack_paid(mysqli $db, array $snackIds, int $salaryId): void
{
    $snackIds = array_values(array_unique(array_filter(array_map('intval', $snackIds), static fn($id) => $id > 0)));
    if ($snackIds === []) {
        return;
    }
    $cols = ame_emp_hub_cols($db, 'employee_snacks');
    if (!isset($cols['payout_status'])) {
        return;
    }
    $sql = "UPDATE employee_snacks SET payout_status = 'paid', salary_id = ?
            WHERE (payout_status IS NULL OR payout_status = '' OR payout_status = 'pending') AND id IN (" . implode(',', $snackIds) . ')';
    $stmt = @$db->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $salaryId);
    $stmt->execute();
    $stmt->close();
}

function ame_emp_hub_mark_work_paid(mysqli $db, array $workIds, int $salaryId): void
{
    $workIds = array_values(array_unique(array_filter(array_map('intval', $workIds), static fn($id) => $id > 0)));
    if ($workIds === []) {
        return;
    }
    $sql = "UPDATE employee_works SET payout_status = 'paid', salary_id = ?
            WHERE payout_status = 'pending' AND id IN (" . implode(',', $workIds) . ')';
    $stmt = @$db->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $salaryId);
    $stmt->execute();
    $stmt->close();
}

function ame_emp_hub_ledger(mysqli $db, int $empId, string $from, string $to): array
{
    $emp = ame_emp_hub_row($db, $empId, true);
    if (!$emp) {
        throw new Exception('কর্মী পাওয়া যায়নি।');
    }
    $lines = [];
    $push = static function (string $date, string $desc, float $debit, float $credit, string $bucket) use (&$lines) {
        $lines[] = [
            'date' => $date,
            'description' => $desc,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'bucket' => $bucket,
        ];
    };

    $sal = @$db->prepare("SELECT payment_date, month_year, amount, base_amount, commission_amount, bonus_amount, snacks_amount, advance_cut, deduction_amount, notes
                          FROM salaries WHERE employee_id = ? AND (status IS NULL OR status NOT IN ('reversed','cancelled'))
                          AND COALESCE(payment_date, CONCAT(month_year,'-01')) BETWEEN ? AND ? ORDER BY id ASC");
    if ($sal) {
        $sal->bind_param('iss', $empId, $from, $to);
        $sal->execute();
        $res = $sal->get_result();
        while ($r = $res->fetch_assoc()) {
            $d = (string)($r['payment_date'] ?? $r['month_year'] . '-01');
            $net = (float)($r['amount'] ?? 0);
            $base = (float)($r['base_amount'] ?? 0);
            $com = (float)($r['commission_amount'] ?? 0);
            $bon = (float)($r['bonus_amount'] ?? 0);
            if ($base > 0) {
                $push($d, 'বেতন ' . ($r['month_year'] ?? ''), 0, $base, 'credit');
            }
            if ($com > 0) {
                $push($d, 'কমিশন', 0, $com, 'credit');
            }
            if ($bon > 0) {
                $push($d, 'বোনাস', 0, $bon, 'credit');
            }
            if ($net > 0 && $base <= 0 && $com <= 0) {
                $push($d, 'বেতন/আয়', 0, $net, 'credit');
            }
            $sn = (float)($r['snacks_amount'] ?? 0);
            if ($sn > 0) {
                $push($d, 'নাস্তা কর্তন', $sn, 0, 'debit');
            }
            $cut = (float)($r['advance_cut'] ?? 0);
            if ($cut > 0) {
                $push($d, 'অগ্রিম সমন্বয়', $cut, 0, 'debit');
            }
        }
        $sal->close();
    }

    $commissionFps = ame_emp_hub_commission_fingerprints($db, $empId);
    $wk = @$db->prepare('SELECT work_date, customer_name, customer_phone, vehicle_no, service_type, invoice_ref, memo_no, imei_serial,
                                work_value, employee_amount, bonus_amount, source_type, source_id
                         FROM employee_works WHERE employee_id = ? AND work_date BETWEEN ? AND ? ORDER BY id ASC');
    if ($wk) {
        $wk->bind_param('iss', $empId, $from, $to);
        $wk->execute();
        $res = $wk->get_result();
        while ($r = $res->fetch_assoc()) {
            if (ame_emp_hub_manual_work_is_commission_duplicate($r, $commissionFps)) {
                continue;
            }
            $refs = implode(' · ', array_filter([
                (string)($r['invoice_ref'] ?? ''),
                (string)($r['memo_no'] ?? ''),
                (string)($r['imei_serial'] ?? ''),
            ]));
            $label = trim(($r['service_type'] ?? 'কাজ') . ' · ' . ($r['customer_name'] ?? '') . ' ' . ($r['customer_phone'] ?? '') . ' ' . ($r['vehicle_no'] ?? '') . ($refs !== '' ? (' · ' . $refs) : ''));
            $push((string)$r['work_date'], $label, 0, (float)$r['employee_amount'], 'credit');
            if ((float)($r['bonus_amount'] ?? 0) > 0) {
                $push((string)$r['work_date'], 'কাজের বোনাস', 0, (float)$r['bonus_amount'], 'credit');
            }
        }
        $wk->close();
    }

    $adv = @$db->prepare("SELECT advance_date, kind, amount, note, status FROM employee_advances WHERE employee_id = ? AND advance_date BETWEEN ? AND ? ORDER BY id ASC");
    if (!$adv) {
        $adv = @$db->prepare("SELECT advance_date, kind, amount, note FROM employee_advances WHERE employee_id = ? AND advance_date BETWEEN ? AND ? ORDER BY id ASC");
    }
    if ($adv) {
        $adv->bind_param('iss', $empId, $from, $to);
        $adv->execute();
        $res = $adv->get_result();
        while ($r = $res->fetch_assoc()) {
            if ((string)($r['status'] ?? 'posted') === 'draft') {
                continue;
            }
            $kind = (string)$r['kind'];
            $amt = (float)$r['amount'];
            if ($kind === 'give') {
                $label = ((string)($r['status'] ?? '') === 'settled' ? 'সকালের অগ্রিম · বেতনে সমন্বয়' : 'অগ্রিম') . ($r['note'] ? ' · ' . $r['note'] : '');
                $push((string)$r['advance_date'], $label, $amt, 0, 'debit');
            } else {
                $push((string)$r['advance_date'], $kind === 'salary_cut' ? 'অগ্রিম সমন্বয়' : 'অগ্রিম ফেরত', 0, $amt, 'debit');
            }
        }
        $adv->close();
    }

    $sn = @$db->prepare('SELECT snack_date, kind, amount, note FROM employee_snacks WHERE employee_id = ? AND snack_date BETWEEN ? AND ? ORDER BY id ASC');
    if ($sn) {
        $sn->bind_param('iss', $empId, $from, $to);
        $sn->execute();
        $res = $sn->get_result();
        while ($r = $res->fetch_assoc()) {
            $push((string)$r['snack_date'], 'নাস্তা (' . ame_emp_hub_snack_kind_label((string)$r['kind']) . ')' . ($r['note'] ? ' · ' . $r['note'] : ''), (float)$r['amount'], 0, 'debit');
        }
        $sn->close();
    }

    $dep = @$db->prepare('SELECT deposit_date, kind, amount, note FROM employee_shop_deposits WHERE employee_id = ? AND deposit_date BETWEEN ? AND ? ORDER BY id ASC');
    if ($dep) {
        $dep->bind_param('iss', $empId, $from, $to);
        $dep->execute();
        $res = $dep->get_result();
        while ($r = $res->fetch_assoc()) {
            $amt = (float)$r['amount'];
            if (($r['kind'] ?? '') === 'withdraw') {
                $push((string)$r['deposit_date'], 'জমা উত্তোলন', $amt, 0, 'deposit');
            } else {
                $push((string)$r['deposit_date'], 'কর্মী জমা', 0, $amt, 'deposit');
            }
        }
        $dep->close();
    }

    usort($lines, static function ($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
    $bal = 0.0;
    foreach ($lines as &$ln) {
        $bal += $ln['credit'] - $ln['debit'];
        $ln['balance'] = round($bal, 2);
    }
    unset($ln);
    return [
        'employee' => ['id' => $empId, 'name' => (string)$emp['name'], 'code' => (string)($emp['employee_code'] ?? '')],
        'from' => $from,
        'to' => $to,
        'lines' => $lines,
        'closing' => round($bal, 2),
    ];
}

function ame_emp_hub_report(
    mysqli $db,
    int $empId,
    string $ym,
    string $range = 'month',
    string $customFrom = '',
    string $customTo = ''
): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        $ym = substr(ame_emp_hub_today(), 0, 7);
    }
    if ($range === 'month') {
        $from = $ym . '-01';
        $to = date('Y-m-t', strtotime($from));
    } else {
        [$from, $to] = ame_emp_hub_date_range($range, $customFrom, $customTo);
    }
    if (strtotime($to) < strtotime($from)) {
        [$from, $to] = [$to, $from];
    }
    if ((strtotime($to) - strtotime($from)) > 366 * 86400) {
        $to = date('Y-m-d', strtotime($from . ' +366 days'));
    }
    $emp = ame_emp_hub_row($db, $empId, true);
    if (!$emp) {
        throw new Exception('কর্মী পাওয়া যায়নি।');
    }
    $present = 0;
    $absent = 0;
    $days = [];
    $att = @$db->prepare('SELECT att_date, status, check_in, check_out, activated_at, changed_by_name, changed_at
                          FROM attendance WHERE employee_id = ? AND att_date BETWEEN ? AND ? ORDER BY att_date ASC');
    if ($att) {
        $att->bind_param('iss', $empId, $from, $to);
        $att->execute();
        $res = $att->get_result();
        $map = [];
        while ($r = $res->fetch_assoc()) {
            $map[$r['att_date']] = $r;
        }
        $att->close();
    } else {
        $map = [];
    }
    $works = ame_emp_hub_works($db, $empId, $from, $to);
    $workByDay = [];
    foreach ($works as &$work) {
        $day = (string)($work['work_date'] ?? '');
        if ($day === '') {
            continue;
        }
        $workRule = ame_emp_hub_pay_rule_on($db, $emp, $day);
        $workMode = ame_emp_hub_valid_pay_mode((string)$workRule['payment_type']);
        $work['pay_mode'] = $workMode;
        $work['pay_label'] = ame_emp_hub_pay_label($workMode);
        $work['statement_percent'] = in_array($workMode, ['percentage', 'work', 'mixed'], true)
            ? round((float)($workRule['percent_rate'] ?? $work['employee_percent'] ?? 0), 2)
            : 0.0;
        $work['statement_employee_amount'] = in_array($workMode, ['percentage', 'work', 'mixed'], true)
            ? round((float)($work['employee_amount'] ?? 0), 2)
            : 0.0;
        if (!isset($workByDay[$day])) {
            $workByDay[$day] = ['w' => 0.0, 'p' => 0.0, 'b' => 0.0];
        }
        $workByDay[$day]['w'] += (float)($work['work_value'] ?? 0);
        $workByDay[$day]['p'] += (float)$work['statement_employee_amount'];
        $workByDay[$day]['b'] += (float)($work['bonus_amount'] ?? 0);
    }
    unset($work);

    $cursor = new DateTime($from);
    $end = new DateTime($to);
    $workValue = 0.0;
    $workPay = 0.0;
    $salary = 0.0;
    $bonusTot = 0.0;
    $snackTot = 0.0;
    $today = ame_emp_hub_today();
    while ($cursor <= $end) {
        $d = $cursor->format('Y-m-d');
        if ($d > $today) {
            break;
        }
        $a = $map[$d] ?? null;
        $on = $a && in_array((string)$a['status'], ['Present', 'Late'], true);
        $w = (float)(($workByDay[$d]['w'] ?? 0));
        $dayRule = ame_emp_hub_pay_rule_on($db, $emp, $d);
        $dayMode = ame_emp_hub_valid_pay_mode((string)$dayRule['payment_type']);
        $dayPayLabel = ame_emp_hub_pay_label($dayMode);
        $note = $on
            ? ($w > 0 ? ('উপস্থিত — ' . $dayPayLabel) : ('উপস্থিত — কাজ নেই · ' . $dayPayLabel))
            : ('অনুপস্থিত — ' . ($dayMode === 'daily' ? 'দৈনিক বেতন শূন্য' : $dayPayLabel));
        if ($on) {
            $present++;
        } else {
            $absent++;
        }
        $workValue += $w;
        $dayCommission = in_array($dayMode, ['percentage', 'work', 'mixed'], true)
            ? (float)(($workByDay[$d]['p'] ?? 0))
            : 0.0;
        $workPay += $dayCommission;
        $dayBonus = (float)(($workByDay[$d]['b'] ?? 0));
        $bonusTot += $dayBonus;
        $dailyPay = 0.0;
        if ($on && $dayMode === 'daily') {
            $dailyPay = round((float)($dayRule['daily_rate'] ?? 0), 2);
        } elseif ($on && in_array($dayMode, ['monthly', 'mixed'], true)) {
            $monthRate = (float)($dayRule['monthly_salary'] ?? 0);
            $dailyPay = round($monthRate / max(1, (int)date('t', strtotime($d))), 2);
        }
        $salary += $dailyPay;
        $days[] = [
            'date' => $d,
            'status' => $on ? 'Present' : 'Absent',
            'note' => $note,
            'check_in' => (string)($a['check_in'] ?? $a['activated_at'] ?? ''),
            'check_out' => (string)($a['check_out'] ?? ''),
            'changed_by' => (string)($a['changed_by_name'] ?? ''),
            'changed_at' => (string)($a['changed_at'] ?? ''),
            'pay_mode' => $dayMode,
            'pay_label' => $dayPayLabel,
            'work_value' => round($w, 2),
            'salary' => $dailyPay,
            'commission' => round($dayCommission, 2),
            'bonus' => round($dayBonus, 2),
        ];
        $cursor->modify('+1 day');
    }

    ame_emp_hub_sync_work_snacks($db, $empId);
    $sn = @$db->prepare('SELECT COALESCE(SUM(amount),0) s FROM employee_snacks WHERE employee_id = ? AND snack_date BETWEEN ? AND ?');
    if ($sn) {
        $sn->bind_param('iss', $empId, $from, $to);
        $sn->execute();
        $snackTot += (float)($sn->get_result()->fetch_assoc()['s'] ?? 0);
        $sn->close();
    }
    $advGive = 0.0;
    $advCut = 0.0;
    $draftSkip = isset(ame_emp_hub_cols($db, 'employee_advances')['status']) ? " AND COALESCE(status,'posted') <> 'draft'" : '';
    $ag = @$db->prepare("SELECT kind, COALESCE(SUM(amount),0) t FROM employee_advances WHERE employee_id = ? AND advance_date BETWEEN ? AND ?" . $draftSkip . " GROUP BY kind");
    if ($ag) {
        $ag->bind_param('iss', $empId, $from, $to);
        $ag->execute();
        $res = $ag->get_result();
        while ($r = $res->fetch_assoc()) {
            if (($r['kind'] ?? '') === 'give') {
                $advGive = (float)$r['t'];
            } else {
                $advCut += (float)$r['t'];
            }
        }
        $ag->close();
    }
    $depIn = 0.0;
    $depOut = 0.0;
    $dg = @$db->prepare("SELECT kind, COALESCE(SUM(amount),0) t FROM employee_shop_deposits WHERE employee_id = ? AND deposit_date BETWEEN ? AND ? GROUP BY kind");
    if ($dg) {
        $dg->bind_param('iss', $empId, $from, $to);
        $dg->execute();
        $res = $dg->get_result();
        while ($r = $res->fetch_assoc()) {
            if (($r['kind'] ?? '') === 'withdraw') {
                $depOut = (float)$r['t'];
            } else {
                $depIn += (float)$r['t'];
            }
        }
        $dg->close();
    }

    $todayRule = ame_emp_hub_pay_rule_on($db, $emp, ame_emp_hub_today());
    $mode = ame_emp_hub_valid_pay_mode((string)$todayRule['payment_type']);
    $salary = round($salary, 2);
    $commission = $workPay;
    $paidAmount = 0.0;
    $paid = @$db->prepare("SELECT COALESCE(SUM(amount),0) paid FROM salaries
                           WHERE employee_id = ? AND (status IS NULL OR status NOT IN ('reversed','cancelled'))
                           AND COALESCE(payment_date, CONCAT(month_year,'-01')) BETWEEN ? AND ?");
    if ($paid) {
        $paid->bind_param('iss', $empId, $from, $to);
        $paid->execute();
        $paidAmount = (float)($paid->get_result()->fetch_assoc()['paid'] ?? 0);
        $paid->close();
    }
    $labour = round($salary + $commission + $bonusTot + $snackTot, 2);
    $shop = round($workValue - $labour, 2);
    $prod = $labour > 0 ? round($workValue / $labour, 2) : ($workValue > 0 ? 99 : 0);
    $payable = round($salary + $commission + $bonusTot + $snackTot - $advCut, 2);

    return [
        'employee' => [
            'id' => $empId,
            'name' => (string)$emp['name'],
            'code' => (string)($emp['employee_code'] ?? ''),
            'pay_mode' => $mode,
            'pay_label' => ame_emp_hub_pay_label($mode),
        ],
        'month' => $ym,
        'range' => $range,
        'from' => $from,
        'to' => $to,
        'present_days' => $present,
        'absent_days' => $absent,
        'work_value' => round($workValue, 2),
        'salary' => $salary,
        'commission' => round($commission, 2),
        'bonus' => round($bonusTot, 2),
        'snack' => round($snackTot, 2),
        'advance' => round($advGive, 2),
        'advance_adjustment' => round($advCut, 2),
        'deposit' => round($depIn, 2),
        'withdrawal' => round($depOut, 2),
        'labour_cost' => $labour,
        'shop_contribution' => $shop,
        'productivity' => $prod,
        'final_payable' => $payable,
        'paid_amount' => round($paidAmount, 2),
        'outstanding' => round($payable - $paidAmount, 2),
        'employee_balance' => round((float)($emp['shop_deposit_balance'] ?? 0) - (float)($emp['advance_balance'] ?? 0), 2),
        'pay_history' => ame_emp_hub_pay_history($db, $emp),
        'days' => $days,
        'works' => $works,
        'audit' => ame_emp_hub_audit_rows($db, $empId, 40),
    ];
}

/**
 * ইনভয়েস/মেমো থেকে একই বিক্রয় শনাক্ত (যেমন 062240 ও INV-20261114-062240)।
 */
function ame_emp_hub_work_invoice_key(string $invoiceRef, string $memoNo = ''): string
{
    $raw = strtoupper(trim($invoiceRef !== '' ? $invoiceRef : $memoNo));
    if ($raw === '') {
        return '';
    }
    if (preg_match('/(\d{6})$/', $raw, $m)) {
        return $m[1];
    }
    return $raw;
}

function ame_emp_hub_work_fingerprint(array $row): string
{
    $invKey = ame_emp_hub_work_invoice_key(
        (string)($row['invoice_ref'] ?? ''),
        (string)($row['memo_no'] ?? '')
    );
    $job = trim((string)($row['service_type'] ?? ''));
    $bill = number_format(round((float)($row['work_value'] ?? 0), 2), 2, '.', '');
    $srcType = strtolower(trim((string)($row['source_type'] ?? '')));
    $srcId = (int)($row['source_id'] ?? 0);
    if ($srcType === 'sale_service' && $srcId > 0) {
        return 'ss:' . $srcId;
    }
    if ((int)($row['editable'] ?? 1) === 0 && !empty($row['id'])) {
        return 'sc:' . (int)$row['id'];
    }
    return $invKey . '|' . $job . '|' . $bill;
}

/**
 * বিক্রয়/মেরামত থেকে যে কমিশন লাইন স্বয়ংক্রিয় আছে — ম্যানুয়াল employee_works ডুপ চেকের জন্য।
 *
 * @return array<string, true>
 */
function ame_emp_hub_commission_fingerprints(mysqli $db, int $empId): array
{
    $fps = [];
    if ($empId <= 0) {
        return $fps;
    }
    $stmt = @$db->prepare(
        "SELECT sc.id, sc.service_type, sc.service_amount, sc.source_type, sc.source_id,
                s.invoice_number, w.job_no
         FROM service_commissions sc
         LEFT JOIN sales s ON s.id = sc.sale_id
         LEFT JOIN workshop_jobs w ON sc.source_type = 'workshop_job' AND w.id = sc.source_id
         WHERE sc.employee_id = ?
         ORDER BY sc.id ASC"
    );
    if (!$stmt) {
        return $fps;
    }
    $stmt->bind_param('i', $empId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $isWorkshop = (string)($r['source_type'] ?? '') === 'workshop_job';
            $row = [
                'id' => (int)$r['id'],
                'editable' => 0,
                'invoice_ref' => $isWorkshop ? (string)($r['job_no'] ?? '') : (string)($r['invoice_number'] ?? ''),
                'memo_no' => '',
                'service_type' => (string)($r['service_type'] ?? ''),
                'work_value' => (float)($r['service_amount'] ?? 0),
                'source_type' => (string)($r['source_type'] ?? ''),
                'source_id' => (int)($r['source_id'] ?? 0),
            ];
            $fps[ame_emp_hub_work_fingerprint($row)] = true;
        }
    }
    $stmt->close();

    return $fps;
}

function ame_emp_hub_manual_work_is_commission_duplicate(array $workRow, array $commissionFps): bool
{
    if ($commissionFps === []) {
        return false;
    }

    return isset($commissionFps[ame_emp_hub_work_fingerprint($workRow)]);
}

/**
 * একই কাজ employee_works + service_commissions উভয় উৎসে থাকলে ডাবল লাইন দেখাবে না।
 */
function ame_emp_hub_works_dedupe(array $rows): array
{
    $autoKeys = [];
    foreach ($rows as $row) {
        if ((int)($row['editable'] ?? 1) === 0) {
            $autoKeys[ame_emp_hub_work_fingerprint($row)] = true;
        }
    }
    $seen = [];
    $out = [];
    foreach ($rows as $row) {
        $fp = ame_emp_hub_work_fingerprint($row);
        $isManual = (int)($row['editable'] ?? 1) === 1;
        if ($isManual && isset($autoKeys[$fp])) {
            continue;
        }
        if (isset($seen[$fp])) {
            continue;
        }
        $seen[$fp] = true;
        $out[] = $row;
    }
    return $out;
}

function ame_emp_hub_works(mysqli $db, int $empId, string $from, string $to): array
{
    $rows = [];
    $stmt = @$db->prepare(
        'SELECT * FROM employee_works WHERE employee_id = ? AND work_date BETWEEN ? AND ? ORDER BY work_date DESC, COALESCE(work_time, \'00:00:00\') DESC, id DESC'
    );
    if ($stmt) {
        $stmt->bind_param('iss', $empId, $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $r['editable'] = 1;
            $r['source_label'] = 'কর্মী পেজ';
            $rows[] = $r;
        }
        $stmt->close();
    }

    $commissions = @$db->prepare(
        "SELECT sc.id, sc.sale_id, sc.service_amount, sc.commission_rate, sc.commission_amount,
                sc.shop_amount, sc.service_type, sc.status AS payout_status, sc.created_at,
                COALESCE(NULLIF(sc.source_type, ''), 'sale_service') source_type, sc.source_id,
                s.invoice_number, s.vehicle_no AS sale_vehicle_no, s.guest_customer_name, s.guest_customer_phone,
                c.name AS customer_name, c.phone AS customer_phone,
                w.job_no, w.item_name, w.serial_no, w.phone AS workshop_phone, w.status AS workshop_status
         FROM service_commissions sc
         LEFT JOIN sales s ON s.id = sc.sale_id
         LEFT JOIN customers c ON c.id = s.customer_id
         LEFT JOIN workshop_jobs w ON sc.source_type = 'workshop_job' AND w.id = sc.source_id
         WHERE sc.employee_id = ? AND DATE(sc.created_at) BETWEEN ? AND ?
         ORDER BY sc.created_at DESC, sc.id DESC"
    );
    if ($commissions) {
        $commissions->bind_param('iss', $empId, $from, $to);
        $commissions->execute();
        $res = $commissions->get_result();
        while ($r = $res->fetch_assoc()) {
            $isWorkshop = (string)($r['source_type'] ?? '') === 'workshop_job';
            $customer = trim((string)($r['customer_name'] ?? ''));
            if ($customer === '') {
                $customer = trim((string)($r['guest_customer_name'] ?? ''));
            }
            $rows[] = [
                'id' => (int)$r['id'],
                'employee_id' => $empId,
                'work_date' => substr((string)$r['created_at'], 0, 10),
                'work_time' => substr((string)$r['created_at'], 11, 8),
                'customer_name' => $customer,
                'customer_phone' => (string)($r['customer_phone'] ?: ($r['guest_customer_phone'] ?: $r['workshop_phone'])),
                'vehicle' => $isWorkshop ? (string)($r['item_name'] ?? '') : '',
                'vehicle_no' => (string)($r['sale_vehicle_no'] ?? ''),
                'service_type' => (string)($r['service_type'] ?? ''),
                'description' => $isWorkshop ? ('জব অবস্থা: ' . (string)($r['workshop_status'] ?? '')) : '',
                'invoice_ref' => $isWorkshop ? (string)($r['job_no'] ?? '') : (string)($r['invoice_number'] ?? ''),
                'memo_no' => '',
                'imei_serial' => $isWorkshop ? (string)($r['serial_no'] ?? '') : '',
                'work_value' => round((float)($r['service_amount'] ?? 0), 2),
                'employee_percent' => round((float)($r['commission_rate'] ?? 0), 2),
                'employee_amount' => round((float)($r['commission_amount'] ?? 0), 2),
                'bonus_amount' => 0.0,
                'snack' => 0.0,
                'other_cost' => 0.0,
                'shop_contribution' => round((float)($r['shop_amount'] ?? 0), 2),
                'payout_status' => (string)($r['payout_status'] ?? 'pending'),
                'editable' => 0,
                'source_label' => $isWorkshop ? 'মেরামত জব' : 'বিক্রয় ইনভয়েস',
            ];
        }
        $commissions->close();
    }

    usort($rows, static function (array $a, array $b): int {
        $ak = (string)($a['work_date'] ?? '') . ' ' . (string)($a['work_time'] ?? '');
        $bk = (string)($b['work_date'] ?? '') . ' ' . (string)($b['work_time'] ?? '');
        return strcmp($bk, $ak);
    });
    return ame_emp_hub_works_dedupe($rows);
}

function ame_emp_hub_audit_rows(mysqli $db, int $empId, int $limit = 40): array
{
    $rows = [];
    $lim = max(1, min(200, $limit));
    $stmt = @$db->prepare("SELECT action, field_name, old_value, new_value, actor_name, created_at
                           FROM employee_hub_audit WHERE employee_id = ? ORDER BY id DESC LIMIT {$lim}");
    if ($stmt) {
        $stmt->bind_param('i', $empId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
    }
    return $rows;
}

function ame_emp_hub_search_party(mysqli $db, string $q): array
{
    $q = trim($q);
    if ($q === '') {
        return ['customers' => []];
    }
    $like = '%' . $q . '%';
    $out = [];
    $pick = function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db) : '1=1';
    $stmt = @$db->prepare('SELECT id, name, phone FROM customers WHERE ' . $pick . ' AND (name LIKE ? OR phone LIKE ?) ORDER BY id DESC LIMIT 12');
    if ($stmt) {
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $out[] = $r;
        }
        $stmt->close();
    }
    return ['customers' => $out];
}

function ame_emp_hub_accounts(mysqli $db): array
{
    if (!function_exists('ame_active_accounts')) {
        require_once __DIR__ . '/account_functions.php';
    }
    if (function_exists('ame_active_accounts')) {
        return ame_active_accounts($db);
    }
    return [];
}

function ame_emp_hub_money_kind_label(string $kind): string
{
    return match ($kind) {
        'salary' => 'বেতন',
        'snack' => 'নাস্তা',
        'advance' => 'অগ্রিম',
        'deposit' => 'জমা',
        'work' => 'কাজ',
        default => 'টাকা',
    };
}

function ame_emp_hub_money_entries(mysqli $db, int $empId, string $filter = '', int $limit = 40): array
{
    $filter = strtolower(trim($filter));
    $want = static function (string $kind) use ($filter): bool {
        return $filter === '' || $filter === 'all' || $filter === $kind;
    };
    $out = [];
    if ($want('salary')) {
        $sql = "SELECT id, COALESCE(payment_date, CONCAT(month_year,'-01')) AS entry_date, month_year,
                       COALESCE(amount, 0) AS amount, COALESCE(notes, '') AS notes, COALESCE(bonus_amount, 0) AS bonus,
                       COALESCE(snacks_amount, 0) AS snacks
                FROM salaries
                WHERE employee_id = ? AND (status IS NULL OR status NOT IN ('reversed','cancelled'))
                ORDER BY id DESC LIMIT 40";
        $st = @$db->prepare($sql);
        if ($st) {
            $st->bind_param('i', $empId);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $note = trim((string)$r['notes']);
                $label = $note !== '' ? $note : ('বেতন ' . (string)$r['month_year']);
                $out[] = [
                    'kind' => 'salary',
                    'id' => (int)$r['id'],
                    'date' => (string)$r['entry_date'],
                    'label' => $label,
                    'amount' => round((float)$r['amount'], 2),
                    'can_delete' => true,
                ];
            }
            $st->close();
        }
        /* 10.98.694 */
        $rv = @$db->prepare("SELECT id, COALESCE(payment_date, CONCAT(month_year,'-01')) AS entry_date, month_year,
                       COALESCE(amount, 0) AS amount, COALESCE(notes, '') AS notes, reversed_at
                FROM salaries
                WHERE employee_id = ? AND status = 'reversed'
                ORDER BY id DESC LIMIT 10");
        if ($rv) {
            $rv->bind_param('i', $empId);
            $rv->execute();
            $rres = $rv->get_result();
            while ($r = $rres->fetch_assoc()) {
                $out[] = [
                    'kind' => 'salary_reversed',
                    'id' => (int)$r['id'],
                    'date' => (string)$r['entry_date'],
                    'label' => 'বেতন ' . (string)$r['month_year'],
                    'amount' => round((float)$r['amount'], 2),
                    'can_delete' => false,
                    'reversed' => true,
                    'reversed_at' => (string)($r['reversed_at'] ?? ''),
                    'note' => trim((string)$r['notes']),
                ];
            }
            $rv->close();
        }
    }
    if ($want('snack')) {
        $st = @$db->prepare('SELECT id, snack_date, kind, amount, note FROM employee_snacks WHERE employee_id = ? ORDER BY id DESC LIMIT 40');
        if ($st) {
            $st->bind_param('i', $empId);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $out[] = [
                    'kind' => 'snack',
                    'id' => (int)$r['id'],
                    'date' => (string)$r['snack_date'],
                    'label' => 'নাস্তা (' . ame_emp_hub_snack_kind_label((string)$r['kind']) . ')' . (!empty($r['note']) ? ' · ' . $r['note'] : ''),
                    'amount' => round((float)$r['amount'], 2),
                    'can_delete' => true,
                ];
            }
            $st->close();
        }
    }
    if ($want('advance')) {
        $st = @$db->prepare('SELECT id, advance_date, kind, amount, note, status FROM employee_advances WHERE employee_id = ? ORDER BY id DESC LIMIT 40');
        if (!$st) {
            $st = @$db->prepare('SELECT id, advance_date, kind, amount, note FROM employee_advances WHERE employee_id = ? ORDER BY id DESC LIMIT 40');
        }
        if ($st) {
            $st->bind_param('i', $empId);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $k = (string)$r['kind'];
                $cut = $k === 'salary_cut';
                $draft = (string)($r['status'] ?? '') === 'draft';
                $out[] = [
                    'kind' => 'advance',
                    'id' => (int)$r['id'],
                    'date' => (string)$r['advance_date'],
                    'label' => ($draft ? 'সকালের ড্রাফট' : ame_emp_hub_snack_kind_label($k)) . (!empty($r['note']) ? ' · ' . $r['note'] : '') . ($cut ? ' — বেতন মুছলে যাবে' : ''),
                    'amount' => round((float)$r['amount'], 2),
                    'can_delete' => !$cut,
                ];
            }
            $st->close();
        }
    }
    if ($want('deposit')) {
        $st = @$db->prepare('SELECT id, deposit_date, kind, amount, note FROM employee_shop_deposits WHERE employee_id = ? ORDER BY id DESC LIMIT 40');
        if ($st) {
            $st->bind_param('i', $empId);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $k = (string)$r['kind'] === 'withdraw' ? 'জমা উত্তোলন' : 'কর্মী জমা';
                $out[] = [
                    'kind' => 'deposit',
                    'id' => (int)$r['id'],
                    'date' => (string)$r['deposit_date'],
                    'label' => $k . (!empty($r['note']) ? ' · ' . $r['note'] : ''),
                    'amount' => round((float)$r['amount'], 2),
                    'can_delete' => true,
                ];
            }
            $st->close();
        }
    }
    if ($want('work')) {
        $st = @$db->prepare('SELECT id, work_date, customer_name, service_type, employee_amount, work_value FROM employee_works WHERE employee_id = ? ORDER BY id DESC LIMIT 40');
        if ($st) {
            $st->bind_param('i', $empId);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $who = trim((string)($r['service_type'] ?? 'কাজ') . ' · ' . (string)($r['customer_name'] ?? ''));
                $out[] = [
                    'kind' => 'work',
                    'id' => (int)$r['id'],
                    'date' => (string)$r['work_date'],
                    'label' => trim($who, ' ·'),
                    'amount' => round((float)($r['employee_amount'] ?? $r['work_value'] ?? 0), 2),
                    'can_delete' => true,
                ];
            }
            $st->close();
        }
    }
    usort($out, static function ($a, $b) {
        $d = strcmp((string)$b['date'], (string)$a['date']);
        return $d !== 0 ? $d : ((int)$b['id'] <=> (int)$a['id']);
    });
    return array_slice($out, 0, max(1, $limit));
}

function ame_emp_hub_delete_money(mysqli $db, string $kind, int $id, int $expectEmp = 0, string $reason = ''): array
{
    $kind = strtolower(trim($kind));
    if ($id <= 0) {
        throw new Exception('সঠিক এন্ট্রি বাছুন।');
    }
    if (!function_exists('account_credit')) {
        require_once __DIR__ . '/account_functions.php';
    }
    $db->begin_transaction();
    try {
        if ($kind === 'salary') {
            ame_emp_hub_delete_salary_row($db, $id, $expectEmp, $reason);
        } elseif ($kind === 'snack') {
            ame_emp_hub_delete_snack_row($db, $id, $expectEmp);
        } elseif ($kind === 'advance') {
            ame_emp_hub_delete_advance_row($db, $id, $expectEmp);
        } elseif ($kind === 'deposit') {
            ame_emp_hub_delete_deposit_row($db, $id, $expectEmp);
        } elseif ($kind === 'work') {
            $work = ame_emp_hub_work_row($db, $id);
            if (!$work) {
                throw new Exception('কাজ পাওয়া যায়নি।');
            }
            if ($expectEmp > 0 && (int)$work['employee_id'] !== $expectEmp) {
                throw new Exception('এই কাজ এই কর্মীর নয়।');
            }
            ame_emp_hub_delete_work($db, $id);
        } else {
            throw new Exception('এই ধরনের এন্ট্রি মুছা যায় না।');
        }
        $db->commit();
        return ['ok' => true, 'kind' => $kind, 'id' => $id];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_emp_hub_delete_salary_row(mysqli $db, int $salaryId, int $expectEmp = 0, string $reason = ''): void
{
    $stmt = $db->prepare('SELECT * FROM salaries WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $salaryId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        throw new Exception('বেতন এন্ট্রি পাওয়া যায়নি।');
    }
    $empId = (int)$row['employee_id'];
    if ($expectEmp > 0 && $empId !== $expectEmp) {
        throw new Exception('এই বেতন এই কর্মীর নয়।');
    }
    if (($row['status'] ?? '') === 'reversed' || ($row['status'] ?? '') === 'cancelled') {
        throw new Exception('এই বেতন আগেই মুছে/রিভার্স করা আছে।');
    }
    $cash = round((float)$row['amount'], 2);
    $acc = (int)($row['account_id'] ?? 0);
    $empId = (int)$row['employee_id'];
    if ($cash > 0.009 && $acc > 0) {
        account_credit($acc, $cash, 'salary_reversal', $salaryId, 'বেতন মুছা #' . $salaryId);
    }
    $adv = round((float)($row['advance_cut'] ?? 0), 2);
    $payDate = (string)($row['payment_date'] ?? '');
    /* 10.98.712 */
    $oldCutNote = 'বেতনে সমন্বয় (সাবেক অগ্রিম) #' . $salaryId;
    $oldCutFind = @$db->prepare("SELECT id, amount FROM employee_advances WHERE employee_id = ? AND kind = 'salary_cut' AND note = ? ORDER BY id DESC LIMIT 1");
    if ($oldCutFind) {
        $oldCutFind->bind_param('is', $empId, $oldCutNote);
        $oldCutFind->execute();
        $oldCutHit = $oldCutFind->get_result()->fetch_assoc();
        $oldCutFind->close();
        if ($oldCutHit) {
            $oldCutAmt = round((float)$oldCutHit['amount'], 2);
            $oldCutId = (int)$oldCutHit['id'];
            $oldCutBack = $db->prepare('UPDATE employees SET advance_balance = COALESCE(advance_balance,0) + ? WHERE id = ?');
            $oldCutBack->bind_param('di', $oldCutAmt, $empId);
            $oldCutBack->execute();
            $oldCutBack->close();
            $oldCutDel = $db->prepare('DELETE FROM employee_advances WHERE id = ? LIMIT 1');
            $oldCutDel->bind_param('i', $oldCutId);
            $oldCutDel->execute();
            $oldCutDel->close();
            $adv = round(max(0, $adv - $oldCutAmt), 2);
        }
    }
    if ($adv > 0.009) {
        $cutId = 0;
        $find = @$db->prepare("SELECT id FROM employee_advances WHERE employee_id = ? AND kind = 'salary_cut' AND ABS(amount - ?) < 0.009 AND (? = '' OR advance_date = ?) ORDER BY id DESC LIMIT 1");
        if ($find) {
            $find->bind_param('idss', $empId, $adv, $payDate, $payDate);
            $find->execute();
            $hit = $find->get_result()->fetch_assoc();
            $find->close();
            $cutId = (int)($hit['id'] ?? 0);
        }
        $upd = $db->prepare('UPDATE employees SET advance_balance = COALESCE(advance_balance,0) + ? WHERE id = ?');
        $upd->bind_param('di', $adv, $empId);
        $upd->execute();
        $upd->close();
        if ($cutId > 0) {
            $delCut = $db->prepare('DELETE FROM employee_advances WHERE id = ? LIMIT 1');
            $delCut->bind_param('i', $cutId);
            $delCut->execute();
            $delCut->close();
        }
    }
    $restoreSnack = @$db->prepare("UPDATE employee_snacks SET payout_status = 'pending', salary_id = NULL WHERE salary_id = ?");
    if ($restoreSnack) {
        $restoreSnack->bind_param('i', $salaryId);
        $restoreSnack->execute();
        $restoreSnack->close();
    }
    $restoreWork = @$db->prepare("UPDATE employee_works SET payout_status = 'pending', salary_id = NULL WHERE salary_id = ?");
    if ($restoreWork) {
        $restoreWork->bind_param('i', $salaryId);
        $restoreWork->execute();
        $restoreWork->close();
    }
    $restoreEarnings = @$db->prepare(
        "UPDATE worker_earnings we
         INNER JOIN service_commissions sc
            ON we.source_type = 'sale_service' AND we.source_id = sc.id
         SET we.paid_amount = 0, we.pay_status = 'unpaid'
         WHERE sc.salary_id = ?"
    );
    if ($restoreEarnings) {
        $restoreEarnings->bind_param('i', $salaryId);
        $restoreEarnings->execute();
        $restoreEarnings->close();
    }
    $restoreCommission = @$db->prepare("UPDATE service_commissions SET status = 'pending', salary_id = NULL WHERE salary_id = ?");
    if ($restoreCommission) {
        $restoreCommission->bind_param('i', $salaryId);
        $restoreCommission->execute();
        $restoreCommission->close();
    }
    if (function_exists('ame_earnings_sync_legacy_paid')) {
        ame_earnings_sync_legacy_paid($db, $empId);
    }
    $reason = trim(function_exists('mb_substr') ? mb_substr($reason, 0, 200) : substr($reason, 0, 200));
    $reasonNote = $reason !== '' ? (' | ফেরত: ' . $reason) : ' | ফেরত আনা হয়েছে';
    $mark = $db->prepare("UPDATE salaries SET status = 'reversed', reversed_at = NOW(), notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ?");
    if ($mark) {
        $mark->bind_param('si', $reasonNote, $salaryId);
        $mark->execute();
        $mark->close();
    } else {
        $del = $db->prepare('DELETE FROM salaries WHERE id = ? LIMIT 1');
        $del->bind_param('i', $salaryId);
        $del->execute();
        $del->close();
    }
    ame_emp_hub_audit($db, $empId, 'বেতন ফেরত আনা', 'salary', (string)$cash, $reason !== '' ? $reason : '0');
    if (function_exists('log_activity')) {
        log_activity('Salary Reversed', 'HRM', 'Salary #' . $salaryId . ' employee ' . $empId . ($reason !== '' ? ' reason: ' . $reason : ''));
    }
}

function ame_emp_hub_delete_snack_row(mysqli $db, int $id, int $expectEmp = 0): void
{
    $stmt = $db->prepare('SELECT * FROM employee_snacks WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        throw new Exception('নাস্তা এন্ট্রি পাওয়া যায়নি।');
    }
    if ($expectEmp > 0 && (int)$row['employee_id'] !== $expectEmp) {
        throw new Exception('এই নাস্তা এই কর্মীর নয়।');
    }
    $del = $db->prepare('DELETE FROM employee_snacks WHERE id = ? LIMIT 1');
    $del->bind_param('i', $id);
    $del->execute();
    $del->close();
    ame_emp_hub_audit($db, (int)$row['employee_id'], 'নাস্তা মুছা', 'snack', (string)($row['amount'] ?? '0'), '0');
}

function ame_emp_hub_delete_advance_row(mysqli $db, int $id, int $expectEmp = 0): void
{
    $stmt = $db->prepare('SELECT * FROM employee_advances WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        throw new Exception('অগ্রিম এন্ট্রি পাওয়া যায়নি।');
    }
    if ($expectEmp > 0 && (int)$row['employee_id'] !== $expectEmp) {
        throw new Exception('এই অগ্রিম এই কর্মীর নয়।');
    }
    $kind = (string)($row['kind'] ?? 'give');
    if ($kind === 'salary_cut') {
        throw new Exception('বেতন সমন্বয় আলাদা মুছা যায় না। বেতন এন্ট্রি মুছুন।');
    }
    $amount = round((float)$row['amount'], 2);
    $acc = (int)($row['account_id'] ?? 0);
    $empId = (int)$row['employee_id'];
    $draft = (string)($row['status'] ?? 'posted') === 'draft';
    if ($draft) {
        $del = $db->prepare('DELETE FROM employee_advances WHERE id = ? LIMIT 1');
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        ame_emp_hub_audit($db, $empId, 'ড্রাফট অগ্রিম মুছা', 'advance', (string)$amount, '0');
        return;
    }
    if ($kind === 'give') {
        if ((int)($row['affects_cash'] ?? 1) === 1 && $acc > 0 && $amount > 0.009) {
            account_credit($acc, $amount, 'employee_advance_reversal', $id, 'অগ্রিম মুছা #' . $id);
        }
        $upd = $db->prepare('UPDATE employees SET advance_balance = GREATEST(0, COALESCE(advance_balance,0) - ?) WHERE id = ?');
        $upd->bind_param('di', $amount, $empId);
        $upd->execute();
        $upd->close();
    } else {
        if ($acc > 0 && $amount > 0.009) {
            account_debit($acc, $amount, 'employee_advance_reversal', $id, 'অগ্রিম ফেরত মুছা #' . $id, true);
        }
        $upd = $db->prepare('UPDATE employees SET advance_balance = COALESCE(advance_balance,0) + ? WHERE id = ?');
        $upd->bind_param('di', $amount, $empId);
        $upd->execute();
        $upd->close();
    }
    $del = $db->prepare('DELETE FROM employee_advances WHERE id = ? LIMIT 1');
    $del->bind_param('i', $id);
    $del->execute();
    $del->close();
    ame_emp_hub_audit($db, $empId, 'অগ্রিম মুছা', 'advance', (string)$amount, '0');
}

function ame_emp_hub_delete_deposit_row(mysqli $db, int $id, int $expectEmp = 0): void
{
    $stmt = $db->prepare('SELECT * FROM employee_shop_deposits WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        throw new Exception('জমা এন্ট্রি পাওয়া যায়নি।');
    }
    if ($expectEmp > 0 && (int)$row['employee_id'] !== $expectEmp) {
        throw new Exception('এই জমা এই কর্মীর নয়।');
    }
    $kind = (string)($row['kind'] ?? 'deposit');
    $amount = round((float)$row['amount'], 2);
    $acc = (int)($row['account_id'] ?? 0);
    $empId = (int)$row['employee_id'];
    if ($kind === 'withdraw') {
        if ($acc > 0 && $amount > 0.009) {
            account_credit($acc, $amount, 'employee_shop_deposit_reversal', $id, 'জমা উত্তোলন মুছা #' . $id);
        }
        $upd = $db->prepare('UPDATE employees SET shop_deposit_balance = COALESCE(shop_deposit_balance,0) + ? WHERE id = ?');
        $upd->bind_param('di', $amount, $empId);
        $upd->execute();
        $upd->close();
    } else {
        if ($acc > 0 && $amount > 0.009) {
            account_debit($acc, $amount, 'employee_shop_deposit_reversal', $id, 'কর্মী জমা মুছা #' . $id, true);
        }
        $upd = $db->prepare('UPDATE employees SET shop_deposit_balance = GREATEST(0, COALESCE(shop_deposit_balance,0) - ?) WHERE id = ?');
        $upd->bind_param('di', $amount, $empId);
        $upd->execute();
        $upd->close();
    }
    $del = $db->prepare('DELETE FROM employee_shop_deposits WHERE id = ? LIMIT 1');
    $del->bind_param('i', $id);
    $del->execute();
    $del->close();
    ame_emp_hub_audit($db, $empId, 'জমা মুছা', 'deposit', (string)$amount, '0');
}

function ame_emp_hub_adjust_schema(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @$db->query("CREATE TABLE IF NOT EXISTS employee_money_adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        box VARCHAR(20) NOT NULL,
        adj_date DATE NOT NULL,
        delta DECIMAL(12,2) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY emp_box (employee_id, box, adj_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ame_emp_hub_box_sum(mysqli $db, int $empId, string $box, string $day = ''): float
{
    if ($empId <= 0) {
        return 0.0;
    }
    ame_emp_hub_adjust_schema($db);
    if ($box === 'balance' || $day === '') {
        $st = @$db->prepare('SELECT COALESCE(SUM(delta),0) d FROM employee_money_adjustments WHERE employee_id = ? AND box = ?');
        if (!$st) {
            return 0.0;
        }
        $st->bind_param('is', $empId, $box);
    } else {
        $st = @$db->prepare('SELECT COALESCE(SUM(delta),0) d FROM employee_money_adjustments WHERE employee_id = ? AND box = ? AND adj_date = ?');
        if (!$st) {
            return 0.0;
        }
        $st->bind_param('iss', $empId, $box, $day);
    }
    $st->execute();
    $n = (float)($st->get_result()->fetch_assoc()['d'] ?? 0);
    $st->close();
    return round($n, 2);
}

function ame_emp_hub_box_current(mysqli $db, array $emp, string $box): float
{
    $id = (int)($emp['id'] ?? 0);
    $today = ame_emp_hub_today();
    if ($box === 'advance') {
        return round((float)($emp['advance_balance'] ?? 0), 2);
    }
    if ($box === 'deposit') {
        return round((float)($emp['shop_deposit_balance'] ?? 0), 2);
    }
    if ($box === 'balance') {
        if (!function_exists('ame_worker_desk_statement')) {
            require_once __DIR__ . '/worker_desk.php';
        }
        $stmt = ame_worker_desk_statement($db, $id);
        return round((float)($stmt['closing'] ?? 0), 2);
    }
    $present = function_exists('ame_staff_is_duty_active') ? ame_staff_is_duty_active($db, $id) : true;
    $work = ame_emp_hub_today_work($db, $id, $today);
    if ($box === 'salary') {
        $snackDue = round((float) ame_emp_hub_pending_snack_pay($db, $id, $today, false, false)['amount'], 2);
        return round(ame_emp_hub_today_salary($db, $emp, $present, $work, $today) + $snackDue, 2);
    }
    return round((float)($work['work_value'] ?? 0), 2);
}

function ame_emp_hub_box_entries(mysqli $db, int $empId, string $box): array
{
    $today = ame_emp_hub_today();
    $out = [];
    if (in_array($box, ['work', 'salary', 'balance'], true)) {
        ame_emp_hub_adjust_schema($db);
        $sql = 'SELECT id, adj_date, delta, note FROM employee_money_adjustments WHERE employee_id = ? AND box = ?';
        if ($box !== 'balance') {
            $sql .= ' AND adj_date = ?';
        }
        $sql .= ' ORDER BY id DESC LIMIT 30';
        $st = @$db->prepare($sql);
        if ($st) {
            if ($box === 'balance') {
                $st->bind_param('is', $empId, $box);
            } else {
                $st->bind_param('iss', $empId, $box, $today);
            }
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $out[] = [
                    'kind' => 'adjust',
                    'id' => (int)$r['id'],
                    'date' => (string)$r['adj_date'],
                    'label' => 'সমন্বয়' . (!empty($r['note']) ? ' · ' . $r['note'] : ''),
                    'amount' => round((float)$r['delta'], 2),
                ];
            }
            $st->close();
        }
    }
    if ($box === 'work') {
        $st = @$db->prepare('SELECT id, work_date, service_type, customer_name, work_value FROM employee_works WHERE employee_id = ? AND work_date = ? ORDER BY id DESC LIMIT 20');
        if ($st) {
            $st->bind_param('is', $empId, $today);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $out[] = [
                    'kind' => 'work',
                    'id' => (int)$r['id'],
                    'date' => (string)$r['work_date'],
                    'label' => trim((string)($r['service_type'] ?? 'কাজ') . ' · ' . (string)($r['customer_name'] ?? ''), ' ·'),
                    'amount' => round((float)$r['work_value'], 2),
                ];
            }
            $st->close();
        }
    }
    if ($box === 'advance' || $box === 'deposit') {
        foreach (ame_emp_hub_money_entries($db, $empId, $box, 20) as $row) {
            $out[] = $row;
        }
    }
    return $out;
}

function ame_emp_hub_box_state(mysqli $db, int $empId, string $box): array
{
    $allowed = ['work', 'salary', 'advance', 'deposit', 'balance'];
    if (!in_array($box, $allowed, true)) {
        return ['ok' => false, 'error' => 'এই বক্স সমন্বয় করা যায় না।'];
    }
    $emp = ame_emp_hub_row($db, $empId, true);
    if (!$emp) {
        return ['ok' => false, 'error' => 'কর্মী পাওয়া যায়নি।'];
    }
    return [
        'ok' => true,
        'box' => $box,
        'current' => ame_emp_hub_box_current($db, $emp, $box),
        'entries' => ame_emp_hub_box_entries($db, $empId, $box),
    ];
}

function ame_emp_hub_adjust_box(mysqli $db, array $in): array
{
    $empId = (int)($in['id'] ?? 0);
    $box = (string)($in['box'] ?? '');
    $allowed = ['work', 'salary', 'advance', 'deposit', 'balance'];
    if (!in_array($box, $allowed, true)) {
        throw new Exception('এই বক্স সমন্বয় করা যায় না।');
    }
    $emp = ame_emp_hub_row($db, $empId, false);
    if (!$emp) {
        throw new Exception('কর্মী পাওয়া যায়নি।');
    }
    $target = round((float)($in['amount'] ?? 0), 2);
    $note = trim((string)($in['note'] ?? ''));
    if ($note === '') {
        $note = 'সমন্বয়';
    }
    $accountId = (int)($in['account_id'] ?? 0);
    $entryKind = (string)($in['entry_kind'] ?? '');
    $entryId = (int)($in['entry_id'] ?? 0);
    $today = ame_emp_hub_today();
    if ($entryId > 0) {
        ame_emp_hub_adjust_entry($db, $empId, $box, $entryKind, $entryId, $target, $note, $accountId);
        return ['ok' => true, 'message' => 'এন্ট্রি সমন্বয় হয়েছে।'];
    }
    if (in_array($box, ['work', 'salary'], true) && $target < -0.009) {
        throw new Exception('এই বক্সে ঋণাত্মক টাকা রাখা যায় না।');
    }
    if (in_array($box, ['advance', 'deposit'], true) && $target < -0.009) {
        throw new Exception('এই বক্সে ঋণাত্মক টাকা রাখা যায় না।');
    }
    $current = ame_emp_hub_box_current($db, $emp, $box);
    $delta = round($target - $current, 2);
    if (abs($delta) < 0.009) {
        return ['ok' => true, 'message' => 'টাকা আগের মতোই আছে।', 'current' => $current];
    }
    if (in_array($box, ['work', 'salary', 'balance'], true)) {
        ame_emp_hub_adjust_schema($db);
        $user = (int)($_SESSION['user_id'] ?? 0);
        $st = $db->prepare('INSERT INTO employee_money_adjustments (employee_id, box, adj_date, delta, note, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $st->bind_param('issdsi', $empId, $box, $today, $delta, $note, $user);
        $st->execute();
        $st->close();
        ame_emp_hub_audit($db, $empId, 'বক্স সমন্বয়', $box, (string)$current, (string)$target);
        return ['ok' => true, 'message' => 'সমন্বয় রাখা হয়েছে।', 'current' => $target];
    }
    if ($box === 'advance') {
        $kind = $delta > 0 ? 'give' : 'collect';
        $amount = abs($delta);
        if ($accountId > 0) {
            ame_post_employee_advance($db, [
                'employee_id' => $empId,
                'account_id' => $accountId,
                'amount' => $amount,
                'kind' => $kind,
                'advance_date' => $today,
                'note' => $note,
                'idempotency_token' => 'box-' . $empId . '-' . bin2hex(random_bytes(6)),
            ]);
        } else {
            ame_emp_hub_memo_advance($db, $empId, $kind, $amount, $today, $note);
        }
        return ['ok' => true, 'message' => 'অগ্রিম সমন্বয় হয়েছে।', 'current' => $target];
    }
    $kind = $delta > 0 ? 'deposit' : 'withdraw';
    $amount = abs($delta);
    if ($accountId > 0) {
        ame_post_employee_shop_deposit($db, [
            'employee_id' => $empId,
            'account_id' => $accountId,
            'amount' => $amount,
            'kind' => $kind,
            'deposit_date' => $today,
            'note' => $note,
            'idempotency_token' => 'boxd-' . $empId . '-' . bin2hex(random_bytes(6)),
        ]);
    } else {
        ame_emp_hub_memo_deposit($db, $empId, $kind, $amount, $today, $note);
    }
    return ['ok' => true, 'message' => 'জমা সমন্বয় হয়েছে।', 'current' => $target];
}

function ame_emp_hub_memo_advance(mysqli $db, int $empId, string $kind, float $amount, string $date, string $note): void
{
    $db->begin_transaction();
    try {
        $lock = $db->prepare('SELECT name, COALESCE(advance_balance,0) AS advance_balance FROM employees WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $empId);
        $lock->execute();
        $emp = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$emp) {
            throw new Exception('কর্মী পাওয়া যায়নি।');
        }
        $previous = round((float)$emp['advance_balance'], 2);
        if ($kind === 'give') {
            $current = round($previous + $amount, 2);
        } else {
            if ($amount > $previous + 0.009) {
                throw new Exception('ফেরত সাবেক অগ্রিমের বেশি হতে পারবে না।');
            }
            $current = round($previous - $amount, 2);
        }
        $upd = $db->prepare('UPDATE employees SET advance_balance = ? WHERE id = ?');
        $upd->bind_param('di', $current, $empId);
        $upd->execute();
        $upd->close();
        $token = bin2hex(random_bytes(16));
        $user = (int)($_SESSION['user_id'] ?? 0);
        $acc = 0;
        $cash = 0;
        $ins = $db->prepare('INSERT INTO employee_advances (employee_id, account_id, amount, kind, advance_date, note, previous_balance, current_balance, affects_cash, idempotency_token, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->bind_param('iidsssddisi', $empId, $acc, $amount, $kind, $date, $note, $previous, $current, $cash, $token, $user);
        $ins->execute();
        $ins->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_emp_hub_memo_deposit(mysqli $db, int $empId, string $kind, float $amount, string $date, string $note): void
{
    $db->begin_transaction();
    try {
        $lock = $db->prepare('SELECT COALESCE(shop_deposit_balance,0) AS shop_deposit_balance FROM employees WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $empId);
        $lock->execute();
        $emp = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$emp) {
            throw new Exception('কর্মী পাওয়া যায়নি।');
        }
        $previous = round((float)$emp['shop_deposit_balance'], 2);
        if ($kind === 'deposit') {
            $current = round($previous + $amount, 2);
        } else {
            if ($amount > $previous + 0.009) {
                throw new Exception('ফেরত জমার বেশি হতে পারবে না।');
            }
            $current = round($previous - $amount, 2);
        }
        $upd = $db->prepare('UPDATE employees SET shop_deposit_balance = ? WHERE id = ?');
        $upd->bind_param('di', $current, $empId);
        $upd->execute();
        $upd->close();
        $token = bin2hex(random_bytes(16));
        $user = (int)($_SESSION['user_id'] ?? 0);
        $acc = 0;
        $ins = $db->prepare('INSERT INTO employee_shop_deposits (employee_id, account_id, amount, kind, deposit_date, note, previous_balance, current_balance, idempotency_token, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->bind_param('iidsssddsi', $empId, $acc, $amount, $kind, $date, $note, $previous, $current, $token, $user);
        $ins->execute();
        $ins->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_emp_hub_adjust_entry(mysqli $db, int $empId, string $box, string $kind, int $entryId, float $target, string $note, int $accountId): void
{
    if ($kind === 'adjust' || ($kind === '' && in_array($box, ['work', 'salary', 'balance'], true) && $box !== 'work')) {
        ame_emp_hub_adjust_schema($db);
        $st = $db->prepare('SELECT id, delta FROM employee_money_adjustments WHERE id = ? AND employee_id = ? AND box = ? LIMIT 1');
        $st->bind_param('iis', $entryId, $empId, $box);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            throw new Exception('এই সমন্বয় এন্ট্রি পাওয়া যায়নি।');
        }
        $upd = $db->prepare('UPDATE employee_money_adjustments SET delta = ?, note = ? WHERE id = ? AND employee_id = ?');
        $upd->bind_param('dsii', $target, $note, $entryId, $empId);
        $upd->execute();
        $upd->close();
        return;
    }
    if ($kind === 'work' || $box === 'work') {
        $st = $db->prepare('SELECT id, work_value FROM employee_works WHERE id = ? AND employee_id = ? LIMIT 1');
        $st->bind_param('ii', $entryId, $empId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            throw new Exception('কাজের এন্ট্রি পাওয়া যায়নি।');
        }
        if ($target < -0.009) {
            throw new Exception('কাজের মূল্য ঋণাত্মক হতে পারবে না।');
        }
        $upd = $db->prepare('UPDATE employee_works SET work_value = ? WHERE id = ? AND employee_id = ?');
        $upd->bind_param('dii', $target, $entryId, $empId);
        $upd->execute();
        $upd->close();
        return;
    }
    throw new Exception('এই এন্ট্রি এখান থেকে এডিট করা যায় না। মুছে নতুন সমন্বয় রাখুন।');
}
