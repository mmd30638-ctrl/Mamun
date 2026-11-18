<?php
/**
 * Super Admin: full project duty. Regular admin cannot remove them.
 * Role string stays "admin" so existing pages keep working.
 */

if (!function_exists('ame_db_table_exists')) {
    function ame_db_table_exists(mysqli $db, string $table): bool
    {
        static $mem = [];
        $key = $table;
        if (array_key_exists($key, $mem)) {
            return $mem[$key];
        }
        $esc = $db->real_escape_string($table);
        $res = @$db->query("SHOW TABLES LIKE '{$esc}'");
        $ok = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        return $mem[$key] = $ok;
    }
}

if (!function_exists('ame_db_columns')) {
    function ame_db_columns(mysqli $db, string $table): array
    {
        static $mem = [];
        if (isset($mem[$table])) {
            return $mem[$table];
        }
        $cols = [];
        $esc = $db->real_escape_string($table);
        $res = @$db->query("SHOW COLUMNS FROM `{$esc}`");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $cols[strtolower((string) ($row['Field'] ?? ''))] = $row;
            }
            $res->free();
        }
        return $mem[$table] = $cols;
    }
}

if (!function_exists('ame_db_has_col')) {
    function ame_db_has_col(array $cols, string $name): bool
    {
        return isset($cols[strtolower($name)]);
    }
}

if (!function_exists('ame_users_table')) {
    function ame_users_table(mysqli $db): ?string
    {
        foreach (['users', 'admin_users', 'shop_users'] as $t) {
            if (ame_db_table_exists($db, $t)) {
                return $t;
            }
        }
        return null;
    }
}

if (!function_exists('ame_is_super_admin')) {
    function ame_is_super_admin(): bool
    {
        if (!empty($_SESSION['is_super_admin'])) {
            return true;
        }
        $role = strtolower((string) ($_SESSION['role'] ?? ''));
        return $role === 'super_admin' || $role === 'superadmin';
    }
}

if (!function_exists('ame_is_admin_like')) {
    function ame_is_admin_like(): bool
    {
        if (ame_is_super_admin()) {
            return true;
        }
        $role = strtolower((string) ($_SESSION['role'] ?? ''));
        return $role === 'admin';
    }
}

if (!function_exists('ame_current_user_id')) {
    function ame_current_user_id(): int
    {
        foreach (['user_id', 'id', 'uid', 'admin_id'] as $k) {
            if (!empty($_SESSION[$k]) && ctype_digit((string) $_SESSION[$k])) {
                return (int) $_SESSION[$k];
            }
        }
        return 0;
    }
}

if (!function_exists('ame_super_admin_ensure_column')) {
    function ame_super_admin_ensure_column(mysqli $db): void
    {
        $table = ame_users_table($db);
        if ($table === null) {
            return;
        }
        $cols = ame_db_columns($db, $table);
        if (!ame_db_has_col($cols, 'is_super_admin')) {
            @$db->query("ALTER TABLE `{$table}` ADD COLUMN `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0");
        }
    }
}

