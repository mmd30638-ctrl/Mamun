<?php
/**
 * HRM Schema Helper (mysqli, schema-safe)
 * Path: public_html/includes/hrm_functions.php
 */

function ensure_hrm_schema($db) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if ($db instanceof mysqli && !isset($GLOBALS['db'])) {
        $GLOBALS['db'] = $db;
    }
    if (function_exists('ame_schema_stamp') && ame_schema_stamp('hrm_schema')) {
        return;
    }

    // বিভাগ টেবিল (আগে থেকেই থাকতে পারে)
    $db->query("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dept_name VARCHAR(100) NOT NULL,
        total_employees INT DEFAULT 0,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // পদ টেবিল
    $db->query("CREATE TABLE IF NOT EXISTS positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        position_name VARCHAR(100) NOT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        UNIQUE KEY uniq_position_name (position_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // পুরনো departments/positions টেবিলে status কলাম আছে কিনা যাচাই
    $dept_cols = [];
    $dept_res = $db->query("SHOW COLUMNS FROM departments");
    if ($dept_res) { while ($c = $dept_res->fetch_assoc()) { $dept_cols[$c['Field']] = true; } }
    if (!isset($dept_cols['status'])) {
        $db->query("ALTER TABLE departments ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
    }

    $pos_cols = [];
    $pos_res = $db->query("SHOW COLUMNS FROM positions");
    if ($pos_res) { while ($c = $pos_res->fetch_assoc()) { $pos_cols[$c['Field']] = true; } }
    if (!isset($pos_cols['status'])) {
        $db->query("ALTER TABLE positions ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
    }

    // employees টেবিলে department_id কলাম আছে কিনা যাচাই করে, না থাকলে যোগ করা
    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM employees");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $cols[] = $col['Field'];
        }
    }
    if (!in_array('department_id', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN department_id INT NULL DEFAULT NULL");
    }

    // বেতনের ধরন (মাসিক/দৈনিক) কলাম আছে কিনা যাচাই
    if (!in_array('salary_type', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN salary_type ENUM('monthly','daily') NOT NULL DEFAULT 'monthly'");
    }

    // ছবি, ঠিকানা, কর্মী ID, যোগদানের তারিখ কলাম আছে কিনা যাচাই
    if (!in_array('photo', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL");
    }
    if (!in_array('address', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN address VARCHAR(255) NULL DEFAULT NULL");
    }
    if (!in_array('employee_code', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN employee_code VARCHAR(30) NULL DEFAULT NULL");
    }
    // কর্মী আইডি এখন ম্যানুয়ালিও বসানো যায় — দুইজন কর্মীর আইডি যেন কখনো এক না হয়,
    // সেজন্য DB-লেভেলে ইউনিক কী (একসাথে দুই রিকোয়েস্ট এলেও রেস-কন্ডিশন আটকাবে)।
    $emp_index_result = $db->query("SHOW INDEX FROM employees");
    $emp_has_unique_code = false;
    if ($emp_index_result) {
        while ($idx = $emp_index_result->fetch_assoc()) {
            if (($idx['Key_name'] ?? '') === 'uniq_employee_code') {
                $emp_has_unique_code = true;
                break;
            }
        }
    }
    if (!$emp_has_unique_code) {
        $added = @$db->query("ALTER TABLE employees ADD UNIQUE KEY uniq_employee_code (employee_code)");
        if (!$added) {
            // যোগ হয়নি — মানে ডেটাবেজে আগে থেকেই একাধিক কর্মীর কোড এক (ডুপ্লিকেট)।
            // প্রতিটা ডুপ্লিকেট গ্রুপের সবচেয়ে পুরনো (ছোট id) কর্মীটা তার কোড রাখবে,
            // বাকিদের নতুন ফাঁকা কোড বসিয়ে দেওয়া হবে — তারপর ইউনিক কী আবার বসানো হবে।
            $dupRes = @$db->query(
                "SELECT employee_code FROM employees
                 WHERE employee_code IS NOT NULL AND employee_code <> ''
                 GROUP BY employee_code HAVING COUNT(*) > 1"
            );
            if ($dupRes && $dupRes->num_rows > 0) {
                $codes = [];
                while ($row = $dupRes->fetch_assoc()) {
                    $codes[] = $row['employee_code'];
                }
                foreach ($codes as $dupCode) {
                    $rowsStmt = $db->prepare("SELECT id, name FROM employees WHERE employee_code = ? ORDER BY id ASC");
                    if (!$rowsStmt) {
                        continue;
                    }
                    $rowsStmt->bind_param('s', $dupCode);
                    $rowsStmt->execute();
                    $rows = $rowsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $rowsStmt->close();
                    // প্রথমটা (সবচেয়ে পুরনো) বাদ দিয়ে বাকিদের নতুন কোড বসানো
                    for ($i = 1; $i < count($rows); $i++) {
                        $newCode = generate_employee_code($db);
                        $upd = $db->prepare("UPDATE employees SET employee_code = ? WHERE id = ?");
                        if ($upd) {
                            $upd->bind_param('si', $newCode, $rows[$i]['id']);
                            $upd->execute();
                            $upd->close();
                            if (function_exists('log_activity')) {
                                log_activity(
                                    'Employee code fixed',
                                    'HRM',
                                    ($rows[$i]['name'] ?? '') . ': ' . $dupCode . ' → ' . $newCode . ' (duplicate code auto-corrected)'
                                );
                            }
                        }
                    }
                }
            }
            $added = @$db->query("ALTER TABLE employees ADD UNIQUE KEY uniq_employee_code (employee_code)");
            if (!$added) {
                error_log('hrm_functions: could not add uniq_employee_code even after de-dup — ' . $db->error);
            }
        }
    }
    if (!in_array('joining_date', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN joining_date DATE NULL DEFAULT NULL");
    }
    if (!in_array('service_share_percent', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN service_share_percent DECIMAL(5,2) NOT NULL DEFAULT 50.00");
    }
    if (!in_array('payment_type', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'percentage'");
    }

    // "ডিলিট" — সত্যিকারের DELETE না করে সরানোর তারিখ বসানো, যাতে পুরনো
    // বেতন/অগ্রিম/হিসাবের রেকর্ড অক্ষত থাকে (users.php-এর "Remove" প্যাটার্নের মতোই)
    if (!in_array('removed_at', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN removed_at DATETIME DEFAULT NULL");
    }
    if (!in_array('removed_by', $cols, true)) {
        $db->query("ALTER TABLE employees ADD COLUMN removed_by INT DEFAULT NULL");
    }
    $emp_late_cols = [
        'is_engineer' => 'ALTER TABLE employees ADD COLUMN is_engineer TINYINT(1) NOT NULL DEFAULT 0',
        'is_service' => 'ALTER TABLE employees ADD COLUMN is_service TINYINT(1) NOT NULL DEFAULT 0',
        'is_repair' => 'ALTER TABLE employees ADD COLUMN is_repair TINYINT(1) NOT NULL DEFAULT 0',
        'is_sales' => 'ALTER TABLE employees ADD COLUMN is_sales TINYINT(1) NOT NULL DEFAULT 0',
        'user_role' => "ALTER TABLE employees ADD COLUMN user_role VARCHAR(40) NULL DEFAULT NULL",
        'name_snapshot' => 'ALTER TABLE employees ADD COLUMN name_snapshot VARCHAR(150) NULL DEFAULT NULL',
        'purged_at' => 'ALTER TABLE employees ADD COLUMN purged_at DATETIME NULL DEFAULT NULL',
    ];
    foreach ($emp_late_cols as $emp_col => $emp_sql) {
        if (!in_array($emp_col, $cols, true)) {
            $db->query($emp_sql);
            $cols[] = $emp_col;
        }
    }

    /*
     * users টেবিলে employee_id সংযোগ কলাম এবং username-এ
     * ইউনিক কী যোগ করা (একেক কর্মীর জন্য আলাদা লগইন একাউন্ট)
     */
    $user_cols = [];
    $user_columns_result = $db->query("SHOW COLUMNS FROM users");
    if ($user_columns_result) {
        while ($col = $user_columns_result->fetch_assoc()) {
            $user_cols[$col['Field']] = true;
        }
    }
    if (!isset($user_cols['employee_id'])) {
        $db->query("ALTER TABLE users ADD COLUMN employee_id INT NULL DEFAULT NULL");
    }

    $user_indexes = [];
    $user_index_result = $db->query("SHOW INDEX FROM users");
    if ($user_index_result) {
        while ($row = $user_index_result->fetch_assoc()) {
            $user_indexes[] = $row['Key_name'];
        }
    }
    if (!in_array('uniq_username', $user_indexes, true)) {
        // আগে থেকে ডুপ্লিকেট username থাকলে ব্যর্থ হতে পারে, তাতেও সাইট চলতে থাকবে
        @$db->query("ALTER TABLE users ADD UNIQUE KEY uniq_username (username)");
    }

    /*
     * engineers টেবিলে employee_id সংযোগ কলাম (কর্মী = ইঞ্জিনিয়ার লিংক)
     */
    $eng_cols = [];
    $eng_res = $db->query("SHOW COLUMNS FROM engineers");
    if ($eng_res) { while ($c = $eng_res->fetch_assoc()) { $eng_cols[$c['Field']] = true; } }
    if (!isset($eng_cols['employee_id'])) {
        $db->query("ALTER TABLE engineers ADD COLUMN employee_id INT NULL DEFAULT NULL");
    }
    if (!isset($eng_cols['phone'])) {
        $db->query("ALTER TABLE engineers ADD COLUMN phone VARCHAR(30) NULL DEFAULT NULL");
    }
    $eng_dup = @$db->query(
        "SELECT employee_id
         FROM engineers
         WHERE employee_id IS NOT NULL AND employee_id > 0
         GROUP BY employee_id HAVING COUNT(*) > 1 LIMIT 1"
    );
    if ($eng_dup && $eng_dup->num_rows === 0) {
        // NULL একাধিক থাকতে পারে; কিন্তু একই কর্মীর দুইটি সক্রিয় engineer পরিচয় আর হবে না।
        @$db->query('ALTER TABLE engineers ADD UNIQUE KEY uniq_engineer_employee (employee_id)');
    } elseif ($eng_dup && $eng_dup->num_rows > 0) {
        error_log('HRM identity guard: duplicate engineer.employee_id rows need manual merge; unique key deferred.');
    }

    /*
     * সার্ভিস কমিশন ট্র্যাকিং টেবিল
     */
    $db->query("CREATE TABLE IF NOT EXISTS service_commissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        sale_id INT NULL,
        service_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        commission_rate DECIMAL(5,2) NOT NULL DEFAULT 50.00,
        commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY employee_id (employee_id),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $sc_cols = [];
    $sc_res = $db->query("SHOW COLUMNS FROM service_commissions");
    if ($sc_res) {
        while ($c = $sc_res->fetch_assoc()) {
            $sc_cols[$c['Field']] = true;
        }
    }
    if (!isset($sc_cols['shop_amount'])) {
        $db->query("ALTER TABLE service_commissions ADD COLUMN shop_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
    }
    if (!isset($sc_cols['engineer_id'])) {
        $db->query("ALTER TABLE service_commissions ADD COLUMN engineer_id INT NULL DEFAULT NULL");
    }
    if (!isset($sc_cols['service_type'])) {
        $db->query("ALTER TABLE service_commissions ADD COLUMN service_type VARCHAR(255) NULL DEFAULT NULL");
    }
    if (!isset($sc_cols['payout_id'])) {
        $db->query("ALTER TABLE service_commissions ADD COLUMN payout_id INT NULL DEFAULT NULL");
    }
    if (!isset($sc_cols['source_type'])) {
        $db->query("ALTER TABLE service_commissions ADD COLUMN source_type VARCHAR(30) NOT NULL DEFAULT 'sale_service'");
    }
    if (!isset($sc_cols['source_id'])) {
        $db->query("ALTER TABLE service_commissions ADD COLUMN source_id INT NULL DEFAULT NULL");
    }
    if (!isset($sc_cols['salary_id'])) {
        $db->query("ALTER TABLE service_commissions ADD COLUMN salary_id INT NULL DEFAULT NULL");
    }
    @$db->query('ALTER TABLE service_commissions ADD INDEX idx_commission_source (source_type, source_id)');
    $sc_dup = @$db->query(
        "SELECT source_type, source_id
         FROM service_commissions
         WHERE source_id IS NOT NULL AND source_id > 0
         GROUP BY source_type, source_id HAVING COUNT(*) > 1 LIMIT 1"
    );
    if ($sc_dup && $sc_dup->num_rows === 0) {
        @$db->query('ALTER TABLE service_commissions ADD UNIQUE KEY uniq_commission_source (source_type, source_id)');
    } elseif ($sc_dup && $sc_dup->num_rows > 0) {
        error_log('HRM commission guard: historical duplicate source rows found; unique key deferred.');
    }
    @$db->query(
        "UPDATE service_commissions
         SET shop_amount = ROUND(service_amount - commission_amount, 2)
         WHERE shop_amount = 0 AND service_amount <> commission_amount"
    );

    $db->query("CREATE TABLE IF NOT EXISTS worker_payouts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        account_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        payout_date DATE NOT NULL,
        note VARCHAR(255) NULL DEFAULT NULL,
        created_by INT NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY employee_id (employee_id),
        KEY payout_date (payout_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // শুধু ইঞ্জিনিয়ার-চিহ্নিত কর্মীদের সার্ভিস তালিকায় সিঙ্ক করা
    try {
        $unsynced = $db->query(
            "SELECT e.id FROM employees e
             WHERE COALESCE(e.is_engineer, 0) = 1
               AND (e.purged_at IS NULL)
               AND (e.removed_at IS NULL)
               AND NOT EXISTS (SELECT 1 FROM engineers g WHERE g.employee_id = e.id)"
        );
        if ($unsynced && $unsynced->num_rows > 0) {
            while ($row = $unsynced->fetch_assoc()) {
                sync_employee_as_engineer($db, (int)$row['id']);
            }
        }
        ame_link_orphan_engineers($db);
        ame_link_staff_users($db);
    } catch (Throwable $e) {
        error_log('HRM identity sync skipped: ' . $e->getMessage());
    }

    /*
     * salaries টেবিলে বোনাস/কর্তন আলাদা রাখার কলাম যোগ করা
     */
    $salary_cols = [];
    $salary_columns_result = $db->query("SHOW COLUMNS FROM salaries");
    if ($salary_columns_result) {
        while ($col = $salary_columns_result->fetch_assoc()) {
            $salary_cols[$col['Field']] = true;
        }
    }
    if (!isset($salary_cols['base_amount'])) {
        $db->query("ALTER TABLE salaries ADD COLUMN base_amount DECIMAL(15,2) NOT NULL DEFAULT 0");
    }
    if (!isset($salary_cols['bonus_amount'])) {
        $db->query("ALTER TABLE salaries ADD COLUMN bonus_amount DECIMAL(15,2) NOT NULL DEFAULT 0");
    }
    if (!isset($salary_cols['deduction_amount'])) {
        $db->query("ALTER TABLE salaries ADD COLUMN deduction_amount DECIMAL(15,2) NOT NULL DEFAULT 0");
    }
    if (!isset($salary_cols['snacks_amount'])) {
        $db->query("ALTER TABLE salaries ADD COLUMN snacks_amount DECIMAL(15,2) NOT NULL DEFAULT 0");
    }
    if (!isset($salary_cols['commission_amount'])) {
        $db->query("ALTER TABLE salaries ADD COLUMN commission_amount DECIMAL(15,2) NOT NULL DEFAULT 0");
    }
    $salary_extra = [
        'payment_method' => "ALTER TABLE salaries ADD COLUMN payment_method VARCHAR(30) NOT NULL DEFAULT 'cash'",
        'reference_no' => "ALTER TABLE salaries ADD COLUMN reference_no VARCHAR(80) NULL DEFAULT NULL",
        'notes' => "ALTER TABLE salaries ADD COLUMN notes VARCHAR(255) NULL DEFAULT NULL",
        'status' => "ALTER TABLE salaries ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'paid'",
        'advance_cut' => "ALTER TABLE salaries ADD COLUMN advance_cut DECIMAL(15,2) NOT NULL DEFAULT 0",
        'reversed_at' => "ALTER TABLE salaries ADD COLUMN reversed_at DATETIME NULL DEFAULT NULL",
    ];
    foreach ($salary_extra as $col => $sql) {
        if (!isset($salary_cols[$col])) {
            $db->query($sql);
        }
    }

    ensure_employee_advance_schema($db);

    $db->query("CREATE TABLE IF NOT EXISTS employee_work_descriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        engineer_id INT NOT NULL,
        employee_id INT NULL DEFAULT NULL,
        description VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_work_desc_engineer (engineer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (function_exists('ensure_earnings_schema')) {
        ensure_earnings_schema($db);
    }
    if (function_exists('ame_schema_stamp')) {
        ame_schema_stamp('hrm_schema', true);
    }
}

/**
 * নতুন কর্মীর জন্য অটো-জেনারেটেড ইউনিক কোড (EMP-0001, EMP-0002...)
 */
/**
 * নতুন কর্মীর জন্য অটো কোড — আগে শুধু "সর্বশেষ id + 1" ধরে নিত, যেটা
 * কর্মী ডিলিট হলে, কোড ম্যানুয়ালি বসালে, বা দুইজন প্রায় একইসাথে যোগ করলে
 * সহজেই আগে থেকে ব্যবহৃত একটা কোডের সাথে মিলে যেত (ডুপ্লিকেট কোড)।
 * এখন বিদ্যমান সবচেয়ে বড় EMP-#### নম্বর থেকে শুরু করে, ডেটাবেজে সত্যিই
 * ফাঁকা না পাওয়া পর্যন্ত খুঁজে যায় — এবং সংক্ষিপ্ত সময়ের জন্য লক নিয়ে
 * দুইটা রিকোয়েস্ট একই মুহূর্তে একই কোড পাওয়া ঠেকায়।
 */
function generate_employee_code($db) {
    $locked = function_exists('ame_lock') ? ame_lock($db, 'hrm_emp_code') : false;
    try {
        $next = 1;
        $res = @$db->query("SELECT employee_code FROM employees WHERE employee_code REGEXP '^EMP-[0-9]+$'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $n = (int) substr((string) $row['employee_code'], 4);
                if ($n >= $next) {
                    $next = $n + 1;
                }
            }
        }

        // সেফটি নেট: সন্দেহ হলে (রিজেক্সপ কাজ না করলে বা ফাঁকা রেজাল্টে) id-ভিত্তিক
        // পুরনো হিসাবটাও মিলিয়ে দেখা — যেটা বড়, সেটাই শুরুর বিন্দু।
        $idRes = @$db->query("SELECT MAX(id) AS m FROM employees");
        if ($idRes && ($row = $idRes->fetch_assoc()) && (int) ($row['m'] ?? 0) >= $next) {
            $next = (int) $row['m'] + 1;
        }

        $check = $db->prepare("SELECT id FROM employees WHERE employee_code = ? LIMIT 1");
        for ($i = 0; $i < 200; $i++) {
            $code = 'EMP-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            if ($check) {
                $check->bind_param('s', $code);
                $check->execute();
                $exists = $check->get_result()->fetch_assoc();
                if (!$exists) {
                    return $code;
                }
            } else {
                return $code;
            }
            $next++;
        }
        return $code;
    } finally {
        if ($locked && function_exists('ame_unlock')) {
            ame_unlock($db, 'hrm_emp_code');
        }
    }
}

/**
 * ফর্মে যে কর্মী আইডি টাইপ করা হয়েছে সেটা যাচাই করে চূড়ান্ত কোড ঠিক করে।
 * খালি রাখলে অটো-জেনারেটেড কোড বসে; টাইপ করলে ফরম্যাট ও ইউনিকনেস চেক হয়।
 *
 * @return array{ok:bool,code:string,error:string}
 */
function resolve_employee_code($db, string $posted, int $exclude_id = 0): array {
    $code = trim($posted);

    if ($code === '') {
        return ['ok' => true, 'code' => generate_employee_code($db), 'error' => ''];
    }

    if (!preg_match('/^[A-Za-z0-9_-]{2,30}$/', $code)) {
        return [
            'ok' => false,
            'code' => $code,
            'error' => 'কর্মী আইডিতে শুধু ইংরেজি অক্ষর, সংখ্যা, - ও _ ব্যবহার করুন (২-৩০ অক্ষর)।',
        ];
    }

    $stmt = $db->prepare("SELECT id FROM employees WHERE employee_code = ? AND id != ? LIMIT 1");
    $stmt->bind_param("si", $code, $exclude_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        return [
            'ok' => false,
            'code' => $code,
            'error' => 'এই কর্মী আইডি (' . $code . ') আরেকজন কর্মীর নামে আগে থেকে আছে — অন্য একটা দিন।',
        ];
    }

    return ['ok' => true, 'code' => $code, 'error' => ''];
}

/**
 * নির্দিষ্ট বিভাগে বর্তমানে সক্রিয় কর্মীর সংখ্যা (লাইভ গণনা)
 */
function get_department_employee_count($db, $department_id) {
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM employees WHERE department_id = ? AND status = 'active'");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0);
}

/**
 * নির্দিষ্ট পদে বর্তমানে সক্রিয় কর্মীর সংখ্যা (লাইভ গণনা)
 */
function get_position_employee_count($db, $position_name) {
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM employees WHERE designation = ? AND status = 'active'");
    $stmt->bind_param("s", $position_name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0);
}

/**
 * কর্মী যোগ/এডিট করার সময় নতুন পদের নাম হলে positions তালিকায় যোগ করে রাখা
 */
function remember_position($db, $position_name) {
    $position_name = trim((string)$position_name);
    if ($position_name === '') {
        return;
    }
    $stmt = $db->prepare("INSERT IGNORE INTO positions (position_name) VALUES (?)");
    $stmt->bind_param("s", $position_name);
    $stmt->execute();
    $stmt->close();
}

/**
 * নির্দিষ্ট কর্মীর সাথে সংযুক্ত লগইন একাউন্ট (থাকলে) ফেরত দেয়
 */
function get_employee_login($db, $employee_id) {
    $stmt = $db->prepare("SELECT id, username, status, role, phone, employee_id FROM users WHERE employee_id = ? ORDER BY id ASC");
    if (!$stmt) {
        $stmt = $db->prepare("SELECT id, username, status FROM users WHERE employee_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    if (!$rows) {
        return null;
    }
    if (count($rows) === 1) {
        return $rows[0];
    }
    $empPhone = '';
    $empName = '';
    $emp = @$db->prepare('SELECT phone, name FROM employees WHERE id = ? LIMIT 1');
    if ($emp) {
        $emp->bind_param('i', $employee_id);
        $emp->execute();
        $er = $emp->get_result()->fetch_assoc();
        $emp->close();
        $empPhone = preg_replace('/\D+/', '', (string)($er['phone'] ?? '')) ?? '';
        $empName = (string)($er['name'] ?? '');
    }
    $tail = strlen($empPhone) >= 10 ? substr($empPhone, -10) : '';
    foreach ($rows as $row) {
        $userTail = preg_replace('/\D+/', '', (string)($row['phone'] ?? '')) ?? '';
        $userTail = strlen($userTail) >= 10 ? substr($userTail, -10) : '';
        if ($tail !== '' && $userTail !== '' && $tail === $userTail) {
            return $row;
        }
        if ($empName !== '' && !ame_names_conflict($empName, (string)($row['username'] ?? ''))) {
            return $row;
        }
    }
    return null;
}

/**
 * কর্মীকে POS-এর ইঞ্জিনিয়ার/সার্ভিস-কর্মী তালিকায় সিঙ্ক করা
 * (কর্মী যোগ/এডিট/স্ট্যাটাস পরিবর্তনের পর কল করতে হবে)
 */
function sync_employee_as_engineer($db, $employee_id) {
    $stmt = $db->prepare("SELECT name, phone, status, is_engineer, is_service, is_repair, purged_at, removed_at FROM employees WHERE id = ?");
    if (!$stmt) {
        $stmt = $db->prepare("SELECT name, phone, status FROM employees WHERE id = ?");
    }
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$emp) {
        return;
    }
    if (trim((string)($emp['purged_at'] ?? '')) !== '' || trim((string)($emp['removed_at'] ?? '')) !== '') {
        return;
    }

    $isEng = array_key_exists('is_engineer', $emp)
        ? (int)(!empty($emp['is_engineer']) || !empty($emp['is_service']) || !empty($emp['is_repair']))
        : 0;
    $eng_status = ($isEng && ($emp['status'] ?? 'active') === 'active') ? 'active' : 'inactive';

    $check = $db->prepare("SELECT id FROM engineers WHERE employee_id = ?");
    $check->bind_param("i", $employee_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        $engRowId = (int)($existing['id'] ?? 0);
        if ($isEng && $engRowId > 0) {
            $update = $db->prepare("UPDATE engineers SET name = ?, phone = ?, status = ? WHERE id = ?");
            if ($update) {
                $update->bind_param("sssi", $emp['name'], $emp['phone'], $eng_status, $engRowId);
                $update->execute();
                $update->close();
            }
        }
        return;
    }
    if (!$isEng) {
        return;
    }
    $insert = $db->prepare("INSERT INTO engineers (name, phone, status, employee_id) VALUES (?, ?, ?, ?)");
    $insert->bind_param("sssi", $emp['name'], $emp['phone'], $eng_status, $employee_id);
    $insert->execute();
    $insert->close();
}

/**
 * একজন কর্মীর মোট অপরিশোধিত (pending) সার্ভিস কমিশন
 */
function get_pending_commission($db, $employee_id) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(commission_amount), 0) AS total FROM service_commissions WHERE employee_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $total = (float)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return $total;
}

function ame_clamp_share_percent($value) {
    $n = (float)$value;
    if ($n < 0) {
        return 0.0;
    }
    if ($n > 100) {
        return 100.0;
    }
    return round($n, 2);
}

function ame_employee_share_percent($db, $employee_id) {
    $employee_id = (int)$employee_id;
    if ($employee_id <= 0) {
        return 50.0;
    }
    $stmt = $db->prepare("SELECT service_share_percent FROM employees WHERE id = ?");
    if (!$stmt) {
        return 50.0;
    }
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || !isset($row['service_share_percent'])) {
        return 50.0;
    }
    return ame_clamp_share_percent($row['service_share_percent']);
}

function ame_names_conflict(string $left, string $right): bool
{
    $norm = static function (string $value): string {
        $value = trim($value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        return preg_replace('/\s+/u', '', $value) ?? '';
    };
    $a = $norm($left);
    $b = $norm($right);
    if ($a === '' || $b === '') {
        return false;
    }
    if ($a === $b || str_contains($a, $b) || str_contains($b, $a)) {
        return false;
    }
    return true;
}

function ame_find_employee_by_phone_or_name($db, $phone, $name) {
    $phone = preg_replace('/\s+/', '', trim((string)$phone));
    $name = trim((string)$name);
    $digits = preg_replace('/\D+/', '', $phone);

    $pick = static function ($stmt) use ($name) {
        if (!$stmt) {
            return 0;
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $chosen = 0;
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if ($name !== '' && function_exists('ame_names_conflict') && ame_names_conflict($name, (string)($row['name'] ?? ''))) {
                    continue;
                }
                $chosen = (int)$row['id'];
                break;
            }
        }
        $stmt->close();
        return $chosen;
    };
    if ($phone !== '') {
        $stmt = $db->prepare("SELECT id, name FROM employees WHERE REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ? ORDER BY id ASC LIMIT 8");
        if ($stmt) {
            $stmt->bind_param("s", $phone);
        }
        $found = $pick($stmt);
        if ($found > 0) {
            return $found;
        }
        $stmt = $db->prepare("SELECT id, name FROM employees WHERE phone = ? ORDER BY id ASC LIMIT 8");
        if ($stmt) {
            $stmt->bind_param("s", $phone);
        }
        $found = $pick($stmt);
        if ($found > 0) {
            return $found;
        }
    }
    if (strlen($digits) >= 10) {
        $tail = substr($digits, -11);
        $stmt = @$db->prepare(
            "SELECT id, name FROM employees
             WHERE RIGHT(REPLACE(REPLACE(REPLACE(IFNULL(phone,''), ' ', ''), '-', ''), '+', ''), 11) = ?
             ORDER BY id ASC LIMIT 8"
        );
        if ($stmt) {
            $stmt->bind_param("s", $tail);
        }
        $found = $pick($stmt);
        if ($found > 0) {
            return $found;
        }
    }

    if ($name !== '') {
        $stmt = $db->prepare("SELECT id FROM employees WHERE name = ? ORDER BY id ASC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return (int)$row['id'];
            }
        }
    }

    return 0;
}

function ame_create_linked_employee($db, $name, $phone, $designation, $status = 'active') {
    $name = trim((string)$name);
    if ($name === '') {
        return 0;
    }
    $phone = trim((string)$phone);
    $designation = trim((string)$designation);
    if ($designation === '') {
        $designation = 'সার্ভিস কর্মী';
    }
    $status = $status === 'inactive' ? 'inactive' : 'active';
    $code = generate_employee_code($db);
    $joining = date('Y-m-d');
    $salary = 0.0;
    $share = 50.0;
    $salary_type = 'monthly';

    $stmt = $db->prepare(
        "INSERT INTO employees
            (name, phone, designation, salary, salary_type, employee_code, joining_date, status, service_share_percent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param(
        "sssdssssd",
        $name,
        $phone,
        $designation,
        $salary,
        $salary_type,
        $code,
        $joining,
        $status,
        $share
    );
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }
    $id = (int)$db->insert_id;
    $stmt->close();
    $engFlag = 0;
    $roleKey = '';
    if (func_num_args() >= 6) {
        $engFlag = !empty(func_get_arg(5)) ? 1 : 0;
    }
    if (func_num_args() >= 7) {
        $roleKey = trim((string)func_get_arg(6));
    }
    if ($id > 0) {
        @$db->query(
            'UPDATE employees SET is_engineer = ' . (int)$engFlag
            . ($roleKey !== '' ? (", user_role = '" . $db->real_escape_string($roleKey) . "'") : '')
            . ', name_snapshot = name WHERE id = ' . (int)$id
        );
    }
    return $id;
}

/**
 * ইঞ্জিনিয়ারের সাথে কর্মী না থাকলে খুঁজে বা তৈরি করে সংযুক্ত করা
 */
function ensure_employee_for_engineer($db, $engineer_id) {
    $engineer_id = (int)$engineer_id;
    if ($engineer_id <= 0) {
        return 0;
    }

    $stmt = $db->prepare("SELECT id, name, phone, status, employee_id, purged_at FROM engineers WHERE id = ?");
    if (!$stmt) {
        $stmt = $db->prepare("SELECT id, name, phone, status, employee_id FROM engineers WHERE id = ?");
    }
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("i", $engineer_id);
    $stmt->execute();
    $eng = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$eng) {
        return 0;
    }
    if (trim((string)($eng['purged_at'] ?? '')) !== '') {
        return (int)($eng['employee_id'] ?? 0);
    }

    $employee_id = (int)($eng['employee_id'] ?? 0);
    if ($employee_id > 0) {
        $chk = $db->prepare("SELECT id FROM employees WHERE id = ?");
        $chk->bind_param("i", $employee_id);
        $chk->execute();
        $ok = (bool)$chk->get_result()->fetch_assoc();
        $chk->close();
        if ($ok) {
            return $employee_id;
        }
    }

    $employee_id = ame_find_employee_by_phone_or_name($db, $eng['phone'] ?? '', $eng['name'] ?? '');
    if ($employee_id <= 0) {
        $eng_status = (($eng['status'] ?? 'active') === 'active') ? 'active' : 'inactive';
        $employee_id = ame_create_linked_employee(
            $db,
            (string)($eng['name'] ?? ''),
            (string)($eng['phone'] ?? ''),
            'সার্ভিস ইঞ্জিনিয়ার',
            $eng_status,
            1,
            'engineer'
        );
    }
    if ($employee_id <= 0) {
        return 0;
    }

    $upd = $db->prepare("UPDATE engineers SET employee_id = ? WHERE id = ?");
    $upd->bind_param("ii", $employee_id, $engineer_id);
    $upd->execute();
    $upd->close();
    return $employee_id;
}

/**
 * লগইন ইউজারকে কর্মী হিসেবে সংযুক্ত করা — অ্যাডমিনসহ সবাই একই প্রোফাইল।
 */
function ensure_employee_for_user($db, $user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return 0;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        return 0;
    }
    if (function_exists('ame_user_is_purged') && ame_user_is_purged($user)) {
        return (int)($user['employee_id'] ?? 0);
    }
    if (function_exists('ame_user_is_removed') && ame_user_is_removed($user)) {
        $existing = (int)($user['employee_id'] ?? 0);
        return $existing > 0 ? $existing : 0;
    }

    $role = strtolower(trim((string)($user['role'] ?? '')));
    $employee_id = (int)($user['employee_id'] ?? 0);
    if ($employee_id > 0) {
        $chk = $db->prepare("SELECT id FROM employees WHERE id = ?");
        $chk->bind_param("i", $employee_id);
        $chk->execute();
        $ok = (bool)$chk->get_result()->fetch_assoc();
        $chk->close();
        if ($ok) {
            ame_sync_user_employee_identity($db, $user_id, $employee_id);
            sync_employee_as_engineer($db, $employee_id);
            return $employee_id;
        }
    }

    $display = '';
    if (function_exists('ame_recalled_user_name')) {
        $display = ame_recalled_user_name($db, $user_id);
    }
    if ($display === '') {
        $display = trim((string)($user['username'] ?? 'কর্মী'));
    }
    $phone = trim((string)($user['phone'] ?? ''));

    $employee_id = 0;
    if ($phone !== '') {
        $employee_id = ame_find_employee_by_phone_or_name($db, $phone, '');
    }
    if ($employee_id <= 0) {
        $isEng = in_array($role, ['engineer', 'service', 'repair'], true) ? 1 : 0;
        $desig = $role !== '' ? $role : 'কর্মী';
        if (function_exists('ame_role_label_for')) {
            $lab = trim(ame_role_label_for($db, $role));
            if ($lab !== '') {
                $desig = $lab;
            }
        }
        $employee_id = ame_create_linked_employee(
            $db,
            $display,
            $phone,
            $desig,
            'active',
            $isEng,
            $role
        );
    }
    if ($employee_id <= 0) {
        return 0;
    }

    $upd = $db->prepare("UPDATE users SET employee_id = ? WHERE id = ?");
    if ($upd) {
        $upd->bind_param("ii", $employee_id, $user_id);
        $upd->execute();
        $upd->close();
    }
    ame_sync_user_employee_identity($db, $user_id, $employee_id);
    sync_employee_as_engineer($db, $employee_id);
    return $employee_id;
}

function ame_sync_user_employee_identity($db, $user_id, $employee_id): void
{
    $user_id = (int)$user_id;
    $employee_id = (int)$employee_id;
    if ($user_id <= 0 || $employee_id <= 0 || !($db instanceof mysqli)) {
        return;
    }
    $u = null;
    $st = @$db->prepare('SELECT id, username, phone, role FROM users WHERE id = ? LIMIT 1');
    if ($st) {
        $st->bind_param('i', $user_id);
        $st->execute();
        $u = $st->get_result()->fetch_assoc();
        $st->close();
    }
    $e = null;
    $st = @$db->prepare('SELECT id, name, phone, user_role, designation FROM employees WHERE id = ? LIMIT 1');
    if ($st) {
        $st->bind_param('i', $employee_id);
        $st->execute();
        $e = $st->get_result()->fetch_assoc();
        $st->close();
    }
    if (!$u || !$e) {
        return;
    }
    $ePhone = preg_replace('/\s+/', '', trim((string)($e['phone'] ?? '')));
    $uPhone = preg_replace('/\s+/', '', trim((string)($u['phone'] ?? '')));
    $phone = $ePhone !== '' ? $ePhone : $uPhone;
    if ($phone !== '') {
        if ($ePhone !== $phone) {
            $up = @$db->prepare('UPDATE employees SET phone = ? WHERE id = ?');
            if ($up) {
                $up->bind_param('si', $phone, $employee_id);
                $up->execute();
                $up->close();
            }
        }
        if ($uPhone !== $phone) {
            $up = @$db->prepare('UPDATE users SET phone = ? WHERE id = ?');
            if ($up) {
                $up->bind_param('si', $phone, $user_id);
                $up->execute();
                $up->close();
            }
        }
    }
    $name = trim((string)($e['name'] ?? ''));
    if ($name === '') {
        $name = function_exists('ame_recalled_user_name') ? ame_recalled_user_name($db, $user_id) : '';
        if ($name === '') {
            $name = trim((string)($u['username'] ?? ''));
        }
        if ($name !== '') {
            $up = @$db->prepare('UPDATE employees SET name = ?, name_snapshot = ? WHERE id = ?');
            if ($up) {
                $up->bind_param('ssi', $name, $name, $employee_id);
                $up->execute();
                $up->close();
            }
        }
    }
    if ($name !== '' && function_exists('ame_remember_user_name')) {
        ame_remember_user_name($db, $user_id, $name);
    }
    $empRole = trim((string)($e['user_role'] ?? ''));
    $userRole = strtolower(trim((string)($u['role'] ?? '')));
    if ($empRole === '' && $userRole !== '') {
        $up = @$db->prepare('UPDATE employees SET user_role = ? WHERE id = ?');
        if ($up) {
            $up->bind_param('si', $userRole, $employee_id);
            $up->execute();
            $up->close();
        }
    }
}

function ame_link_staff_users($db) {
    $users_table = $db->query("SHOW TABLES LIKE 'users'");
    if (!$users_table || $users_table->num_rows === 0) {
        return;
    }
    $where = '(employee_id IS NULL OR employee_id = 0)';
    $cols = [];
    $colRes = @$db->query('SHOW COLUMNS FROM users');
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) {
            $cols[$c['Field']] = true;
        }
    }
    if (isset($cols['removed_at'])) {
        $where .= ' AND (removed_at IS NULL OR removed_at = \'\')';
    }
    if (isset($cols['purged_at'])) {
        $where .= ' AND (purged_at IS NULL OR purged_at = \'\')';
    }
    $staff = $db->query('SELECT id FROM users WHERE ' . $where);
    if (!$staff) {
        return;
    }
    while ($row = $staff->fetch_assoc()) {
        ensure_employee_for_user($db, (int)$row['id']);
    }
    $linkedSql = 'SELECT id, employee_id FROM users WHERE employee_id IS NOT NULL AND employee_id > 0';
    if (isset($cols['purged_at'])) {
        $linkedSql .= " AND (purged_at IS NULL OR purged_at = '')";
    }
    if (isset($cols['removed_at'])) {
        $linkedSql .= " AND (removed_at IS NULL OR removed_at = '')";
    }
    $linked = @$db->query($linkedSql);
    if ($linked) {
        while ($row = $linked->fetch_assoc()) {
            ame_sync_user_employee_identity($db, (int)$row['id'], (int)$row['employee_id']);
        }
    }
}

function ame_link_orphan_engineers($db) {
    $eng_table = $db->query("SHOW TABLES LIKE 'engineers'");
    if (!$eng_table || $eng_table->num_rows === 0) {
        return;
    }
    $orphans = $db->query(
        "SELECT id FROM engineers WHERE (employee_id IS NULL OR employee_id = 0)
           AND (purged_at IS NULL OR purged_at = '')"
    );
    if (!$orphans) {
        return;
    }
    while ($row = $orphans->fetch_assoc()) {
        ensure_employee_for_engineer($db, (int)$row['id']);
    }
}

/**
 * বিক্রয় সম্পন্ন হলে সার্ভিস বিল কর্মী ও দোকানের শতাংশ অনুযায়ী ভাগ করা
 */
function record_service_commission(
    $db,
    $sale_id,
    $engineer_id,
    $service_amount,
    $service_type = '',
    $source_type = 'sale_service',
    $source_id = 0
) {
    if (function_exists('ame_engineer_bill_record')) {
        ame_engineer_bill_record($db, $sale_id, $engineer_id, $service_amount, $service_type, $source_type, $source_id);
        return;
    }
    if ($engineer_id <= 0 || $service_amount <= 0) {
        return;
    }

    try {
        ensure_hrm_schema($db);

        $employee_id = ensure_employee_for_engineer($db, $engineer_id);
        if ($employee_id <= 0) {
            return;
        }

        $workDate = date('Y-m-d');
        if ($source_type === 'sale_service' && (int)$sale_id > 0) {
            $dateStmt = @$db->prepare('SELECT DATE(created_at) AS work_date FROM sales WHERE id = ? LIMIT 1');
            if ($dateStmt) {
                $saleDateId = (int)$sale_id;
                $dateStmt->bind_param('i', $saleDateId);
                $dateStmt->execute();
                $workDate = (string)($dateStmt->get_result()->fetch_assoc()['work_date'] ?? $workDate);
                $dateStmt->close();
            }
        } elseif ($source_type === 'workshop_job' && (int)$source_id > 0) {
            $dateStmt = @$db->prepare('SELECT DATE(COALESCE(delivered_at, created_at)) AS work_date FROM workshop_jobs WHERE id = ? LIMIT 1');
            if ($dateStmt) {
                $jobDateId = (int)$source_id;
                $dateStmt->bind_param('i', $jobDateId);
                $dateStmt->execute();
                $workDate = (string)($dateStmt->get_result()->fetch_assoc()['work_date'] ?? $workDate);
                $dateStmt->close();
            }
        }
        $payMode = 'percentage';
        if (function_exists('ame_earnings_quote')) {
            $quote = ame_earnings_quote($db, $employee_id, (float)$service_amount, 1.0, 1.0, $workDate);
            $payMode = strtolower((string)$quote['payment_type']);
        }
        if (in_array($payMode, ['daily', 'monthly'], true)) {
            $rate = 0.0;
            $commission_amount = 0.0;
            $shop_amount = round((float)$service_amount, 2);
        } elseif (function_exists('ame_earnings_quote')) {
            $rate = $quote['payment_type'] === 'percentage' ? (float)$quote['percent_rate'] : 0.0;
            $commission_amount = (float)$quote['earning'];
            $shop_amount = (float)$quote['company'];
        } else {
            $rate = ame_employee_share_percent($db, $employee_id);
            $commission_amount = round($service_amount * ($rate / 100), 2);
            $shop_amount = round($service_amount - $commission_amount, 2);
        }
        $stype = substr(trim((string)$service_type), 0, 255);
        $source_type = substr(trim((string)$source_type), 0, 30);
        if ($source_type === '') {
            $source_type = 'sale_service';
        }
        $source_id = (int)$source_id;
        if ($source_id > 0) {
            $exists = $db->prepare(
                'SELECT id FROM service_commissions WHERE source_type = ? AND source_id = ? LIMIT 1'
            );
            if ($exists) {
                $exists->bind_param('si', $source_type, $source_id);
                $exists->execute();
                $duplicate = $exists->get_result()->fetch_assoc();
                $exists->close();
                if ($duplicate) {
                    return;
                }
            }
        }

        // একই ইনভয়েস/জব আগে কর্মী পেজে হাতে লেখা থাকলে আবার স্বয়ংক্রিয় কমিশন নয়।
        $source_ref = '';
        if ($source_type === 'sale_service' && (int)$sale_id > 0) {
            $refStmt = @$db->prepare('SELECT invoice_number FROM sales WHERE id = ? LIMIT 1');
            if ($refStmt) {
                $saleRefId = (int)$sale_id;
                $refStmt->bind_param('i', $saleRefId);
                $refStmt->execute();
                $source_ref = trim((string)($refStmt->get_result()->fetch_assoc()['invoice_number'] ?? ''));
                $refStmt->close();
            }
        } elseif ($source_type === 'workshop_job' && $source_id > 0) {
            $refStmt = @$db->prepare('SELECT job_no FROM workshop_jobs WHERE id = ? LIMIT 1');
            if ($refStmt) {
                $refStmt->bind_param('i', $source_id);
                $refStmt->execute();
                $source_ref = trim((string)($refStmt->get_result()->fetch_assoc()['job_no'] ?? ''));
                $refStmt->close();
            }
        }
        if ($source_ref !== '') {
            $manual = @$db->prepare(
                "SELECT id FROM employee_works
                 WHERE employee_id = ? AND source_type = 'manual'
                   AND UPPER(TRIM(invoice_ref)) = UPPER(?) LIMIT 1"
            );
            if ($manual) {
                $manual->bind_param('is', $employee_id, $source_ref);
                $manual->execute();
                $already_manual = $manual->get_result()->fetch_assoc();
                $manual->close();
                if ($already_manual) {
                    error_log('Service commission skipped: manual work already owns ' . $source_ref);
                    return;
                }
            }
        }

        $insert = $db->prepare(
            "INSERT INTO service_commissions
                (employee_id, sale_id, service_amount, commission_rate, commission_amount, shop_amount, engineer_id, service_type, source_type, source_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), 'pending')"
        );
        if (!$insert) {
            return;
        }
        $insert->bind_param(
            "iiddddissi",
            $employee_id,
            $sale_id,
            $service_amount,
            $rate,
            $commission_amount,
            $shop_amount,
            $engineer_id,
            $stype,
            $source_type,
            $source_id
        );
        $insert->execute();
        $commission_id = (int)$db->insert_id;
        $insert->close();
        if (function_exists('ame_earnings_lock_from_commission')) {
            ame_earnings_lock_from_commission($db, $commission_id);
        }
    } catch (Throwable $e) {
        // কমিশন রেকর্ড ব্যর্থ হলেও আসল বিক্রয় যেন কখনো আটকে না যায়
        error_log('Service commission record failed: ' . $e->getMessage());
    }
}

/**
 * বেতন বা সার্ভিস-ভাগ পরিশোধের সময় সব pending কমিশন 'paid' করে দেওয়া
 */
function mark_commissions_paid($db, $employee_id, $payout_id = null) {
    $employee_id = (int)$employee_id;
    $payout_id = $payout_id !== null ? (int)$payout_id : 0;
    if ($employee_id <= 0) {
        return;
    }

    if ($payout_id > 0) {
        $stmt = $db->prepare(
            "UPDATE service_commissions SET status = 'paid', payout_id = ? WHERE employee_id = ? AND status = 'pending'"
        );
        if ($stmt) {
            $stmt->bind_param("ii", $payout_id, $employee_id);
            $stmt->execute();
            $stmt->close();
            if (function_exists('ame_earnings_sync_legacy_paid')) {
                ame_earnings_sync_legacy_paid($db, $employee_id);
            }
            return;
        }
    }

    $stmt = $db->prepare("UPDATE service_commissions SET status = 'paid' WHERE employee_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->close();
    if (function_exists('ame_earnings_sync_legacy_paid')) {
        ame_earnings_sync_legacy_paid($db, $employee_id);
    }
}

function ame_month_bounds($ym) {
    $ym = trim((string)$ym);
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        $ym = date('Y-m');
    }
    $from = $ym . '-01';
    $to = date('Y-m-d', strtotime($from . ' +1 month'));
    return [$ym, $from, $to];
}

function ame_worker_month_rows($db, $ym) {
    [, $from, $to] = ame_month_bounds($ym);
    $sql = "SELECT e.id, e.name, e.phone, e.employee_code, e.service_share_percent,
                   COALESCE(SUM(sc.service_amount), 0) AS service_total,
                   COALESCE(SUM(sc.commission_amount), 0) AS worker_share,
                   COALESCE(SUM(COALESCE(sc.shop_amount, sc.service_amount - sc.commission_amount)), 0) AS shop_share,
                   COALESCE(SUM(CASE WHEN sc.status = 'paid' THEN sc.commission_amount ELSE 0 END), 0) AS paid_share,
                   COALESCE(SUM(CASE WHEN sc.status = 'pending' THEN sc.commission_amount ELSE 0 END), 0) AS due_share
            FROM employees e
            INNER JOIN service_commissions sc
                ON sc.employee_id = e.id
               AND sc.created_at >= ?
               AND sc.created_at < ?
            WHERE (e.status IS NULL OR e.status = '' OR e.status = 'active') AND e.removed_at IS NULL
            GROUP BY e.id, e.name, e.phone, e.employee_code, e.service_share_percent
            ORDER BY e.name ASC";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("ss", $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function ame_worker_statement($db, $employee_id, $ym) {
    $employee_id = (int)$employee_id;
    [$ym, $from, $to] = ame_month_bounds($ym);

    $emp = null;
    $es = $db->prepare("SELECT id, name, phone, employee_code, designation, service_share_percent FROM employees WHERE id = ?");
    if ($es) {
        $es->bind_param("i", $employee_id);
        $es->execute();
        $emp = $es->get_result()->fetch_assoc();
        $es->close();
    }
    if (!$emp) {
        return null;
    }

    $lines = [];
    $sql = "SELECT sc.id, sc.sale_id, sc.service_amount, sc.commission_rate, sc.commission_amount,
                   COALESCE(sc.shop_amount, sc.service_amount - sc.commission_amount) AS shop_amount,
                   sc.service_type, sc.status, sc.created_at, sc.payout_id,
                   s.invoice_number
            FROM service_commissions sc
            LEFT JOIN sales s ON s.id = sc.sale_id
            WHERE sc.employee_id = ?
              AND sc.created_at >= ?
              AND sc.created_at < ?
            ORDER BY sc.created_at ASC, sc.id ASC";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iss", $employee_id, $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $lines[] = $row;
        }
        $stmt->close();
    }

    $payouts = [];
    $ps = $db->prepare(
        "SELECT wp.id, wp.amount, wp.payout_date, wp.note, a.account_name
         FROM worker_payouts wp
         LEFT JOIN accounts a ON a.id = wp.account_id
         WHERE wp.employee_id = ?
           AND wp.payout_date >= ?
           AND wp.payout_date < ?
         ORDER BY wp.payout_date ASC, wp.id ASC"
    );
    if ($ps) {
        $ps->bind_param("iss", $employee_id, $from, $to);
        $ps->execute();
        $pr = $ps->get_result();
        while ($row = $pr->fetch_assoc()) {
            $payouts[] = $row;
        }
        $ps->close();
    }

    $service_total = 0.0;
    $worker_share = 0.0;
    $shop_share = 0.0;
    $paid_share = 0.0;
    $due_share = 0.0;
    foreach ($lines as $line) {
        $service_total += (float)$line['service_amount'];
        $worker_share += (float)$line['commission_amount'];
        $shop_share += (float)$line['shop_amount'];
        if (($line['status'] ?? '') === 'paid') {
            $paid_share += (float)$line['commission_amount'];
        } else {
            $due_share += (float)$line['commission_amount'];
        }
    }

    return [
        'employee' => $emp,
        'ym' => $ym,
        'from' => $from,
        'to' => $to,
        'label' => date('F Y', strtotime($from)),
        'lines' => $lines,
        'payouts' => $payouts,
        'service_total' => $service_total,
        'worker_share' => $worker_share,
        'shop_share' => $shop_share,
        'paid_share' => $paid_share,
        'due_share' => $due_share,
    ];
}

/**
 * সকালের অগ্রিম — কর্মীর নামে সাবেক (ওল্ড) রাখা।
 */
function ensure_employee_advance_schema($db) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $hasAdv = false;
    $advTable = @$db->query("SHOW TABLES LIKE 'employee_advances'");
    if ($advTable && $advTable->num_rows > 0) {
        $hasAdv = true;
    }
    if (!$hasAdv) {
    $db->query(
        "CREATE TABLE IF NOT EXISTS employee_advances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            account_id INT NOT NULL DEFAULT 0,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            kind VARCHAR(20) NOT NULL DEFAULT 'give',
            advance_date DATE NOT NULL,
            note VARCHAR(255) NULL DEFAULT NULL,
            previous_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            current_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            affects_cash TINYINT(1) NOT NULL DEFAULT 1,
            idempotency_token VARCHAR(64) NULL DEFAULT NULL,
            created_by INT NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY employee_id (employee_id),
            KEY advance_date (advance_date),
            KEY kind (kind),
            UNIQUE KEY uniq_adv_idem (idempotency_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    }
    $advCols = [];
    $advColRes = @$db->query('SHOW COLUMNS FROM employee_advances');
    if ($advColRes) {
        while ($c = $advColRes->fetch_assoc()) {
            $advCols[$c['Field']] = true;
        }
    }
    if (!isset($advCols['status'])) {
        @$db->query("ALTER TABLE employee_advances ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'posted'");
    }

    $emp_cols = [];
    $res = $db->query("SHOW COLUMNS FROM employees");
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            $emp_cols[$c['Field']] = true;
        }
    }
    if (!isset($emp_cols['advance_balance'])) {
        $db->query("ALTER TABLE employees ADD COLUMN advance_balance DECIMAL(12,2) NOT NULL DEFAULT 0");
    }
    if (!isset($emp_cols['shop_deposit_balance'])) {
        $db->query("ALTER TABLE employees ADD COLUMN shop_deposit_balance DECIMAL(12,2) NOT NULL DEFAULT 0");
    }

    $hasDep = false;
    $depTable = @$db->query("SHOW TABLES LIKE 'employee_shop_deposits'");
    if ($depTable && $depTable->num_rows > 0) {
        $hasDep = true;
    }
    if (!$hasDep) {
    $db->query(
        "CREATE TABLE IF NOT EXISTS employee_shop_deposits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            account_id INT NOT NULL DEFAULT 0,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            kind VARCHAR(20) NOT NULL DEFAULT 'deposit',
            deposit_date DATE NOT NULL,
            note VARCHAR(255) NULL DEFAULT NULL,
            previous_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            current_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            idempotency_token VARCHAR(64) NULL DEFAULT NULL,
            created_by INT NULL DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY employee_id (employee_id),
            KEY deposit_date (deposit_date),
            KEY kind (kind),
            UNIQUE KEY uniq_shop_dep_idem (idempotency_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    }
}

function ame_hrm_is_admin(): bool
{
    return isset($_SESSION['role']) && strtolower((string) $_SESSION['role']) === 'admin';
}

/** নিয়মিত বেতন m-hrm। অগ্রিম অ্যাডমিন, বেতন দেন এমন ম্যানেজার, অথবা m-hrm-advance। */
function ame_hrm_can_advance(mysqli $db): bool
{
    if (ame_hrm_is_admin()) {
        return true;
    }
    if (!function_exists('ame_worker_can_manage') && is_file(__DIR__ . '/worker_desk.php')) {
        require_once __DIR__ . '/worker_desk.php';
    }
    if (function_exists('ame_worker_can_manage') && ame_worker_can_manage()) {
        return true;
    }
    return function_exists('has_permission') && has_permission($db, 'm-hrm-advance');
}

function ame_employee_advance_balance($db, $employee_id) {
    $stmt = $db->prepare("SELECT COALESCE(advance_balance, 0) AS b FROM employees WHERE id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return round((float)($row['b'] ?? 0), 2);
}

function ame_employee_today_advance_given($db, $employee_id, $date = null) {
    $date = $date ?: date('Y-m-d');
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS t
         FROM employee_advances
         WHERE employee_id = ? AND kind = 'give' AND advance_date = ?"
    );
    $stmt->bind_param("is", $employee_id, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return round((float)($row['t'] ?? 0), 2);
}

function ame_employee_advance_lines($db, $employee_id, $date = null) {
    $date = $date ?: date('Y-m-d');
    $stmt = $db->prepare(
        "SELECT id, amount, note, created_at
         FROM employee_advances
         WHERE employee_id = ? AND kind = 'give' AND advance_date = ?
         ORDER BY id ASC"
    );
    $stmt->bind_param("is", $employee_id, $date);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    return $rows;
}

/**
 * give = দোকান থেকে নগদ অগ্রিম (কর্মী সাবেক বাড়ে, ক্যাশ কমে)
 * collect = কর্মী ফেরত দিল (সাবেক কমে, ক্যাশ বাড়ে)
 * salary_cut = বেতন থেকে কর্তন (সাবেক কমে, আলাদা ক্যাশ চলাচল নেই)
 */
function ame_post_employee_advance($db, array $in) {
    ensure_employee_advance_schema($db);
    $emp_id = (int)($in['employee_id'] ?? 0);
    $account_id = (int)($in['account_id'] ?? 0);
    $amount = round((float)($in['amount'] ?? 0), 2);
    $kind = (string)($in['kind'] ?? 'give');
    $as_draft = ($kind === 'draft') || !empty($in['as_draft']);
    if ($kind !== 'collect' && $kind !== 'salary_cut') {
        $kind = 'give';
    }
    $date = (string)($in['advance_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $note = trim((string)($in['note'] ?? ''));
    $token = trim((string)($in['idempotency_token'] ?? ''));
    $user_id = (int)($in['created_by'] ?? ($_SESSION['user_id'] ?? 0));
    $own_tx = empty($in['in_transaction']);
    $affects_cash = $kind !== 'salary_cut' && !$as_draft;
    if (!function_exists('account_debit')) {
        require_once __DIR__ . '/account_functions.php';
    }

    if ($emp_id <= 0) {
        throw new Exception('কর্মী বাছাই করুন।');
    }
    if ($amount <= 0) {
        throw new Exception('পরিমাণ শূন্যের বেশি হতে হবে।');
    }
    if ($affects_cash && $account_id <= 0) {
        throw new Exception('টাকা কোন অ্যাকাউন্ট থেকে যাবে সেটি বাছাই করুন।');
    }

    if ($token !== '') {
        $chk = $db->prepare("SELECT id, previous_balance, current_balance FROM employee_advances WHERE idempotency_token = ? LIMIT 1");
        $chk->bind_param("s", $token);
        $chk->execute();
        $replay = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($replay) {
            return [
                'ok' => true,
                'replay' => true,
                'id' => (int)$replay['id'],
                'previous' => (float)$replay['previous_balance'],
                'current' => (float)$replay['current_balance'],
            ];
        }
    }

    if ($own_tx) {
        $db->begin_transaction();
    }
    try {
        if ($kind === 'give' && !$as_draft) {
            $dup = $db->prepare(
                "SELECT id FROM employee_advances
                 WHERE employee_id = ? AND kind = 'give' AND ABS(amount - ?) < 0.009
                   AND created_at >= (NOW() - INTERVAL 60 SECOND)
                 ORDER BY id DESC LIMIT 1"
            );
            $dup->bind_param("id", $emp_id, $amount);
            $dup->execute();
            $hit = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($hit) {
                throw new Exception('একই কর্মীকে এই পরিমাণ গত ১ মিনিটেই দেওয়া হয়েছে। আবার কাটেনি।');
            }
        }

        $lock = $db->prepare("SELECT id, name, COALESCE(advance_balance, 0) AS advance_balance FROM employees WHERE id = ? FOR UPDATE");
        $lock->bind_param("i", $emp_id);
        $lock->execute();
        $emp = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$emp) {
            throw new Exception('কর্মী খুঁজে পাওয়া যায়নি।');
        }

        $previous = round((float)$emp['advance_balance'], 2);
        if ($as_draft) {
            $current = $previous;
            if ($note === '') {
                $note = 'সকালের ড্রাফট';
            }
        } elseif ($kind === 'give') {
            $current = round($previous + $amount, 2);
        } else {
            if ($amount > $previous + 0.009) {
                throw new Exception('ফেরত সাবেক অগ্রিমের বেশি হতে পারবে না। সাবেক ৳' . number_format($previous, 2));
            }
            $current = round($previous - $amount, 2);
        }

        if (!$as_draft) {
            $upd = $db->prepare("UPDATE employees SET advance_balance = ? WHERE id = ?");
            $upd->bind_param("di", $current, $emp_id);
            $upd->execute();
            $upd->close();
        }

        $cash_flag = $affects_cash ? 1 : 0;
        $status = $as_draft ? 'draft' : 'posted';
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
        }
        $ins = $db->prepare(
            "INSERT INTO employee_advances
                (employee_id, account_id, amount, kind, advance_date, note, previous_balance, current_balance, affects_cash, idempotency_token, created_by, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param(
            "iidsssddisis",
            $emp_id,
            $account_id,
            $amount,
            $kind,
            $date,
            $note,
            $previous,
            $current,
            $cash_flag,
            $token,
            $user_id,
            $status
        );
        $ins->execute();
        $adv_id = (int)$db->insert_id;
        $ins->close();

        $who = (string)$emp['name'];
        if (!$as_draft && $kind === 'give') {
            account_debit($account_id, $amount, 'employee_advance', $adv_id, 'অগ্রিম: ' . $who);
        } elseif ($kind === 'collect') {
            account_credit($account_id, $amount, 'employee_advance', $adv_id, 'অগ্রিম ফেরত: ' . $who);
        }

        if ($own_tx) {
            $db->commit();
        }
        return [
            'ok' => true,
            'replay' => false,
            'id' => $adv_id,
            'previous' => $previous,
            'current' => $current,
            'name' => $who,
            'status' => $status,
            'message' => $as_draft ? 'সকালের ড্রাফট রাখা হয়েছে। বিকালে বেতনে সমন্বয় অথবা অ্যাকাউন্টে যুক্ত হবে।' : 'অগ্রিম রাখা হয়েছে।',
        ];
    } catch (Throwable $e) {
        if ($own_tx) {
            $db->rollback();
        }
        throw $e;
    }
}

/** সকালের খোলা ড্রাফট — অ্যাকাউন্ট ও সাবেক অগ্রিম এখনো নড়েনি। */
function ame_employee_draft_rows($db, int $emp_id): array
{
    ensure_employee_advance_schema($db);
    $rows = [];
    $total = 0.0;
    if ($emp_id < 1) {
        return ['rows' => $rows, 'total' => 0.0];
    }
    $stmt = $db->prepare(
        "SELECT id, amount, account_id, advance_date, note
         FROM employee_advances
         WHERE employee_id = ? AND kind = 'give' AND status = 'draft'
         ORDER BY id ASC"
    );
    if (!$stmt) {
        return ['rows' => $rows, 'total' => 0.0];
    }
    $stmt->bind_param('i', $emp_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $amount = round((float)$row['amount'], 2);
        $total = round($total + $amount, 2);
        $rows[] = [
            'id' => (int)$row['id'],
            'amount' => $amount,
            'account_id' => (int)$row['account_id'],
            'advance_date' => (string)$row['advance_date'],
            'note' => (string)($row['note'] ?? ''),
        ];
    }
    $stmt->close();
    return ['rows' => $rows, 'total' => $total];
}

/**
 * বিকালে ড্রাফট বন্ধ।
 * settle = বেতনে সমন্বয় (টাকা অ্যাকাউন্ট থেকে যায়, সাবেক অগ্রিম বাড়ে না; বেতনের বেশি হলে বাড়তিটা সাবেক অগ্রিম)।
 * post = সেই অ্যাকাউন্টে যুক্ত (সাবেক অগ্রিম বাড়ে)।
 *
 * @return array{ok:bool,total:float,settled:float,posted:float,current:float}
 */
function ame_close_employee_advance_drafts($db, int $emp_id, string $mode, int $account_id, array $opt = []): array
{
    ensure_employee_advance_schema($db);
    if ($emp_id < 1) {
        throw new Exception('কর্মী বাছাই করুন।');
    }
    if ($mode !== 'post') {
        $mode = 'settle';
    }
    if (!function_exists('account_debit')) {
        require_once __DIR__ . '/account_functions.php';
    }
    $own_tx = empty($opt['in_transaction']);
    $cap = array_key_exists('cap', $opt) ? round(max(0, (float)$opt['cap']), 2) : null;
    if ($own_tx) {
        $db->begin_transaction();
    }
    try {
        $lock = $db->prepare('SELECT id, name, COALESCE(advance_balance,0) AS advance_balance FROM employees WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $emp_id);
        $lock->execute();
        $emp = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$emp) {
            throw new Exception('কর্মী খুঁজে পাওয়া যায়নি।');
        }
        $pack = ame_employee_draft_rows($db, $emp_id);
        $rows = $pack['rows'];
        $total = (float)$pack['total'];
        if ($rows === []) {
            if ($own_tx) {
                $db->commit();
            }
            return ['ok' => true, 'total' => 0.0, 'settled' => 0.0, 'posted' => 0.0, 'current' => round((float)$emp['advance_balance'], 2)];
        }
        $who = (string)$emp['name'];
        $balance = round((float)$emp['advance_balance'], 2);
        $settled = 0.0;
        $posted = 0.0;
        if ($mode === 'settle') {
            $fit = $cap === null ? $total : round(min($total, $cap), 2);
            $extra = round(max(0, $total - $fit), 2);
            foreach ($rows as $row) {
                $useAcc = (int)$row['account_id'] > 0 ? (int)$row['account_id'] : $account_id;
                if ($useAcc < 1) {
                    throw new Exception('ড্রাফট কোন অ্যাকাউন্টে যাবে সেটি বাছাই করুন।');
                }
                if (function_exists('ame_require_selectable_account')) {
                    ame_require_selectable_account($db, $useAcc);
                }
                account_debit($useAcc, (float)$row['amount'], 'employee_advance', (int)$row['id'], 'সকালের ড্রাফট বেতনে সমন্বয়: ' . $who, true);
                $st = 'settled';
                $upd = $db->prepare('UPDATE employee_advances SET status = ?, affects_cash = 1, account_id = ? WHERE id = ?');
                $id = (int)$row['id'];
                $upd->bind_param('sii', $st, $useAcc, $id);
                $upd->execute();
                $upd->close();
            }
            $settled = $fit;
            if ($extra > 0.009) {
                $previous = $balance;
                $balance = round($balance + $extra, 2);
                $bal = $db->prepare('UPDATE employees SET advance_balance = ? WHERE id = ?');
                $bal->bind_param('di', $balance, $emp_id);
                $bal->execute();
                $bal->close();
                $note = 'সকালের ড্রাফটের বাড়তি যা বেতনে ধরেনি';
                $kind = 'give';
                $date = date('Y-m-d');
                $zeroAcc = $account_id > 0 ? $account_id : (int)$rows[0]['account_id'];
                $flag = 0;
                $token = bin2hex(random_bytes(16));
                $user_id = (int)($_SESSION['user_id'] ?? 0);
                $status = 'posted';
                $ins = $db->prepare(
                    'INSERT INTO employee_advances
                        (employee_id, account_id, amount, kind, advance_date, note, previous_balance, current_balance, affects_cash, idempotency_token, created_by, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->bind_param('iidsssddisis', $emp_id, $zeroAcc, $extra, $kind, $date, $note, $previous, $balance, $flag, $token, $user_id, $status);
                $ins->execute();
                $ins->close();
                $posted = $extra;
            }
        } else {
            foreach ($rows as $row) {
                $useAcc = (int)$row['account_id'] > 0 ? (int)$row['account_id'] : $account_id;
                if ($useAcc < 1) {
                    throw new Exception('ড্রাফট কোন অ্যাকাউন্টে যাবে সেটি বাছাই করুন।');
                }
                if (function_exists('ame_require_selectable_account')) {
                    ame_require_selectable_account($db, $useAcc);
                }
                $previous = $balance;
                $balance = round($balance + (float)$row['amount'], 2);
                account_debit($useAcc, (float)$row['amount'], 'employee_advance', (int)$row['id'], 'সকালের ড্রাফট অ্যাকাউন্টে: ' . $who, true);
                $st = 'posted';
                $id = (int)$row['id'];
                $upd = $db->prepare('UPDATE employee_advances SET status = ?, affects_cash = 1, account_id = ?, previous_balance = ?, current_balance = ? WHERE id = ?');
                $upd->bind_param('siddi', $st, $useAcc, $previous, $balance, $id);
                $upd->execute();
                $upd->close();
                unset($amt);
            }
            $bal = $db->prepare('UPDATE employees SET advance_balance = ? WHERE id = ?');
            $bal->bind_param('di', $balance, $emp_id);
            $bal->execute();
            $bal->close();
            $posted = $total;
        }
        if ($own_tx) {
            $db->commit();
        }
        return [
            'ok' => true,
            'total' => $total,
            'settled' => $settled,
            'posted' => $posted,
            'current' => $balance,
            'message' => $mode === 'post'
                ? 'ড্রাফট অ্যাকাউন্টে যুক্ত হয়েছে।'
                : 'ড্রাফট বেতনে সমন্বয় হয়েছে।',
        ];
    } catch (Throwable $e) {
        if ($own_tx) {
            $db->rollback();
        }
        throw $e;
    }
}

/**
 * deposit = কর্মী দোকানে টাকা রাখল (দোকানের অ্যাকাউন্ট বাড়ে, জমা বাড়ে)
 * withdraw = সেই জমা থেকে ফেরত নিল (অ্যাকাউন্ট কমে, জমার বেশি নেওয়া যায় না)
 */
function ame_post_employee_shop_deposit($db, array $in) {
    ensure_employee_advance_schema($db);
    $emp_id = (int) ($in['employee_id'] ?? 0);
    $account_id = (int) ($in['account_id'] ?? 0);
    $amount = round((float) ($in['amount'] ?? 0), 2);
    $kind = (string) ($in['kind'] ?? 'deposit');
    if ($kind !== 'withdraw') {
        $kind = 'deposit';
    }
    $date = (string) ($in['deposit_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $note = trim((string) ($in['note'] ?? ''));
    $token = trim((string) ($in['idempotency_token'] ?? ''));
    $user_id = (int) ($in['created_by'] ?? ($_SESSION['user_id'] ?? 0));
    if (!function_exists('account_credit')) {
        require_once __DIR__ . '/account_functions.php';
    }
    if ($emp_id <= 0) {
        throw new Exception('কর্মী বাছাই করুন।');
    }
    if ($amount <= 0) {
        throw new Exception('পরিমাণ শূন্যের বেশি হতে হবে।');
    }
    if ($account_id <= 0) {
        throw new Exception('টাকা কোন অ্যাকাউন্টে জমা হবে সেটি বাছাই করুন।');
    }

    if ($token !== '') {
        $chk = $db->prepare('SELECT id, previous_balance, current_balance FROM employee_shop_deposits WHERE idempotency_token = ? LIMIT 1');
        $chk->bind_param('s', $token);
        $chk->execute();
        $replay = $chk->get_result()->fetch_assoc();
        $chk->close();
        if ($replay) {
            return [
                'ok' => true,
                'replay' => true,
                'id' => (int) $replay['id'],
                'previous' => (float) $replay['previous_balance'],
                'current' => (float) $replay['current_balance'],
            ];
        }
    }

    $db->begin_transaction();
    try {
        $dup = $db->prepare(
            "SELECT id FROM employee_shop_deposits
             WHERE employee_id = ? AND kind = ? AND ABS(amount - ?) < 0.009
               AND created_at >= (NOW() - INTERVAL 60 SECOND)
             ORDER BY id DESC LIMIT 1"
        );
        $dup->bind_param('isd', $emp_id, $kind, $amount);
        $dup->execute();
        $hit = $dup->get_result()->fetch_assoc();
        $dup->close();
        if ($hit) {
            throw new Exception('একই পরিমাণ গত ১ মিনিটেই জমা হয়েছে। আবার কাটেনি।');
        }

        $lock = $db->prepare("SELECT id, name, status, removed_at, COALESCE(shop_deposit_balance, 0) AS shop_deposit_balance FROM employees WHERE id = ? FOR UPDATE");
        $lock->bind_param('i', $emp_id);
        $lock->execute();
        $emp = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$emp || ame_employee_is_removed($emp)) {
            throw new Exception('কর্মী খুঁজে পাওয়া যায়নি।');
        }

        $previous = round((float) $emp['shop_deposit_balance'], 2);
        if ($kind === 'deposit') {
            $current = round($previous + $amount, 2);
        } else {
            if ($amount > $previous + 0.009) {
                throw new Exception('ফেরত জমার বেশি হতে পারবে না। জমা ৳' . number_format($previous, 2));
            }
            $current = round($previous - $amount, 2);
        }

        $upd = $db->prepare('UPDATE employees SET shop_deposit_balance = ? WHERE id = ?');
        $upd->bind_param('di', $current, $emp_id);
        $upd->execute();
        $upd->close();

        if ($token === '') {
            $token = bin2hex(random_bytes(16));
        }
        $ins = $db->prepare(
            'INSERT INTO employee_shop_deposits
                (employee_id, account_id, amount, kind, deposit_date, note, previous_balance, current_balance, idempotency_token, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->bind_param(
            'iidsssddsi',
            $emp_id,
            $account_id,
            $amount,
            $kind,
            $date,
            $note,
            $previous,
            $current,
            $token,
            $user_id
        );
        $ins->execute();
        $dep_id = (int) $db->insert_id;
        $ins->close();

        $who = (string) $emp['name'];
        if ($kind === 'deposit') {
            account_credit($account_id, $amount, 'employee_shop_deposit', $dep_id, 'কর্মী জমা: ' . $who);
        } else {
            account_debit($account_id, $amount, 'employee_shop_deposit', $dep_id, 'কর্মী জমা ফেরত: ' . $who);
        }

        $db->commit();
        return [
            'ok' => true,
            'replay' => false,
            'id' => $dep_id,
            'previous' => $previous,
            'current' => $current,
            'name' => $who,
            'kind' => $kind,
        ];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

require_once __DIR__ . '/earnings_functions.php';

/**
 * কর্মী "ডিলিট" — users.php-এর Remove/Restore প্যাটার্নের মতোই আসল DELETE না
 * করে removed_at বসানো হয়, যাতে বেতন/অগ্রিম/কমিশনের পুরনো হিসাব অক্ষত থাকে।
 */
function ame_employee_is_removed(array $emp): bool
{
    return trim((string) ($emp['removed_at'] ?? '')) !== '';
}

/** তালিকা/ড্রপডাউনের কোয়েরিতে বসানোর জন্য — সরানো কর্মী বাদ দিতে */
function ame_sql_employee_present(string $alias = ''): string
{
    $col = $alias !== '' ? $alias . '.removed_at' : 'removed_at';
    return '(' . $col . ' IS NULL)';
}

function ame_remove_employee($db, int $employee_id): ?string
{
    if (!($db instanceof mysqli) || $employee_id <= 0) {
        return 'সঠিক কর্মী বাছুন। / Choose a valid employee.';
    }
    $stmt = $db->prepare('SELECT id, name, removed_at FROM employees WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'ডাটাবেজ ত্রুটি। / Database error.';
    }
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$emp) {
        return 'কর্মী পাওয়া যায়নি। / Employee not found.';
    }
    if (ame_employee_is_removed($emp)) {
        return 'এই কর্মী আগেই সরানো হয়েছে। / This employee is already removed.';
    }
    $by = (int) ($_SESSION['user_id'] ?? 0);
    $upd = $db->prepare('UPDATE employees SET removed_at = NOW(), removed_by = ?, status = ? WHERE id = ?');
    if (!$upd) {
        return 'ডাটাবেজ ত্রুটি। / Database error.';
    }
    $status = 'inactive';
    $byOrNull = $by > 0 ? $by : null;
    $upd->bind_param('isi', $byOrNull, $status, $employee_id);
    $upd->execute();
    $upd->close();
    if (function_exists('log_activity')) {
        log_activity('Employee Removed', 'HRM', (string) ($emp['name'] ?? ''));
    }
    return null;
}

function ame_restore_employee($db, int $employee_id): ?string
{
    if (!($db instanceof mysqli) || $employee_id <= 0) {
        return 'সঠিক কর্মী বাছুন। / Choose a valid employee.';
    }
    $stmt = $db->prepare('SELECT id, name, removed_at FROM employees WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'ডাটাবেজ ত্রুটি। / Database error.';
    }
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$emp) {
        return 'কর্মী পাওয়া যায়নি। / Employee not found.';
    }
    if (!ame_employee_is_removed($emp)) {
        return 'এই কর্মী সরানো হয়নি। / This employee is not removed.';
    }
    $upd = $db->prepare("UPDATE employees SET removed_at = NULL, removed_by = NULL, status = 'active' WHERE id = ?");
    if (!$upd) {
        return 'ডাটাবেজ ত্রুটি। / Database error.';
    }
    $upd->bind_param('i', $employee_id);
    $upd->execute();
    $upd->close();
    if (function_exists('log_activity')) {
        log_activity('Employee Restored', 'HRM', (string) ($emp['name'] ?? ''));
    }
    return null;
}

function ame_staff_pay_mode(array $emp): string
{
    $p = strtolower(trim((string)($emp['payment_type'] ?? '')));
    if (in_array($p, ['daily', 'monthly', 'percentage', 'work', 'mixed'], true)) {
        return $p;
    }
    if ($p === 'daily') {
        return 'daily';
    }
    if ($p === 'percentage') {
        return 'percentage';
    }
    return (($emp['salary_type'] ?? '') === 'daily') ? 'daily' : 'percentage';
}

function ame_staff_present_days(mysqli $db, int $employee_id, string $ym): int
{
    if ($employee_id <= 0) {
        return 0;
    }
    [$ym, $from, $to] = ame_month_bounds($ym);
    $has = @$db->query("SHOW TABLES LIKE 'attendance'");
    if (!$has || (int)$has->num_rows === 0) {
        return 0;
    }
    $stmt = $db->prepare("SELECT COUNT(*) c FROM attendance WHERE employee_id = ? AND att_date >= ? AND att_date < ? AND status IN ('Present','Late')");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iss', $employee_id, $from, $to);
    $stmt->execute();
    $n = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return max(0, $n);
}

function ame_staff_engineer_id(mysqli $db, int $employee_id): int
{
    if ($employee_id <= 0) {
        return 0;
    }
    $stmt = $db->prepare('SELECT id FROM engineers WHERE employee_id = ? ORDER BY id ASC LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function ame_staff_month_jobs(mysqli $db, int $employee_id, string $ym): array
{
    [$ym, $from, $to] = ame_month_bounds($ym);
    $jobs = [];
    $total = 0.0;
    $share = 0.0;
    $houses = [];

    $sql = "SELECT sc.id, sc.sale_id, sc.service_amount, sc.commission_amount, sc.service_type, sc.created_at,
                   s.invoice_number, s.guest_customer_name,
                   c.name AS customer_name, c.address AS customer_address, c.phone AS customer_phone
            FROM service_commissions sc
            LEFT JOIN sales s ON s.id = sc.sale_id
            LEFT JOIN customers c ON c.id = s.customer_id
            WHERE sc.employee_id = ?
              AND sc.created_at >= ?
              AND sc.created_at < ?
            ORDER BY sc.created_at DESC, sc.id DESC";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('iss', $employee_id, $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $house = trim((string)($row['customer_name'] ?? ''));
            if ($house === '') {
                $house = trim((string)($row['guest_customer_name'] ?? ''));
            }
            if ($house === '') {
                $house = 'অজানা গাড়ি';
            }
            $addr = trim((string)($row['customer_address'] ?? ''));
            $jobs[] = [
                'sale_id' => (int)($row['sale_id'] ?? 0),
                'invoice' => (string)($row['invoice_number'] ?? ''),
                'house' => $house,
                'address' => $addr,
                'job' => (string)($row['service_type'] ?? ''),
                'amount' => (float)$row['service_amount'],
                'share' => (float)$row['commission_amount'],
                'at' => (string)($row['created_at'] ?? ''),
            ];
            $total += (float)$row['service_amount'];
            $share += (float)$row['commission_amount'];
            if (!isset($houses[$house])) {
                $houses[$house] = 0.0;
            }
            $houses[$house] += (float)$row['service_amount'];
        }
        $stmt->close();
    }

    return [
        'jobs' => $jobs,
        'work_total' => $total,
        'share_total' => $share,
        'houses' => $houses,
        'house_names' => array_keys($houses),
    ];
}

function ame_staff_work_ledger(mysqli $db, string $ym, int $employee_id = 0, string $q = ''): array
{
    [$ym, $from, $to] = ame_month_bounds($ym);
    $rows = [];
    $has_eng_name = false;
    $col = @$db->query("SHOW COLUMNS FROM sale_services LIKE 'engineer_name'");
    if ($col && $col->fetch_assoc()) {
        $has_eng_name = true;
    }
    $name_sql = $has_eng_name
        ? "COALESCE(NULLIF(ss.engineer_name, ''), e.name, emp.name)"
        : "COALESCE(e.name, emp.name)";
    $sql = "SELECT ss.id, ss.sale_id, ss.amount, ss.service_type, ss.engineer_id,
                   $name_sql AS worker_name,
                   emp.id AS employee_id,
                   COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(s.guest_customer_name), ''), 'অজানা গাড়ি') AS house_name,
                   COALESCE(c.address, '') AS house_address,
                   COALESCE(c.phone, '') AS house_phone,
                   s.invoice_number, s.created_at
            FROM sale_services ss
            LEFT JOIN sales s ON s.id = ss.sale_id
            LEFT JOIN customers c ON c.id = s.customer_id
            LEFT JOIN engineers e ON e.id = ss.engineer_id
            LEFT JOIN employees emp ON emp.id = e.employee_id
            WHERE s.created_at >= ? AND s.created_at < ?";
    $types = 'ss';
    $params = [$from, $to];
    if ($employee_id > 0) {
        $sql .= ' AND (emp.id = ? OR e.employee_id = ? OR ss.engineer_id IN (SELECT id FROM engineers WHERE employee_id = ?))';
        $types .= 'iii';
        array_push($params, $employee_id, $employee_id, $employee_id);
    }
    if ($q !== '') {
        $search_name = $has_eng_name
            ? 'COALESCE(emp.name, e.name, ss.engineer_name)'
            : 'COALESCE(emp.name, e.name)';
        $sql .= " AND ($search_name LIKE ?
                      OR c.name LIKE ?
                      OR s.guest_customer_name LIKE ?
                      OR c.address LIKE ?
                      OR s.invoice_number LIKE ?)";
        $like = '%' . $q . '%';
        $types .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY s.created_at DESC, ss.id DESC LIMIT 800';
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }

    if (!$rows) {
        $sql2 = "SELECT sc.id, sc.sale_id, sc.service_amount AS amount, sc.service_type, sc.engineer_id,
                        emp.name AS worker_name, sc.employee_id,
                        COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(s.guest_customer_name), ''), 'অজানা গাড়ি') AS house_name,
                        COALESCE(c.address, '') AS house_address,
                        COALESCE(c.phone, '') AS house_phone,
                        s.invoice_number, sc.created_at
                 FROM service_commissions sc
                 LEFT JOIN employees emp ON emp.id = sc.employee_id
                 LEFT JOIN sales s ON s.id = sc.sale_id
                 LEFT JOIN customers c ON c.id = s.customer_id
                 WHERE sc.created_at >= ? AND sc.created_at < ?";
        $types2 = 'ss';
        $params2 = [$from, $to];
        if ($employee_id > 0) {
            $sql2 .= ' AND sc.employee_id = ?';
            $types2 .= 'i';
            $params2[] = $employee_id;
        }
        if ($q !== '') {
            $sql2 .= ' AND (emp.name LIKE ? OR c.name LIKE ? OR s.guest_customer_name LIKE ? OR c.address LIKE ? OR s.invoice_number LIKE ?)';
            $like = '%' . $q . '%';
            $types2 .= 'sssss';
            array_push($params2, $like, $like, $like, $like, $like);
        }
        $sql2 .= ' ORDER BY sc.created_at DESC, sc.id DESC LIMIT 800';
        $stmt2 = $db->prepare($sql2);
        if ($stmt2) {
            $stmt2->bind_param($types2, ...$params2);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($row = $res2->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt2->close();
        }
    }

    $by_worker = [];
    foreach ($rows as $r) {
        $name = trim((string)($r['worker_name'] ?? '')) !== '' ? (string)$r['worker_name'] : 'অজানা কর্মী';
        if (!isset($by_worker[$name])) {
            $by_worker[$name] = ['name' => $name, 'jobs' => 0, 'amount' => 0.0];
        }
        $by_worker[$name]['jobs']++;
        $by_worker[$name]['amount'] += (float)$r['amount'];
    }

    return [
        'ym' => $ym,
        'from' => $from,
        'to' => $to,
        'rows' => $rows,
        'by_worker' => array_values($by_worker),
        'total_amount' => array_sum(array_map(static fn($r) => (float)$r['amount'], $rows)),
        'total_jobs' => count($rows),
    ];
}

require_once __DIR__ . '/staff_duty.php';
