<?php
// ইউজার প্রোফাইল ও ডাইনামিক রোল হেল্পার

function ensure_user_schema($db) {
    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM users");
    while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; }

    $new_cols = [
        'email'      => "ALTER TABLE users ADD COLUMN email VARCHAR(120) DEFAULT NULL",
        'photo'      => "ALTER TABLE users ADD COLUMN photo VARCHAR(255) DEFAULT NULL",
        'phone'      => "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL",
        'whatsapp'   => "ALTER TABLE users ADD COLUMN whatsapp VARCHAR(20) DEFAULT NULL",
        'must_change_password' => "ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0",
        'address'    => "ALTER TABLE users ADD COLUMN address VARCHAR(255) DEFAULT NULL",
        'document'   => "ALTER TABLE users ADD COLUMN document VARCHAR(255) DEFAULT NULL",
        'status'     => "ALTER TABLE users ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1",
        'created_at' => "ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
        'removed_at' => "ALTER TABLE users ADD COLUMN removed_at DATETIME DEFAULT NULL",
        'removed_by' => "ALTER TABLE users ADD COLUMN removed_by INT DEFAULT NULL",
        'removed_username' => "ALTER TABLE users ADD COLUMN removed_username VARCHAR(120) DEFAULT NULL",
        'purged_at' => "ALTER TABLE users ADD COLUMN purged_at DATETIME DEFAULT NULL",
        'full_name' => "ALTER TABLE users ADD COLUMN full_name VARCHAR(160) DEFAULT NULL",
    ];
    foreach ($new_cols as $col => $sql) {
        if (!in_array($col, $cols)) { $db->query($sql); }
    }

    $db->query("CREATE TABLE IF NOT EXISTS user_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_key VARCHAR(50) NOT NULL UNIQUE,
        role_label VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    /* MariaDB ১০.১১+ ডিফল্ট utf8mb4_uca1400_ai_ci — general_ci-তে CONVERT
       অনেক হোস্টে ব্যর্থ/মিক্স করে। unicode_ci দুই পাশেই চেষ্টা; ফেল করলেও
       রোল মিল PHP-তে হয়, JOIN-এর উপর নির্ভর নেই। */
    @$db->query('ALTER TABLE user_roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    @$db->query("ALTER TABLE users MODIFY `role` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cashier'");

    $roles = [
        'admin' => 'Admin (সম্পূর্ণ এক্সেস)',
        'manager' => 'Manager',
        'cashier' => 'Cashier',
        'employee' => 'Employee',
        'family' => 'Family Member',
        'viewer' => 'View Only',
    ];
    foreach ($roles as $key => $label) {
        $st = $db->prepare('INSERT IGNORE INTO user_roles (role_key, role_label) VALUES (?,?)');
        if ($st) {
            $st->bind_param('ss', $key, $label);
            $st->execute();
            $st->close();
        }
    }

    @$db->query("CREATE TABLE IF NOT EXISTS user_name_memory (
        user_id INT NOT NULL PRIMARY KEY,
        display_name VARCHAR(160) NOT NULL DEFAULT '',
        deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * user_roles → role_key => role_label। JOIN/কলেশন ছাড়া, যাতে
 * utf8mb4_general_ci ও utf8mb4_uca1400_ai_ci মিক্স না হয়।
 *
 * @return array<string,string>
 */
function ame_role_label_map($db): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }
    $map = [];
    if (!($db instanceof mysqli)) {
        return $map;
    }
    $res = @$db->query('SELECT role_key, role_label FROM user_roles');
    if (!$res) {
        return $map;
    }
    while ($row = $res->fetch_assoc()) {
        $key = (string)($row['role_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $label = (string)($row['role_label'] ?? $key);
        $map[$key] = $label;
        $map[strtolower($key)] = $label;
    }
    return $map;
}

function ame_role_label_for($db, $role): string
{
    $role = trim((string)$role);
    if ($role === '') {
        return '';
    }
    $map = ame_role_label_map($db);
    return $map[$role] ?? $map[strtolower($role)] ?? $role;
}

function ame_attach_role_label($db, ?array $row): ?array
{
    if (!is_array($row)) {
        return $row;
    }
    $row['role_label'] = ame_role_label_for($db, $row['role'] ?? '');
    return $row;
}

function ame_attach_role_labels($db, array $rows): array
{
    $map = ame_role_label_map($db);
    foreach ($rows as &$row) {
        if (!is_array($row)) {
            continue;
        }
        $role = (string)($row['role'] ?? '');
        $row['role_label'] = $map[$role] ?? $map[strtolower($role)] ?? $role;
    }
    unset($row);
    return $rows;
}

function ame_user_row_active(array $user): bool
{
    $status = $user['status'] ?? 1;
    if (is_numeric($status)) {
        return (int)$status === 1;
    }
    $status = strtolower(trim((string)$status));
    return $status === '' || $status === 'active' || $status === '1' || $status === 'on';
}

function ame_count_active_admins($db, int $except_id = 0): int
{
    if (!($db instanceof mysqli)) {
        return 0;
    }
    $n = 0;
    $res = @$db->query('SELECT id, role, status, removed_at FROM users');
    if (!$res) {
        $res = @$db->query('SELECT id, role, status FROM users');
    }
    if (!$res) {
        return 0;
    }
    while ($row = $res->fetch_assoc()) {
        if ((int)$row['id'] === $except_id) {
            continue;
        }
        if (strtolower((string)($row['role'] ?? '')) !== 'admin') {
            continue;
        }
        if (function_exists('ame_user_is_removed') && ame_user_is_removed($row)) {
            continue;
        }
        if (ame_user_row_active($row)) {
            $n++;
        }
    }
    return $n;
}

/** @return string|null error message, or null on success */
function ame_set_user_active($db, int $user_id, bool $active): ?string
{
    if (!($db instanceof mysqli) || $user_id <= 0) {
        return 'সঠিক ইউজার নির্বাচন করুন।';
    }
    $stmt = $db->prepare('SELECT id, username, role, status, removed_at FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'ডাটাবেজ ত্রুটি। / Database error.';
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        return 'ইউজার খুঁজে পাওয়া যায়নি। / User not found.';
    }
    if (ame_user_is_removed($user)) {
        return 'সরানো ব্যবহারকারীকে এক্টিভ করা যাবে না — আগে ফেরত আনুন। / Restore the user before setting active.';
    }
    if (ame_user_row_active($user) === $active) {
        return null;
    }
    $me = (int)($_SESSION['user_id'] ?? 0);
    if (!$active && $me === $user_id) {
        return 'নিজের একাউন্ট ইনঅ্যাকটিভ করা যাবে না। / You cannot inactivate yourself.';
    }
    if (!$active && strtolower((string)($user['role'] ?? '')) === 'admin' && ame_count_active_admins($db, $user_id) < 1) {
        return 'শেষ অ্যাডমিনকে ইনঅ্যাকটিভ করা যাবে না। / The last admin cannot be inactivated.';
    }
    $flag = $active ? 1 : 0;
    $up = $db->prepare('UPDATE users SET status = ? WHERE id = ? LIMIT 1');
    if (!$up) {
        return 'স্ট্যাটাস আপডেট হয়নি।';
    }
    $up->bind_param('ii', $flag, $user_id);
    $up->execute();
    $up->close();
    if (function_exists('ame_bump_auth_version') && !$active) {
        ame_bump_auth_version($db, $user_id);
    }
    $label = $active ? 'এক্টিভ' : 'ইনঅ্যাকটিভ';
    if (function_exists('log_activity')) {
        log_activity('User Status', 'Settings', ($user['username'] ?? '') . ' → ' . $label);
    }
    return null;
}

function ame_user_is_removed(array $user): bool
{
    return trim((string) ($user['removed_at'] ?? '')) !== ''
        || trim((string) ($user['purged_at'] ?? '')) !== '';
}

function ame_user_is_purged(array $user): bool
{
    return trim((string) ($user['purged_at'] ?? '')) !== '';
}

/** Roster / pickers: hide parked logins. History rows keep the numeric id. */
function ame_sql_user_present(string $alias = ''): string
{
    $col = $alias !== '' ? $alias . '.removed_at' : 'removed_at';
    return '(' . $col . ' IS NULL OR ' . $col . " = '')";
}

function ame_user_display_name(array $user): string
{
    $kept = trim((string) ($user['removed_username'] ?? ''));
    if ($kept !== '') {
        return $kept;
    }
    $full = trim((string) ($user['full_name'] ?? $user['name'] ?? $user['emp_name'] ?? ''));
    if ($full !== '') {
        return $full;
    }
    return trim((string) ($user['username'] ?? ''));
}

/** @return string|null error, or null on success */
function ame_remove_user($db, int $user_id): ?string
{
    if (!($db instanceof mysqli) || $user_id <= 0) {
        return 'সঠিক ব্যবহারকারী বাছুন। / Choose a valid user.';
    }
    $stmt = $db->prepare('SELECT id, username, role, status, removed_at, removed_username FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'ডাটাবেজ ত্রুটি। / Database error.';
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        return 'ব্যবহারকারী পাওয়া যায়নি। / User not found.';
    }
    if (ame_user_is_removed($user)) {
        return 'এই ব্যবহারকারী আগেই সরানো হয়েছে। / This user is already removed.';
    }
    $me = (int) ($_SESSION['user_id'] ?? 0);
    if ($me === $user_id) {
        return 'নিজেকে সরানো যাবে না। / You cannot remove yourself.';
    }
    if (is_file(__DIR__ . '/super_admin.php')) {
        require_once __DIR__ . '/super_admin.php';
    }
    if (function_exists('ame_super_admin_user_ids')) {
        $sa = ame_super_admin_user_ids($db);
        if (in_array($user_id, $sa, true)) {
            return 'সুপার অ্যাডমিনকে আগে প্রোফাইল থেকে দায়িত্ব সরিয়ে তারপর সরান। / Revoke super admin on the profile first.';
        }
    }
    if (strtolower((string) ($user['role'] ?? '')) === 'admin' && ame_count_active_admins($db, $user_id) < 1) {
        return 'শেষ অ্যাডমিনকে সরানো যাবে না। / The last admin cannot be removed.';
    }

    $original = trim((string) ($user['username'] ?? ''));
    $parked = 'removed.' . $user_id;
    $chk = $db->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
    if ($chk) {
        $chk->bind_param('si', $parked, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $parked = 'removed.' . $user_id . '.' . time();
        }
        $chk->close();
    }

    $now = date('Y-m-d H:i:s');
    $up = $db->prepare('UPDATE users SET status = 0, removed_at = ?, removed_by = ?, removed_username = ?, username = ? WHERE id = ? LIMIT 1');
    if (!$up) {
        return 'সরানো যায়নি। / Could not remove.';
    }
    $up->bind_param('sissi', $now, $me, $original, $parked, $user_id);
    if (!$up->execute()) {
        $up->close();
        return 'সরানো যায়নি — ইউজারনেম ধাক্কা খেয়েছে। / Could not remove — username clash.';
    }
    $up->close();
    if (function_exists('ame_bump_auth_version')) {
        ame_bump_auth_version($db, $user_id);
    }
    if (function_exists('log_activity')) {
        log_activity('User Removed', 'Settings', $original . ' (#' . $user_id . ')');
    }
    return null;
}

/** @return string|null error, or null on success */
function ame_restore_user($db, int $user_id): ?string
{
    if (!($db instanceof mysqli) || $user_id <= 0) {
        return 'সঠিক ব্যবহারকারী বাছুন। / Choose a valid user.';
    }
    $stmt = $db->prepare('SELECT id, username, removed_at, removed_username FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'ডাটাবেজ ত্রুটি। / Database error.';
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        return 'ব্যবহারকারী পাওয়া যায়নি। / User not found.';
    }
    if (!ame_user_is_removed($user)) {
        return 'এই ব্যবহারকারী সরানো হয়নি। / This user is not removed.';
    }
    if (function_exists('ame_user_is_purged') && ame_user_is_purged($user)) {
        return 'চিরতরে ডিলিট ইউজার ফেরত আনা যায় না। / A permanently deleted user cannot be restored.';
    }
    $want = trim((string) ($user['removed_username'] ?? ''));
    if ($want === '') {
        $want = trim((string) ($user['username'] ?? ''));
    }
    if ($want === '' || str_starts_with($want, 'removed.')) {
        $want = 'user' . $user_id;
    }
    $chk = $db->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
    if ($chk) {
        $chk->bind_param('si', $want, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            return 'পুরনো ইউজারনেম এখন অন্যজন ব্যবহার করছে। / The old username is taken.';
        }
        $chk->close();
    }
    $up = $db->prepare('UPDATE users SET status = 1, username = ?, removed_at = NULL, removed_by = NULL, removed_username = NULL WHERE id = ? LIMIT 1');
    if (!$up) {
        return 'ফেরত আনা যায়নি। / Could not restore.';
    }
    $up->bind_param('si', $want, $user_id);
    $up->execute();
    $up->close();
    if (function_exists('log_activity')) {
        log_activity('User Restored', 'Settings', $want . ' (#' . $user_id . ')');
    }
    return null;
}

function ame_next_user_id($db): int
{
    if (!($db instanceof mysqli)) {
        return 1;
    }
    $res = @$db->query('SELECT COALESCE(MAX(id), 0) + 1 AS n FROM users');
    if (!$res) {
        return 1;
    }
    $n = (int) ($res->fetch_assoc()['n'] ?? 1);
    return $n > 0 ? $n : 1;
}

function ame_user_id_free($db, int $want, int $except_id = 0): bool
{
    if (!($db instanceof mysqli) || $want <= 0) {
        return false;
    }
    $st = $db->prepare('SELECT id FROM users WHERE id = ? AND id <> ? LIMIT 1');
    if (!$st) {
        return false;
    }
    $st->bind_param('ii', $want, $except_id);
    $st->execute();
    $taken = $st->get_result()->num_rows > 0;
    $st->close();
    return !$taken;
}

/** @return list<array{0:string,1:string}> */
function ame_user_id_ref_columns(mysqli $db): array
{
    $schema = '';
    $r = @$db->query('SELECT DATABASE()');
    if ($r) {
        $schema = (string) ($r->fetch_row()[0] ?? '');
    }
    if ($schema === '') {
        return [];
    }
    $schema_sql = $db->real_escape_string($schema);
    $sql = "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '{$schema_sql}'
              AND COLUMN_NAME IN ('user_id','created_by','removed_by','updated_by','sold_by','added_by')
              AND DATA_TYPE IN ('int','bigint','mediumint','smallint','tinyint')";
    $res = @$db->query($sql);
    if (!$res) {
        return [];
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $table = (string) $row['TABLE_NAME'];
        $col = (string) $row['COLUMN_NAME'];
        if ($table === 'users' && $col === 'id') {
            continue;
        }
        $out[] = [$table, $col];
    }
    return $out;
}

function ame_sync_users_autoincrement(mysqli $db): void
{
    $res = @$db->query('SELECT COALESCE(MAX(id), 0) + 1 AS n FROM users');
    if (!$res) {
        return;
    }
    $n = (int) ($res->fetch_assoc()['n'] ?? 1);
    if ($n < 1) {
        $n = 1;
    }
    @$db->query('ALTER TABLE users AUTO_INCREMENT = ' . $n);
}

/**
 * Change users.id and rewrite every user_id / created_by pointer
 * so sales, ledger and logs stay attached.
 *
 * @return string|null error, or null on success
 */
function ame_set_user_id($db, int $old_id, int $new_id): ?string
{
    if (!($db instanceof mysqli) || $old_id <= 0) {
        return 'সঠিক ব্যবহারকারী বাছুন। / Choose a valid user.';
    }
    if ($new_id <= 0 || $new_id > 2000000000) {
        return 'আইডি ১ বা তার বেশি একটি সংখ্যা হতে হবে। / ID must be a number of 1 or more.';
    }
    if ($old_id === $new_id) {
        return null;
    }
    $st = $db->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    if (!$st) {
        return 'ডাটাবেজ ত্রুটি। / Database error.';
    }
    $st->bind_param('i', $old_id);
    $st->execute();
    $exists = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$exists) {
        return 'ব্যবহারকারী পাওয়া যায়নি। / User not found.';
    }
    if (!ame_user_id_free($db, $new_id, $old_id)) {
        return 'এই আইডি অন্যজন ব্যবহার করছে — বিক্রয় ইতিহাস যাতে না ভাঙে, খালি আইডি দিন। / That ID is taken. Pick a free ID so sales history stays intact.';
    }

    $refs = ame_user_id_ref_columns($db);
    $db->begin_transaction();
    @$db->query('SET FOREIGN_KEY_CHECKS=0');
    $up = $db->prepare('UPDATE users SET id = ? WHERE id = ? LIMIT 1');
    if (!$up) {
        @$db->query('SET FOREIGN_KEY_CHECKS=1');
        $db->rollback();
        return 'আইডি বসানো যায়নি। / Could not set ID.';
    }
    $up->bind_param('ii', $new_id, $old_id);
    if (!$up->execute()) {
        $up->close();
        @$db->query('SET FOREIGN_KEY_CHECKS=1');
        $db->rollback();
        return 'আইডি বসানো যায়নি — ধাক্কা খেয়েছে। / Could not set ID — number clash.';
    }
    $up->close();

    foreach ($refs as [$table, $col]) {
        $safe_table = str_replace('`', '', $table);
        $safe_col = str_replace('`', '', $col);
        $sql = 'UPDATE `' . $safe_table . '` SET `' . $safe_col . '` = ? WHERE `' . $safe_col . '` = ?';
        $ref = $db->prepare($sql);
        if (!$ref) {
            continue;
        }
        $ref->bind_param('ii', $new_id, $old_id);
        $ref->execute();
        $ref->close();
    }

    $parked = 'removed.' . $old_id;
    $new_parked = 'removed.' . $new_id;
    $fix = $db->prepare('UPDATE users SET username = ? WHERE id = ? AND username = ? LIMIT 1');
    if ($fix) {
        $fix->bind_param('sis', $new_parked, $new_id, $parked);
        $fix->execute();
        $fix->close();
    }

    ame_sync_users_autoincrement($db);
    @$db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->commit();

    if ((int) ($_SESSION['user_id'] ?? 0) === $old_id) {
        $_SESSION['user_id'] = $new_id;
    }
    if (function_exists('log_activity')) {
        log_activity('User ID Set', 'Settings', '#' . $old_id . ' → #' . $new_id);
    }
    return null;
}

function ame_remember_user_name(mysqli $db, int $user_id, string $name): void
{
    if ($user_id <= 0) {
        return;
    }
    $name = trim($name);
    if ($name === '' || str_starts_with($name, 'removed.') || str_starts_with($name, 'deleted.')) {
        $name = '#' . $user_id;
    }
    $st = @$db->prepare('INSERT INTO user_name_memory (user_id, display_name, deleted_at) VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), deleted_at = NOW()');
    if ($st) {
        $st->bind_param('is', $user_id, $name);
        @$st->execute();
        $st->close();
    }
}

function ame_recalled_user_name(mysqli $db, int $user_id): string
{
    if ($user_id <= 0) {
        return '';
    }
    $st = @$db->prepare('SELECT display_name FROM user_name_memory WHERE user_id = ? LIMIT 1');
    if (!$st) {
        return '';
    }
    $st->bind_param('i', $user_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return trim((string) ($row['display_name'] ?? ''));
}

function ame_user_unlink_file(?string $name, string $folder): void
{
    $name = trim((string) $name);
    if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
        return;
    }
    $path = dirname(__DIR__) . '/assets/img/' . $name;
    if ($folder === 'uploads') {
        $path = dirname(__DIR__) . '/uploads/users/' . $name;
    }
    if (is_file($path)) {
        @unlink($path);
    }
}

/** চিরতরে ইউজার মুছে। বিক্রয়/লেজার থাকে। @return string|null */
function ame_purge_user($db, int $user_id): ?string
{
    if (!($db instanceof mysqli) || $user_id <= 0) {
        return 'সঠিক ব্যবহারকারী বাছুন। / Choose a valid user.';
    }
    ensure_user_schema($db);
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'ডাটাবেজ ত্রুটি। / Database error.';
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        return null;
    }
    if (ame_user_is_purged($user)) {
        return null;
    }
    if (!ame_user_is_removed($user)) {
        return 'আগে তালিকা থেকে সরান, তারপর চিরতরে ডিলিট করুন। / Remove the user first, then delete forever.';
    }
    $me = (int) ($_SESSION['user_id'] ?? 0);
    if ($me === $user_id) {
        return 'নিজেকে চিরতরে ডিলিট করা যাবে না। / You cannot delete yourself.';
    }
    if (function_exists('ame_super_admin_user_ids')) {
        $sa = ame_super_admin_user_ids($db);
        if (in_array($user_id, $sa, true)) {
            return 'সুপার অ্যাডমিন ইউজার চিরতরে ডিলিট করা যাবে না। / A super admin cannot be deleted.';
        }
    }
    $name = ame_user_display_name($user);
    ame_remember_user_name($db, $user_id, $name);

    foreach (['user_permissions', 'password_resets', 'password_reset_otps'] as $table) {
        $chk = @$db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
        if ($chk && $chk->num_rows > 0) {
            @$db->query('DELETE FROM `' . $table . '` WHERE user_id = ' . $user_id);
        }
    }
    $orig = trim((string) ($user['removed_username'] ?? $user['username'] ?? ''));
    if ($orig !== '') {
        $la = @$db->prepare('DELETE FROM login_attempts WHERE username = ?');
        if ($la) {
            $la->bind_param('s', $orig);
            @$la->execute();
            $la->close();
        }
    }
    ame_user_unlink_file((string) ($user['photo'] ?? ''), 'img');
    ame_user_unlink_file((string) ($user['document'] ?? ''), 'img');

    @$db->query('SET FOREIGN_KEY_CHECKS=0');
    $gone = @$db->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
    $ok = false;
    if ($gone) {
        $gone->bind_param('i', $user_id);
        $ok = (bool) @$gone->execute();
        $gone->close();
    }
    @$db->query('SET FOREIGN_KEY_CHECKS=1');

    if (!$ok) {
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $parked = 'deleted.' . $user_id;
        $now = date('Y-m-d H:i:s');
        $up = @$db->prepare("UPDATE users SET status = 0, username = ?, password = ?, purged_at = ?, removed_at = IFNULL(removed_at, ?) WHERE id = ? LIMIT 1");
        if ($up) {
            $up->bind_param('ssssi', $parked, $hash, $now, $now, $user_id);
            @$up->execute();
            $up->close();
        }
    }
    if (function_exists('ame_bump_auth_version') && $ok === false) {
        ame_bump_auth_version($db, $user_id);
    }
    if (function_exists('log_activity')) {
        log_activity('User purged', 'Settings', $name . ' (#' . $user_id . ')');
    }
    return null;
}

/** @return array{ok:int,fail:int,errors:list<string>} */
function ame_purge_all_removed_users($db): array
{
    $out = ['ok' => 0, 'fail' => 0, 'errors' => []];
    if (!($db instanceof mysqli)) {
        $out['errors'][] = 'ডাটাবেজ ত্রুটি।';
        return $out;
    }
    ensure_user_schema($db);
    $res = @$db->query("SELECT id FROM users WHERE removed_at IS NOT NULL AND removed_at <> '' AND (purged_at IS NULL OR purged_at = '')");
    if (!$res) {
        $out['errors'][] = 'সরানো ইউজার পাওয়া যায়নি।';
        return $out;
    }
    while ($row = $res->fetch_assoc()) {
        $err = ame_purge_user($db, (int) $row['id']);
        if ($err === null) {
            $out['ok']++;
        } else {
            $out['fail']++;
            $out['errors'][] = $err;
        }
    }
    return $out;
}

/**
 * Unique login rows for সিস্টেম ইউজার. Never JOIN users×employees in SQL —
 * that can duplicate or drop accounts. Employee fields are attached 1:1 after.
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function ame_user_roster_unique(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0 || isset($out[$id])) {
            continue;
        }
        $out[$id] = $row;
    }
    return array_values($out);
}

/**
 * @param list<array<string,mixed>> $rows
 * @return array{total:int,listed:int,active:int,inactive:int,removed:int,purged:int}
 */
function ame_user_roster_stats(array $rows): array
{
    $stats = [
        'total' => 0,
        'listed' => 0,
        'active' => 0,
        'inactive' => 0,
        'removed' => 0,
        'purged' => 0,
    ];
    foreach (ame_user_roster_unique($rows) as $u) {
        $stats['total']++;
        if (ame_user_is_purged($u)) {
            $stats['purged']++;
            continue;
        }
        if (ame_user_is_removed($u)) {
            $stats['removed']++;
            continue;
        }
        $stats['listed']++;
        if (ame_user_row_active($u)) {
            $stats['active']++;
        } else {
            $stats['inactive']++;
        }
    }
    return $stats;
}

function ame_user_row_is_super_admin(array $row): bool
{
    if (!empty($row['is_super_admin'])) {
        return true;
    }
    $role = strtolower(trim((string) ($row['role'] ?? '')));
    return $role === 'super_admin' || $role === 'superadmin';
}

/** @return string|null denial reason, or null if allowed */
function ame_user_password_reset_denied(array $target, bool $actorIsSa): ?string
{
    $id = (int) ($target['id'] ?? 0);
    if ($id <= 0) {
        return 'ইউজার পাওয়া যায়নি। / User not found.';
    }
    if (ame_user_is_purged($target)) {
        return 'মুছে ফেলা ইউজারের পাসওয়ার্ড বদলানো যায় না। / A deleted user cannot have a password set.';
    }
    if (ame_user_is_removed($target)) {
        return 'সরানো ইউজারকে আগে তালিকায় ফেরত আনুন। / Restore the user first.';
    }
    if (ame_user_row_is_super_admin($target) && !$actorIsSa) {
        return 'সুপার অ্যাডমিনের পাসওয়ার্ড শুধু সুপার অ্যাডমিন বদলাতে পারেন। / Only Super Admin can change a Super Admin password.';
    }
    return null;
}

/**
 * Every login account, one row per users.id. Shop money is never written.
 *
 * @return list<array<string,mixed>>
 */
function ame_user_roster_load($db): array
{
    if (!($db instanceof mysqli)) {
        return [];
    }
    if (function_exists('ensure_user_schema')) {
        ensure_user_schema($db);
    }
    $res = @$db->query('SELECT * FROM users ORDER BY id DESC');
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    $rows = ame_user_roster_unique($rows);
    $empIds = [];
    foreach ($rows as $row) {
        $eid = (int) ($row['employee_id'] ?? 0);
        if ($eid > 0) {
            $empIds[$eid] = $eid;
        }
    }
    $emps = [];
    if ($empIds !== []) {
        $in = implode(',', array_map('intval', $empIds));
        $er = @$db->query("SELECT id, name, phone, employee_code, photo FROM employees WHERE id IN ({$in})");
        if (!$er) {
            $er = @$db->query("SELECT id, name, phone FROM employees WHERE id IN ({$in})");
        }
        if ($er) {
            while ($e = $er->fetch_assoc()) {
                $emps[(int) $e['id']] = $e;
            }
            $er->free();
        }
    }
    foreach ($rows as &$row) {
        $eid = (int) ($row['employee_id'] ?? 0);
        $e = $emps[$eid] ?? [];
        $row['emp_id'] = $eid;
        $row['emp_name'] = (string) ($e['name'] ?? '');
        $row['emp_phone'] = (string) ($e['phone'] ?? '');
        $row['emp_code'] = (string) ($e['employee_code'] ?? '');
        $row['emp_photo'] = (string) ($e['photo'] ?? '');
    }
    unset($row);
    if (function_exists('ame_attach_role_labels')) {
        $rows = ame_attach_role_labels($db, $rows);
    }
    return $rows;
}

/** Visible "#id username" line so missing cards cannot hide who is in the database. */
function ame_user_roster_id_line(array $rows): string
{
    $bits = [];
    foreach (ame_user_roster_unique($rows) as $u) {
        if (ame_user_is_purged($u)) {
            continue;
        }
        $id = (int) ($u['id'] ?? 0);
        $name = trim((string) ($u['username'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($u['full_name'] ?? $u['emp_name'] ?? ''));
        }
        $tag = '#' . $id . ($name !== '' ? ' ' . $name : '');
        if (ame_user_is_removed($u)) {
            $tag .= ' (সরানো)';
        }
        $bits[] = $tag;
    }
    return implode(' · ', $bits);
}

/**
 * Staff who can work in the shop but have no users row yet.
 *
 * @return list<array<string,mixed>>
 */
function ame_user_roster_employees_without_login($db): array
{
    if (!($db instanceof mysqli)) {
        return [];
    }
    $sql = 'SELECT e.id, e.name, e.phone, e.employee_code, e.photo, e.user_role, e.status
            FROM employees e
            WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.employee_id = e.id)
            ORDER BY e.id ASC';
    $res = @$db->query($sql);
    if (!$res) {
        $res = @$db->query('SELECT id, name, phone FROM employees ORDER BY id ASC');
        if (!$res) {
            return [];
        }
        $have = [];
        $ur = @$db->query('SELECT employee_id FROM users WHERE employee_id IS NOT NULL AND employee_id > 0');
        if ($ur) {
            while ($x = $ur->fetch_assoc()) {
                $have[(int) $x['employee_id']] = true;
            }
            $ur->free();
        }
        $out = [];
        while ($row = $res->fetch_assoc()) {
            if (isset($have[(int) $row['id']])) {
                continue;
            }
            $out[] = $row;
        }
        $res->free();
        return $out;
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $res->free();
    return $out;
}

/**
 * Admin/SA sets another user's login password. Never logs the secret.
 *
 * @return array{ok:bool,error?:string,message?:string,username?:string}
 */
function ame_admin_set_user_password(
    mysqli $db,
    int $userId,
    string $newPassword,
    string $confirm,
    string $actorPassword,
    bool $forceChange
): array {
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'ইউজার পাওয়া যায়নি। / User not found.'];
    }
    if (is_file(__DIR__ . '/user_profile.php')) {
        require_once __DIR__ . '/user_profile.php';
    }
    if (is_file(__DIR__ . '/super_admin.php')) {
        require_once __DIR__ . '/super_admin.php';
    }
    if ($actorPassword === ''
        || !function_exists('ame_profile_actor_password_ok')
        || !ame_profile_actor_password_ok($db, $actorPassword)
    ) {
        return ['ok' => false, 'error' => 'আপনার নিজের পাসওয়ার্ড সঠিক নয়। / Your own password is wrong.'];
    }
    $st = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    if (!$st) {
        return ['ok' => false, 'error' => 'ডাটাবেজ ত্রুটি। / Database error.'];
    }
    $st->bind_param('i', $userId);
    $st->execute();
    $target = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$target) {
        return ['ok' => false, 'error' => 'ইউজার পাওয়া যায়নি। / User not found.'];
    }
    $actorIsSa = function_exists('ame_is_super_admin') && ame_is_super_admin();
    $deny = ame_user_password_reset_denied($target, $actorIsSa);
    if ($deny !== null) {
        return ['ok' => false, 'error' => $deny];
    }
    if (!function_exists('ame_validate_password')) {
        return ['ok' => false, 'error' => 'পাসওয়ার্ড নিয়ম পাওয়া যায়নি।'];
    }
    $pwErr = ame_validate_password($newPassword);
    if ($pwErr !== null) {
        return ['ok' => false, 'error' => $pwErr];
    }
    if ($newPassword !== $confirm) {
        return ['ok' => false, 'error' => 'দুটো পাসওয়ার্ড মিলছে না। / The two passwords do not match.'];
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $force = $forceChange ? 1 : 0;
    $upd = $db->prepare('UPDATE users SET password = ?, must_change_password = ?, locked_until = NULL WHERE id = ? LIMIT 1');
    if ($upd) {
        $upd->bind_param('sii', $hash, $force, $userId);
    } else {
        $upd = $db->prepare('UPDATE users SET password = ?, must_change_password = ? WHERE id = ? LIMIT 1');
        if ($upd) {
            $upd->bind_param('sii', $hash, $force, $userId);
        } else {
            $upd = $db->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
            if (!$upd) {
                return ['ok' => false, 'error' => 'পাসওয়ার্ড সেভ হয়নি। / Password was not saved.'];
            }
            $upd->bind_param('si', $hash, $userId);
        }
    }
    if (!$upd->execute()) {
        $upd->close();
        return ['ok' => false, 'error' => 'পাসওয়ার্ড সেভ হয়নি। / Password was not saved.'];
    }
    $upd->close();
    if (function_exists('ame_bump_auth_version')) {
        ame_bump_auth_version($db, $userId);
    }
    $me = function_exists('ame_current_user_id')
        ? ame_current_user_id()
        : (int) ($_SESSION['user_id'] ?? 0);
    if ($me === $userId) {
        $_SESSION['auth_version'] = (int) ($_SESSION['auth_version'] ?? 0) + 1;
        if (!$forceChange) {
            unset($_SESSION['must_change_password']);
        }
    }
    $uname = trim((string) ($target['username'] ?? ''));
    if (function_exists('log_activity')) {
        log_activity(
            'Password Reset',
            'Settings',
            'অ্যাডমিন পাসওয়ার্ড বদলেছেন: ' . $uname . ' (#' . $userId . ')'
        );
    }
    return [
        'ok' => true,
        'username' => $uname,
        'message' => 'পাসওয়ার্ড আপডেট হয়েছে। পুরনো সেশন বন্ধ। / Password updated. Old sessions closed.',
    ];
}
?>