if (!function_exists('ame_super_admin_boot')) {
    function ame_super_admin_boot(?mysqli $db): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        if (!$db instanceof mysqli) {
            return;
        }
        $stamped = function_exists('ame_schema_stamp') && ame_schema_stamp('super_admin_col');
        if ($stamped && array_key_exists('is_super_admin', $_SESSION)) {
            return;
        }
        try {
            if (!$stamped) {
                ame_super_admin_ensure_column($db);
                if (function_exists('ame_schema_stamp')) {
                    ame_schema_stamp('super_admin_col', true);
                }
            }
            $table = ame_users_table($db);
            if ($table === null) {
                return;
            }
            $cols = ame_db_columns($db, $table);
            if (!ame_db_has_col($cols, 'is_super_admin')) {
                return;
            }
            $count = 0;
            $res = @$db->query("SELECT COUNT(*) AS c FROM `{$table}` WHERE `is_super_admin` = 1");
            if ($res instanceof mysqli_result) {
                $row = $res->fetch_assoc();
                $count = (int) ($row['c'] ?? 0);
                $res->free();
            }
            if ($count < 1) {
                $where = '1=1';
                if (ame_db_has_col($cols, 'role')) {
                    $where = "LOWER(`role`) IN ('admin','super_admin','superadmin')";
                }
                $pickId = 0;
                $pick = @$db->query("SELECT `id` FROM `{$table}` WHERE {$where} ORDER BY `id` ASC LIMIT 1");
                if ($pick instanceof mysqli_result) {
                    $prow = $pick->fetch_assoc();
                    $pickId = (int) ($prow['id'] ?? 0);
                    $pick->free();
                }
                if ($pickId > 0) {
                    @$db->query("UPDATE `{$table}` SET `is_super_admin` = 1 WHERE `id` = {$pickId} LIMIT 1");
                }
            }
            $uid = ame_current_user_id();
            if ($uid > 0) {
                $stmt = $db->prepare("SELECT `is_super_admin` FROM `{$table}` WHERE `id` = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('i', $uid);
                    $stmt->execute();
                    $got = $stmt->get_result();
                    $row = $got ? $got->fetch_assoc() : null;
                    $stmt->close();
                    $_SESSION['is_super_admin'] = !empty($row['is_super_admin']) ? 1 : 0;
                }
            }
        } catch (Throwable $e) {
            error_log('super admin boot: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ame_super_admin_require')) {
    function ame_super_admin_require(): void
    {
        if (!ame_is_super_admin()) {
            header('Location: /index.php');
            exit;
        }
    }
}

if (!function_exists('ame_super_admin_list')) {
    function ame_super_admin_list(mysqli $db): array
    {
        $table = ame_users_table($db);
        if ($table === null) {
            return [];
        }
        $cols = ame_db_columns($db, $table);
        $nameCol = ame_db_has_col($cols, 'name') ? 'name' : (ame_db_has_col($cols, 'full_name') ? 'full_name' : 'username');
        $userCol = ame_db_has_col($cols, 'username') ? 'username' : $nameCol;
        $roleSel = ame_db_has_col($cols, 'role') ? ', `role`' : '';
        $sql = "SELECT `id`, `{$userCol}` AS username, `{$nameCol}` AS display_name, `is_super_admin` {$roleSel} FROM `{$table}` ORDER BY `is_super_admin` DESC, `id` ASC";
        $rows = [];
        $res = @$db->query($sql);
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        return $rows;
    }
}

if (!function_exists('ame_super_admin_set')) {
    function ame_super_admin_set(mysqli $db, int $userId, bool $on): string
    {
        if (!ame_is_super_admin()) {
            return 'শুধু সুপার অ্যাডমিন এই কাজ করতে পারেন।';
        }
        $table = ame_users_table($db);
        if ($table === null || $userId <= 0) {
            return 'ইউজার পাওয়া যায়নি।';
        }
        if (!$on) {
            $res = @$db->query("SELECT COUNT(*) AS c FROM `{$table}` WHERE `is_super_admin` = 1");
            $count = 0;
            if ($res instanceof mysqli_result) {
                $row = $res->fetch_assoc();
                $count = (int) ($row['c'] ?? 0);
                $res->free();
            }
            if ($count <= 1) {
                return 'শেষ সুপার অ্যাডমিনকে সরানো যাবে না।';
            }
            if ($userId === ame_current_user_id() && $count <= 1) {
                return 'নিজের সুপার অ্যাডমিন দায়িত্ব একা থাকলে ছাড়া যায় না।';
            }
        }
        $flag = $on ? 1 : 0;
        $stmt = $db->prepare("UPDATE `{$table}` SET `is_super_admin` = ? WHERE `id` = ?");
        if (!$stmt) {
            return 'সংরক্ষণ যায়নি।';
        }
        $stmt->bind_param('ii', $flag, $userId);
        $stmt->execute();
        $stmt->close();
        return '';
    }
}

if (!function_exists('ame_super_admin_duties')) {
    function ame_super_admin_duties(): array
    {
        return [
            'সব মডিউল খোলা — বিক্রয়, ক্রয়, স্টক, কর্মী, হিসাব, সেটিংস',
            'ইউজার তৈরি, পারমিশন ও রোল',
            'কর্মীকে প্রজেক্ট থেকে সরানো (আর্কাইভ) এবং আবার ফেরত আনা',
            'আর্কাইভ থেকে চিরতরে প্রজেক্ট তালিকা থেকে সরানো — কোড, বেতন ও হাজিরা মুছে যায় না',
            'কাস্টমার ও সাপ্লায়ার চিরতরে মুছা — নাম, পাসওয়ার্ড ও নিরাপত্তা কোড; সাধারণ অ্যাডমিন পারেন না',
            'ইউজার ও কর্মী প্রোফাইল এডিট — নাম, ছবি, ফোন, রোল, পাসওয়ার্ড',
            'ভুল সুপার অ্যাডমিন দায়িত্ব প্রোফাইল থেকে সরানো; শেষ সুপার অ্যাডমিন সরানো যায় না',
            'সুপার অ্যাডমিন অ্যাকাউন্ট অন্য অ্যাডমিন মুছতে বা সরাতে পারে না',
            'কাস্টমার ও সাপ্লায়ার খাতা/স্টেটমেন্ট লক — মালিকের অনুমতি ছাড়া আপডেট বা রিবিল্ড হিসাব বদলায় না',
            'ব্যাকআপ, ইঞ্জিন রুম, অডিট ও প্রজেক্ট রক্ষা',
            'দোকানের তথ্য, ইনভয়েস টেমপ্লেট, এসএমএস সেটআপ',
            'কোনো সোর্স কোড বা কর্মী কোড মুছে যায় না',
        ];
    }
}

if (!function_exists('ame_super_admin_user_ids')) {
    function ame_super_admin_user_ids(mysqli $db): array
    {
        $ids = [];
        foreach (ame_super_admin_list($db) as $row) {
            if (!empty($row['is_super_admin'])) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        }
        return array_values(array_filter($ids));
    }
}

if (!function_exists('ame_super_admin_duty_links')) {
    function ame_super_admin_duty_links(): array
    {
        return [
            ['কর্মী আর্কাইভ ও চিরতরে মুছুন', '/modules/hrm/employee_archive.php'],
            ['কাস্টমার তালিকা', '/modules/customers/index.php'],
            ['সাপ্লায়ার তালিকা', '/modules/suppliers/list.php'],
            ['ইউজার তালিকা ও প্রোফাইল', '/modules/settings/users.php'],
            ['রোল কন্ট্রোল — সবাই, সুপার অ্যাডমিনসহ', '/modules/settings/role_control.php'],
            ['নতুন ইউজার', '/modules/settings/user_add.php'],
            ['পারমিশন ও রোল', '/modules/settings/role_permissions.php'],
            ['ইঞ্জিন রুম / সহজ আপডেট', '/tools/index.php'],
            ['ব্যাকআপ', '/modules/settings/backup.php'],
            ['অডিট খাতা', '/modules/reports/audit_trail.php'],
            ['প্রজেক্ট রক্ষা', '/modules/settings/project_care.php'],
            ['দোকানের তথ্য', '/modules/settings/dashboard_settings.php'],
            ['ইনভয়েস টেমপ্লেট', '/modules/settings/invoice_template.php'],
            ['এসএমএস সেটআপ', '/modules/settings/sms_config.php'],
            ['ওয়ার্কশপ খাতা', '/modules/workshop/index.php'],
        ];
    }
}
