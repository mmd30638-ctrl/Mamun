<?php
/**
 * Remove a worker from the live project without deleting rows, codes, or history.
 */

if (is_file(__DIR__ . '/super_admin.php')) {
    require_once __DIR__ . '/super_admin.php';
}

if (!function_exists('ame_staff_table')) {
    function ame_staff_table(mysqli $db): ?string
    {
        foreach (['employees', 'hrm_employees', 'staff', 'workers'] as $t) {
            if (function_exists('ame_db_table_exists') && ame_db_table_exists($db, $t)) {
                return $t;
            }
        }
        return null;
    }
}

if (!function_exists('ame_staff_archive_boot')) {
    function ame_staff_archive_boot(mysqli $db): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;
        if (function_exists('ame_schema_stamp') && ame_schema_stamp('staff_archive')) {
            return;
        }
        $table = ame_staff_table($db);
        if ($table === null) {
            return;
        }
        $cols = ame_db_columns($db, $table);
        if (!ame_db_has_col($cols, 'archived_at')) {
            @$db->query("ALTER TABLE `{$table}` ADD COLUMN `archived_at` DATETIME NULL DEFAULT NULL");
        }
        if (!ame_db_has_col($cols, 'archived_by')) {
            @$db->query("ALTER TABLE `{$table}` ADD COLUMN `archived_by` INT NULL DEFAULT NULL");
        }
        if (!ame_db_has_col($cols, 'archive_note')) {
            @$db->query("ALTER TABLE `{$table}` ADD COLUMN `archive_note` VARCHAR(255) NULL DEFAULT NULL");
        }
        if (!ame_db_has_col($cols, 'purged_at')) {
            @$db->query("ALTER TABLE `{$table}` ADD COLUMN `purged_at` DATETIME NULL DEFAULT NULL");
        }
        if (!ame_db_has_col($cols, 'purged_by')) {
            @$db->query("ALTER TABLE `{$table}` ADD COLUMN `purged_by` INT NULL DEFAULT NULL");
        }
        if (function_exists('ame_schema_stamp')) {
            ame_schema_stamp('staff_archive', true);
        }
    }
}

if (!function_exists('ame_staff_code_col')) {
    function ame_staff_code_col(array $cols): ?string
    {
        foreach (['employee_code', 'emp_code', 'staff_code', 'code', 'emp_no'] as $c) {
            if (ame_db_has_col($cols, $c)) {
                return $c;
            }
        }
        return null;
    }
}

if (!function_exists('ame_staff_name_col')) {
    function ame_staff_name_col(array $cols): string
    {
        foreach (['name', 'full_name', 'employee_name', 'staff_name'] as $c) {
            if (ame_db_has_col($cols, $c)) {
                return $c;
            }
        }
        return 'name';
    }
}

if (!function_exists('ame_staff_not_purged_sql')) {
    function ame_staff_not_purged_sql(array $cols): string
    {
        if (ame_db_has_col($cols, 'purged_at')) {
            return '`purged_at` IS NULL';
        }
        return '1=1';
    }
}

if (!function_exists('ame_staff_is_archived_sql')) {
    function ame_staff_is_archived_sql(array $cols, bool $archived): string
    {
        $alive = ame_staff_not_purged_sql($cols);
        if (ame_db_has_col($cols, 'archived_at')) {
            $flag = $archived ? '`archived_at` IS NOT NULL' : '`archived_at` IS NULL';
            return $flag . ' AND ' . $alive;
        }
        return $archived ? '0=1' : $alive;
    }
}

if (!function_exists('ame_staff_is_purged')) {
    function ame_staff_is_purged(mysqli $db, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $table = ame_staff_table($db);
        if ($table === null) {
            return false;
        }
        $cols = ame_db_columns($db, $table);
        if (!ame_db_has_col($cols, 'purged_at')) {
            return false;
        }
        $res = @$db->query('SELECT `purged_at` FROM `' . $table . '` WHERE `id` = ' . (int) $id . ' LIMIT 1');
        if (!($res instanceof mysqli_result)) {
            return false;
        }
        $row = $res->fetch_assoc();
        $res->free();
        return !empty($row['purged_at']);
    }
}

