<?php
/**
 * IAM role control — change any user's job role, including Super Admin.
 * Shop money is never written.
 */
if (is_file(__DIR__ . '/super_admin.php')) {
    require_once __DIR__ . '/super_admin.php';
}
if (is_file(__DIR__ . '/user_functions.php')) {
    require_once __DIR__ . '/user_functions.php';
}
if (is_file(__DIR__ . '/user_profile.php')) {
    require_once __DIR__ . '/user_profile.php';
}

if (!function_exists('ame_role_control_ensure')) {
    function ame_role_control_ensure(mysqli $db): void
    {
        if (function_exists('ensure_user_schema')) {
            ensure_user_schema($db);
        }
        if (function_exists('ame_super_admin_ensure_column')) {
            ame_super_admin_ensure_column($db);
        }
        @$db->query("CREATE TABLE IF NOT EXISTS user_role_changes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            actor_id INT NOT NULL,
            old_role VARCHAR(100) NOT NULL DEFAULT '',
            new_role VARCHAR(100) NOT NULL DEFAULT '',
            old_super TINYINT(1) NOT NULL DEFAULT 0,
            new_super TINYINT(1) NOT NULL DEFAULT 0,
            reason VARCHAR(255) NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_urc_user (user_id, created_at),
            KEY idx_urc_actor (actor_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('ame_role_control_catalog')) {
    /** @return array<string,string> */
    function ame_role_control_catalog(mysqli $db): array
    {
        $map = function_exists('ame_profile_roles') ? ame_profile_roles($db) : [];
        unset($map['super_admin'], $map['superadmin']);
        if ($map === []) {
            $map = [
                'admin' => 'Admin',
                'manager' => 'Manager',
                'cashier' => 'Cashier',
                'employee' => 'Employee',
                'family' => 'Family Member',
                'viewer' => 'View Only',
            ];
        }
        return $map;
    }
}

if (!function_exists('ame_role_control_is_admin_like_row')) {
    function ame_role_control_is_admin_like_row(array $row): bool
    {
        if (!empty($row['is_super_admin'])) {
            return true;
        }
        $role = strtolower(trim((string) ($row['role'] ?? '')));
        return $role === 'admin' || $role === 'super_admin' || $role === 'superadmin';
    }
}

if (!function_exists('ame_role_control_users')) {
    /** @return list<array<string,mixed>> */
    function ame_role_control_users(mysqli $db): array
    {
        ame_role_control_ensure($db);
        $sql = 'SELECT u.id, u.username, u.role, u.status, u.phone, u.full_name, u.photo,
                       u.is_super_admin, u.removed_at, u.purged_at
                FROM users u
                ORDER BY u.is_super_admin DESC, u.id ASC';
        $res = @$db->query($sql);
        if (!$res) {
            $res = @$db->query('SELECT id, username, role, status, phone FROM users ORDER BY id ASC');
        }
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        $out = [];
        foreach ($rows as $row) {
            if (function_exists('ame_user_is_purged') && ame_user_is_purged($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $role = strtolower(trim((string) ($row['role'] ?? '')));
            $out[] = [
                'id' => $id,
                'username' => (string) ($row['username'] ?? ''),
                'display_name' => trim((string) ($row['full_name'] ?? '')) !== ''
                    ? (string) $row['full_name']
                    : (string) ($row['username'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'role' => $role,
                'is_super_admin' => !empty($row['is_super_admin']) ? 1 : 0,
                'is_removed' => function_exists('ame_user_is_removed') && ame_user_is_removed($row),
                'is_active' => function_exists('ame_user_row_active') ? ame_user_row_active($row) : true,
                'photo' => (string) ($row['photo'] ?? ''),
            ];
        }
        if (function_exists('ame_attach_role_labels')) {
            $out = ame_attach_role_labels($db, $out);
        }
        return $out;
    }
}

if (!function_exists('ame_role_control_counts')) {
    /** @param list<array<string,mixed>> $users */
    function ame_role_control_counts(array $users): array
    {
        $n = ['total' => 0, 'super' => 0, 'admin' => 0, 'other' => 0];
        foreach ($users as $u) {
            if (!empty($u['is_removed'])) {
                continue;
            }
            $n['total']++;
            if (!empty($u['is_super_admin'])) {
                $n['super']++;
            }
            if (ame_role_control_is_admin_like_row($u)) {
                $n['admin']++;
            } else {
                $n['other']++;
            }
        }
        return $n;
    }
}

if (!function_exists('ame_role_control_stepup_needed')) {
    function ame_role_control_stepup_needed(array $before, string $newRole, int $newSuper, int $actorId): bool
    {
        $oldRole = strtolower((string) ($before['role'] ?? ''));
        $oldSuper = !empty($before['is_super_admin']) ? 1 : 0;
        if ($oldRole !== $newRole && ($oldRole === 'admin' || $newRole === 'admin')) {
            return true;
        }
        if ($oldSuper !== $newSuper) {
            return true;
        }
        return $actorId > 0 && $actorId === (int) ($before['id'] ?? 0);
    }
}

if (!function_exists('ame_role_control_guard')) {
    /**
     * Last Super Admin / last admin-like lock. Empty string = allowed.
     *
     * @param list<array<string,mixed>> $users
     */
    function ame_role_control_guard(array $users, int $targetId, string $newRole, int $newSuper): string
    {
        $newRole = strtolower($newRole);
        $target = null;
        $superLeft = 0;
        $adminLeft = 0;
        foreach ($users as $u) {
            if (!empty($u['is_removed'])) {
                continue;
            }
            $id = (int) ($u['id'] ?? 0);
            $role = $id === $targetId ? $newRole : strtolower((string) ($u['role'] ?? ''));
            $sa = $id === $targetId ? $newSuper : (!empty($u['is_super_admin']) ? 1 : 0);
            $row = ['id' => $id, 'role' => $role, 'is_super_admin' => $sa];
            if ($id === $targetId) {
                $target = $u;
            }
            if ($sa === 1) {
                $superLeft++;
            }
            if (ame_role_control_is_admin_like_row($row)) {
                $adminLeft++;
            }
        }
        if ($target === null) {
            return 'ইউজার পাওয়া যায়নি।';
        }
        if ($superLeft < 1) {
            return 'শেষ সুপার অ্যাডমিনকে সরানো যাবে না। আগে অন্য কাউকে দায়িত্ব দিন।';
        }
        if ($adminLeft < 1) {
            return 'দোকানে অন্তত একজন অ্যাডমিন বা সুপার অ্যাডমিন রাখতে হবে।';
        }
        return '';
    }
}

if (!function_exists('ame_role_control_apply')) {
    /**
     * @return array{ok:bool,error:string,message:string}
     */
    function ame_role_control_apply(mysqli $db, array $in): array
    {
        ame_role_control_ensure($db);
        if (!function_exists('ame_is_super_admin') || !ame_is_super_admin()) {
            return ['ok' => false, 'error' => 'শুধু সুপার অ্যাডমিন সবার রোল বদলাতে পারেন।', 'message' => ''];
        }
        $targetId = (int) ($in['user_id'] ?? 0);
        $newRole = strtolower(trim((string) ($in['role'] ?? '')));
        $newSuper = !empty($in['is_super_admin']) ? 1 : 0;
        $reason = trim((string) ($in['reason'] ?? ''));
        $password = (string) ($in['actor_password'] ?? '');
        $actorId = function_exists('ame_current_user_id') ? ame_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
        $catalog = ame_role_control_catalog($db);
        if ($targetId < 1) {
            return ['ok' => false, 'error' => 'সঠিক ইউজার বাছুন।', 'message' => ''];
        }
        if ($newRole === 'super_admin' || $newRole === 'superadmin') {
            $newRole = 'admin';
            $newSuper = 1;
        }
        if ($newRole === '' || !isset($catalog[$newRole])) {
            return ['ok' => false, 'error' => 'এই রোলটি তালিকায় নেই।', 'message' => ''];
        }
        if ($reason !== '' && function_exists('mb_substr')) {
            $reason = mb_substr($reason, 0, 200, 'UTF-8');
        } elseif (strlen($reason) > 200) {
            $reason = substr($reason, 0, 200);
        }
        $users = ame_role_control_users($db);
        $before = null;
        foreach ($users as $u) {
            if ((int) $u['id'] === $targetId) {
                $before = $u;
                break;
            }
        }
        if ($before === null) {
            return ['ok' => false, 'error' => 'ইউজার পাওয়া যায়নি।', 'message' => ''];
        }
        if (!empty($before['is_removed'])) {
            return ['ok' => false, 'error' => 'সরানো ইউজারের রোল বদলাতে আগে ফেরত আনুন।', 'message' => ''];
        }
        $oldRole = strtolower((string) $before['role']);
        $oldSuper = !empty($before['is_super_admin']) ? 1 : 0;
        if ($oldRole === $newRole && $oldSuper === $newSuper) {
            return ['ok' => true, 'error' => '', 'message' => 'রোল আগে থেকেই একই।'];
        }
        $lock = ame_role_control_guard($users, $targetId, $newRole, $newSuper);
        if ($lock !== '') {
            return ['ok' => false, 'error' => $lock, 'message' => ''];
        }
        if (ame_role_control_stepup_needed($before, $newRole, $newSuper, $actorId)) {
            if (!function_exists('ame_profile_actor_password_ok') || !ame_profile_actor_password_ok($db, $password)) {
                return ['ok' => false, 'error' => 'নিজের পাসওয়ার্ড দিন — অ্যাডমিন/সুপার অ্যাডমিন বদলাতে নিশ্চিতকরণ লাগে।', 'message' => ''];
            }
        }
        $flag = $newSuper;
        $st = $db->prepare('UPDATE users SET role = ?, is_super_admin = ? WHERE id = ? LIMIT 1');
        if (!$st) {
            return ['ok' => false, 'error' => 'রোল সংরক্ষণ যায়নি।', 'message' => ''];
        }
        $st->bind_param('sii', $newRole, $flag, $targetId);
        $ok = $st->execute();
        $st->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'রোল সংরক্ষণ যায়নি।', 'message' => ''];
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $log = $db->prepare('INSERT INTO user_role_changes (user_id, actor_id, old_role, new_role, old_super, new_super, reason, ip) VALUES (?,?,?,?,?,?,?,?)');
        if ($log) {
            $log->bind_param('iissiiss', $targetId, $actorId, $oldRole, $newRole, $oldSuper, $newSuper, $reason, $ip);
            $log->execute();
            $log->close();
        }
        if (function_exists('log_activity')) {
            log_activity('role_change', 'settings', ($before['username'] ?? '') . ' role ' . $oldRole . ' → ' . $newRole, [
                'user_id' => $targetId,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'old_super' => $oldSuper,
                'new_super' => $newSuper,
            ]);
        }
        if ($actorId === $targetId) {
            $_SESSION['role'] = $newRole;
            $_SESSION['is_super_admin'] = $newSuper;
        }
        $who = (string) ($before['display_name'] ?? $before['username'] ?? ('#' . $targetId));
        $msg = $who . ' — রোল এখন ' . ($catalog[$newRole] ?? $newRole);
        if ($oldSuper !== $newSuper) {
            $msg .= $newSuper ? ' · সুপার অ্যাডমিন দেওয়া হয়েছে' : ' · সুপার অ্যাডমিন সরানো হয়েছে';
        }
        return ['ok' => true, 'error' => '', 'message' => $msg . '.'];
    }
}

if (!function_exists('ame_role_control_history')) {
    /** @return list<array<string,mixed>> */
    function ame_role_control_history(mysqli $db, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));
        $res = @$db->query('SELECT c.*, u.username AS target_name, a.username AS actor_name
            FROM user_role_changes c
            LEFT JOIN users u ON u.id = c.user_id
            LEFT JOIN users a ON a.id = c.actor_id
            ORDER BY c.id DESC LIMIT ' . $limit);
        $out = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = $row;
            }
            $res->free();
        }
        return $out;
    }
}