if (!function_exists('ame_staff_find_by_code')) {
    function ame_staff_find_by_code(mysqli $db, string $code): int
    {
        $code = trim($code);
        if ($code === '') {
            return 0;
        }
        $table = ame_staff_table($db);
        if ($table === null) {
            return 0;
        }
        $cols = ame_db_columns($db, $table);
        $col = ame_staff_code_col($cols);
        if ($col === null) {
            return 0;
        }
        $esc = $db->real_escape_string($code);
        $res = @$db->query("SELECT `id` FROM `{$table}` WHERE `{$col}` = '{$esc}' LIMIT 1");
        if (!($res instanceof mysqli_result)) {
            return 0;
        }
        $row = $res->fetch_assoc();
        $res->free();
        return (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('ame_staff_linked_super_admin')) {
    function ame_staff_linked_super_admin(mysqli $db, int $staffId): bool
    {
        $users = ame_users_table($db);
        if ($users === null) {
            return false;
        }
        $cols = ame_db_columns($db, $users);
        if (!ame_db_has_col($cols, 'is_super_admin')) {
            return false;
        }
        $link = null;
        foreach (['employee_id', 'emp_id', 'staff_id', 'worker_id'] as $c) {
            if (ame_db_has_col($cols, $c)) {
                $link = $c;
                break;
            }
        }
        if ($link === null) {
            return false;
        }
        $stmt = $db->prepare("SELECT `id` FROM `{$users}` WHERE `{$link}` = ? AND `is_super_admin` = 1 LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $staffId);
        $stmt->execute();
        $res = $stmt->get_result();
        $hit = $res && $res->fetch_assoc();
        $stmt->close();
        return (bool) $hit;
    }
}

if (!function_exists('ame_staff_deactivate_login')) {
    function ame_staff_deactivate_login(mysqli $db, int $staffId, bool $enable): void
    {
        $users = ame_users_table($db);
        if ($users === null) {
            return;
        }
        $cols = ame_db_columns($db, $users);
        $link = null;
        foreach (['employee_id', 'emp_id', 'staff_id', 'worker_id'] as $c) {
            if (ame_db_has_col($cols, $c)) {
                $link = $c;
                break;
            }
        }
        if ($link === null) {
            return;
        }
        $flag = $enable ? 1 : 0;
        if (ame_db_has_col($cols, 'is_active')) {
            @$db->query("UPDATE `{$users}` SET `is_active` = {$flag} WHERE `{$link}` = " . (int) $staffId . " AND IFNULL(`is_super_admin`,0) = 0");
        }
        if (ame_db_has_col($cols, 'status')) {
            $type = strtolower((string) ($cols['status']['Type'] ?? ''));
            if (str_contains($type, 'int') || str_contains($type, 'tiny')) {
                @$db->query("UPDATE `{$users}` SET `status` = {$flag} WHERE `{$link}` = " . (int) $staffId . " AND IFNULL(`is_super_admin`,0) = 0");
            } else {
                $val = $enable ? 'active' : 'disabled';
                $esc = $db->real_escape_string($val);
                @$db->query("UPDATE `{$users}` SET `status` = '{$esc}' WHERE `{$link}` = " . (int) $staffId . " AND IFNULL(`is_super_admin`,0) = 0");
            }
        }
    }
}

if (!function_exists('ame_staff_archive')) {
    function ame_staff_archive(mysqli $db, int $id, string $note = ''): string
    {
        if ($id <= 0) {
            return 'কর্মী সিলেক্ট করা নেই।';
        }
        if (ame_staff_linked_super_admin($db, $id)) {
            return 'সুপার অ্যাডমিন কর্মীকে প্রজেক্ট থেকে সরানো যাবে না।';
        }
        if (ame_staff_is_purged($db, $id)) {
            return 'এই কর্মী চিরতরে প্রজেক্ট তালিকা থেকে সরানো। কোড খাতায় আছে।';
        }
        ame_staff_archive_boot($db);
        $table = ame_staff_table($db);
        if ($table === null) {
            return 'কর্মী টেবিল পাওয়া যায়নি।';
        }
        $cols = ame_db_columns($db, $table);
        $sets = [];
        if (ame_db_has_col($cols, 'archived_at')) {
            $sets[] = '`archived_at` = NOW()';
        }
        $by = ame_current_user_id();
        if (ame_db_has_col($cols, 'archived_by')) {
            $sets[] = '`archived_by` = ' . (int) $by;
        }
        if (ame_db_has_col($cols, 'archive_note')) {
            $esc = $db->real_escape_string(mb_substr($note, 0, 250));
            $sets[] = "`archive_note` = '{$esc}'";
        }
        if (ame_db_has_col($cols, 'is_active')) {
            $sets[] = '`is_active` = 0';
        }
        if (ame_db_has_col($cols, 'active')) {
            $sets[] = '`active` = 0';
        }
        if (ame_db_has_col($cols, 'status')) {
            $type = strtolower((string) ($cols['status']['Type'] ?? ''));
            if (str_contains($type, 'enum') && str_contains($type, 'inactive')) {
                $sets[] = "`status` = 'inactive'";
            } elseif (str_contains($type, 'int') || str_contains($type, 'tiny')) {
                $sets[] = '`status` = 0';
            } else {
                $sets[] = "`status` = 'archived'";
            }
        }
        if (!$sets) {
            return 'আর্কাইভ কলাম যোগ হয়নি।';
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE `id` = ' . (int) $id . ' LIMIT 1';
        if (!@$db->query($sql)) {
            return 'আর্কাইভ যায়নি। কোড বা খাতা মুছা হয়নি।';
        }
        ame_staff_deactivate_login($db, $id, false);
        return '';
    }
}

if (!function_exists('ame_staff_restore')) {
    function ame_staff_restore(mysqli $db, int $id): string
    {
        if (!ame_is_super_admin()) {
            return 'ফেরত আনতে সুপার অ্যাডমিন লাগবে।';
        }
        if ($id <= 0) {
            return 'কর্মী সিলেক্ট করা নেই।';
        }
        if (ame_staff_is_purged($db, $id)) {
            return 'চিরতরে সরানো কর্মী ফেরত আনা যায় না। কোড ও খাতা মুছে যায়নি।';
        }
        ame_staff_archive_boot($db);
        $table = ame_staff_table($db);
        if ($table === null) {
            return 'কর্মী টেবিল পাওয়া যায়নি।';
        }
        $cols = ame_db_columns($db, $table);
        $sets = [];
        if (ame_db_has_col($cols, 'archived_at')) {
            $sets[] = '`archived_at` = NULL';
        }
        if (ame_db_has_col($cols, 'archived_by')) {
            $sets[] = '`archived_by` = NULL';
        }
        if (ame_db_has_col($cols, 'is_active')) {
            $sets[] = '`is_active` = 1';
        }
        if (ame_db_has_col($cols, 'active')) {
            $sets[] = '`active` = 1';
        }
        if (ame_db_has_col($cols, 'status')) {
            $type = strtolower((string) ($cols['status']['Type'] ?? ''));
            if (str_contains($type, 'enum') && str_contains($type, 'active')) {
                $sets[] = "`status` = 'active'";
            } elseif (str_contains($type, 'int') || str_contains($type, 'tiny')) {
                $sets[] = '`status` = 1';
            } else {
                $sets[] = "`status` = 'active'";
            }
        }
        if (!$sets) {
            return 'রিস্টোর কলাম নেই।';
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE `id` = ' . (int) $id . ' LIMIT 1';
        if (!@$db->query($sql)) {
            return 'ফেরত আনা যায়নি।';
        }
        ame_staff_deactivate_login($db, $id, true);
        return '';
    }
}

if (!function_exists('ame_staff_archive_rows')) {
    function ame_staff_archive_rows(mysqli $db, bool $archived = true): array
    {
        ame_staff_archive_boot($db);
        $table = ame_staff_table($db);
        if ($table === null) {
            return [];
        }
        $cols = ame_db_columns($db, $table);
        $name = ame_staff_name_col($cols);
        $code = ame_staff_code_col($cols);
        $phone = ame_db_has_col($cols, 'phone') ? 'phone' : (ame_db_has_col($cols, 'mobile') ? 'mobile' : null);
        $sel = ['`id`', "`{$name}` AS name"];
        if ($code) {
            $sel[] = "`{$code}` AS code";
        }
        if ($phone) {
            $sel[] = "`{$phone}` AS phone";
        }
        if (ame_db_has_col($cols, 'archived_at')) {
            $sel[] = '`archived_at`';
        }
        if (ame_db_has_col($cols, 'archive_note')) {
            $sel[] = '`archive_note`';
        }
        $where = ame_staff_is_archived_sql($cols, $archived);
        $sql = 'SELECT ' . implode(', ', $sel) . " FROM `{$table}` WHERE {$where} ORDER BY `id` DESC";
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

if (!function_exists('ame_staff_archived_ids')) {
    function ame_staff_archived_ids(mysqli $db): array
    {
        $ids = [];
        foreach (ame_staff_archive_rows($db, true) as $row) {
            $ids[] = (int) ($row['id'] ?? 0);
        }
        return array_values(array_filter($ids));
    }
}

if (!function_exists('ame_staff_purged_ids')) {
    function ame_staff_purged_ids(mysqli $db): array
    {
        ame_staff_archive_boot($db);
        $table = ame_staff_table($db);
        if ($table === null) {
            return [];
        }
        $cols = ame_db_columns($db, $table);
        if (!ame_db_has_col($cols, 'purged_at')) {
            return [];
        }
        $ids = [];
        $res = @$db->query("SELECT `id` FROM `{$table}` WHERE `purged_at` IS NOT NULL");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
            $res->free();
        }
        return array_values(array_filter($ids));
    }
}

if (!function_exists('ame_staff_purged_rows')) {
    function ame_staff_purged_rows(mysqli $db): array
    {
        ame_staff_archive_boot($db);
        $table = ame_staff_table($db);
        if ($table === null) {
            return [];
        }
        $cols = ame_db_columns($db, $table);
        if (!ame_db_has_col($cols, 'purged_at')) {
            return [];
        }
        $name = ame_staff_name_col($cols);
        $code = ame_staff_code_col($cols);
        $sel = ['`id`', "`{$name}` AS name"];
        if ($code) {
            $sel[] = "`{$code}` AS code";
        }
        $sql = 'SELECT ' . implode(', ', $sel) . " FROM `{$table}` WHERE `purged_at` IS NOT NULL ORDER BY `id` DESC";
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

if (!function_exists('ame_staff_codes_of')) {
    function ame_staff_codes_of(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($id > 0 && $code !== '') {
                $out[(string) $id] = $code;
            }
        }
        return $out;
    }
}

if (!function_exists('ame_staff_purge')) {
    function ame_staff_purge(mysqli $db, int $id, string $code = ''): string
    {
        if (!ame_is_super_admin()) {
            return 'চিরতরে সরাতে সুপার অ্যাডমিন লাগবে।';
        }
        if ($id <= 0 && $code !== '') {
            $id = ame_staff_find_by_code($db, $code);
        }
        if ($id <= 0) {
            return 'কর্মী সিলেক্ট করা নেই।';
        }
        if (ame_staff_linked_super_admin($db, $id)) {
            return 'সুপার অ্যাডমিন কর্মীকে চিরতরে সরানো যাবে না।';
        }
        ame_staff_archive_boot($db);
        $table = ame_staff_table($db);
        if ($table === null) {
            return 'কর্মী টেবিল পাওয়া যায়নি।';
        }
        if (ame_staff_is_purged($db, $id)) {
            return '';
        }
        $cols = ame_db_columns($db, $table);
        $sets = [];
        if (ame_db_has_col($cols, 'purged_at')) {
            $sets[] = '`purged_at` = NOW()';
        }
        $by = ame_current_user_id();
        if (ame_db_has_col($cols, 'purged_by')) {
            $sets[] = '`purged_by` = ' . (int) $by;
        }
        if (ame_db_has_col($cols, 'archived_at')) {
            $sets[] = '`archived_at` = IFNULL(`archived_at`, NOW())';
        }
        if (ame_db_has_col($cols, 'is_active')) {
            $sets[] = '`is_active` = 0';
        }
        if (ame_db_has_col($cols, 'active')) {
            $sets[] = '`active` = 0';
        }
        if (ame_db_has_col($cols, 'status')) {
            $type = strtolower((string) ($cols['status']['Type'] ?? ''));
            if (str_contains($type, 'enum') && str_contains($type, 'inactive')) {
                $sets[] = "`status` = 'inactive'";
            } elseif (str_contains($type, 'int') || str_contains($type, 'tiny')) {
                $sets[] = '`status` = 0';
            } else {
                $sets[] = "`status` = 'archived'";
            }
        }
        if (!$sets) {
            return 'চিরতরে সরানোর কলাম যোগ হয়নি। কোনো কোড মুছা হয়নি।';
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE `id` = ' . (int) $id . ' LIMIT 1';
        if (!@$db->query($sql)) {
            return 'চিরতরে সরানো যায়নি। কোড বা খাতা মুছা হয়নি।';
        }
        ame_staff_deactivate_login($db, $id, false);
        return '';
    }
}

if (!function_exists('ame_staff_can_archive')) {
    function ame_staff_can_archive(?mysqli $db): bool
    {
        if (ame_is_admin_like()) {
            return true;
        }
        return $db instanceof mysqli && function_exists('has_permission') && has_permission($db, 'm-hrm');
    }
}
