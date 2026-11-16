<?php
/**
 * ইউজার ও কর্মী — এক পেশাদার প্রোফাইল।
 * সুপার অ্যাডমিন দায়িত্ব এখান থেকেই সরানো যায়।
 */

if (is_file(__DIR__ . '/super_admin.php')) {
    require_once __DIR__ . '/super_admin.php';
}
if (is_file(__DIR__ . '/user_functions.php')) {
    require_once __DIR__ . '/user_functions.php';
}
if (is_file(__DIR__ . '/password_policy.php')) {
    require_once __DIR__ . '/password_policy.php';
}

function ame_profile_roles(mysqli $db): array
{
    $map = function_exists('ame_role_label_map') ? ame_role_label_map($db) : [];
    if ($map === []) {
        return [
            'admin' => 'Admin (সম্পূর্ণ এক্সেস)',
            'manager' => 'Manager',
            'cashier' => 'Cashier',
            'employee' => 'Employee',
        ];
    }
    $out = [];
    foreach ($map as $key => $label) {
        if ($key === strtolower($key)) {
            $out[$key] = $label;
        }
    }
    return $out !== [] ? $out : $map;
}

function ame_profile_is_super_row(mysqli $db, int $userId): bool
{
    if ($userId <= 0 || !function_exists('ame_super_admin_user_ids')) {
        return false;
    }
    return in_array($userId, ame_super_admin_user_ids($db), true);
}

function ame_profile_pack(mysqli $db, int $userId = 0, int $empId = 0): array
{
    if (function_exists('ensure_user_schema')) {
        ensure_user_schema($db);
    }
    if (function_exists('ame_super_admin_boot')) {
        ame_super_admin_boot($db);
    }
    $user = null;
    $emp = null;
    if ($userId > 0) {
        $st = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        if ($st) {
            $st->bind_param('i', $userId);
            $st->execute();
            $user = $st->get_result()->fetch_assoc() ?: null;
            $st->close();
        }
        if ($user && function_exists('ame_user_is_purged') && ame_user_is_purged($user)) {
            $user = null;
        }
        if ($user && $empId < 1) {
            $empId = (int) ($user['employee_id'] ?? 0);
        }
    }
    if ($empId > 0 && is_file(__DIR__ . '/employee_hub.php')) {
        require_once __DIR__ . '/employee_hub.php';
        if (function_exists('ame_emp_hub_row')) {
            $emp = ame_emp_hub_row($db, $empId, true);
        }
    }
    if ($emp && $userId < 1 && function_exists('get_employee_login')) {
        $login = get_employee_login($db, $empId);
        if (is_array($login) && !empty($login['id'])) {
            $userId = (int) $login['id'];
            $st = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            if ($st) {
                $st->bind_param('i', $userId);
                $st->execute();
                $user = $st->get_result()->fetch_assoc() ?: null;
                $st->close();
            }
        }
    }
    $uid = (int) ($user['id'] ?? 0);
    $full = '';
    if ($emp) {
        $full = trim((string) ($emp['name'] ?? ''));
    }
    if ($full === '' && $user) {
        $full = function_exists('ame_user_display_name')
            ? ame_user_display_name($user)
            : trim((string) ($user['full_name'] ?? $user['username'] ?? ''));
    }
    return [
        'user' => $user,
        'emp' => $emp,
        'user_id' => $uid,
        'emp_id' => (int) ($emp['id'] ?? $empId),
        'display_name' => $full,
        'username' => trim((string) ($user['username'] ?? $emp['login_name'] ?? '')),
        'phone' => trim((string) ($user['phone'] ?? $emp['phone'] ?? '')),
        'email' => trim((string) ($user['email'] ?? '')),
        'whatsapp' => trim((string) ($user['whatsapp'] ?? '')),
        'address' => trim((string) ($user['address'] ?? $emp['address'] ?? '')),
        'role' => strtolower(trim((string) ($user['role'] ?? $emp['login_role'] ?? $emp['user_role'] ?? 'employee'))),
        'is_sa' => $uid > 0 && ame_profile_is_super_row($db, $uid),
        'is_removed' => $user && function_exists('ame_user_is_removed') && ame_user_is_removed($user),
        'is_active' => $user ? (function_exists('ame_user_row_active') && ame_user_row_active($user)) : (($emp['status'] ?? 'active') === 'active'),
        'roles' => ame_profile_roles($db),
        'photo_url' => ame_profile_photo_url($user, $emp),
        'designation' => trim((string) ($emp['designation'] ?? '')),
        'employee_code' => trim((string) ($emp['employee_code'] ?? '')),
        'joining_date' => trim((string) ($emp['joining_date'] ?? '')),
    ];
}

function ame_profile_photo_url(?array $user, ?array $emp): string
{
    $empPhoto = trim((string) ($emp['photo'] ?? ''));
    if ($empPhoto !== '') {
        if (function_exists('ame_emp_hub_photo_url')) {
            return ame_emp_hub_photo_url($empPhoto);
        }
        return '/uploads/employees/' . rawurlencode($empPhoto);
    }
    $uPhoto = trim((string) ($user['photo'] ?? ''));
    if ($uPhoto === '') {
        return '/assets/img/user-placeholder.png';
    }
    if (str_starts_with($uPhoto, '/') || str_starts_with($uPhoto, 'http://') || str_starts_with($uPhoto, 'https://')) {
        return $uPhoto;
    }
    if (str_starts_with($uPhoto, 'user_') || str_starts_with($uPhoto, 'emp_')) {
        $folder = str_starts_with($uPhoto, 'emp_') ? 'employees' : 'users';
        return '/uploads/' . $folder . '/' . rawurlencode($uPhoto);
    }
    return '/assets/img/' . rawurlencode($uPhoto);
}

function ame_profile_save_photo(array $files, string $kind, int $id): ?string
{
    if (empty($files['photo']) || !is_array($files['photo'])) {
        return null;
    }
    $f = $files['photo'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) {
        return null;
    }
    if ((int) ($f['size'] ?? 0) <= 0 || (int) $f['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('ছবি ৫ মেগাবাইটের মধ্যে দিন।');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $f['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($map[$mime])) {
        throw new RuntimeException('শুধু JPG, PNG বা WEBP ছবি চলবে।');
    }
    $root = dirname(__DIR__);
    $dir = $kind === 'emp' ? ($root . '/uploads/employees/') : ($root . '/uploads/users/');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('ছবির ফোল্ডার তৈরি হয়নি।');
    }
    $prefix = $kind === 'emp' ? 'emp_' : 'user_';
    $name = $prefix . max(1, $id) . '_' . time() . '_' . random_int(1000, 9999) . '.' . $map[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) {
        throw new RuntimeException('ছবি রাখা যায়নি।');
    }
    return $name;
}

function ame_profile_actor_password_ok(mysqli $db, string $password): bool
{
    $uid = function_exists('ame_current_user_id') ? ame_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0 || $password === '') {
        return false;
    }
    $st = @$db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    if (!$st) {
        return false;
    }
    $st->bind_param('i', $uid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $hash = (string) ($row['password'] ?? '');
    return $hash !== '' && password_verify($password, $hash);
}

function ame_profile_can_edit_target(mysqli $db, array $pack): bool
{
    if (function_exists('ame_is_super_admin') && ame_is_super_admin()) {
        return true;
    }
    $role = strtolower((string) ($_SESSION['role'] ?? ''));
    if ($role !== 'admin') {
        return false;
    }
    return empty($pack['is_sa']);
}

function ame_profile_save(mysqli $db, array $in, array $files = []): array
{
    $userId = (int) ($in['user_id'] ?? 0);
    $empId = (int) ($in['emp_id'] ?? 0);
    $pack = ame_profile_pack($db, $userId, $empId);
    if (!$pack['user'] && !$pack['emp']) {
        return ['ok' => false, 'error' => 'প্রোফাইল পাওয়া যায়নি।'];
    }
    if (!ame_profile_can_edit_target($db, $pack)) {
        return ['ok' => false, 'error' => 'সুপার অ্যাডমিনের প্রোফাইল সাধারণ অ্যাডমিন এডিট করতে পারেন না।'];
    }
    $userId = (int) $pack['user_id'];
    $empId = (int) $pack['emp_id'];
    $name = trim((string) ($in['full_name'] ?? $in['name'] ?? ''));
    $username = trim((string) ($in['username'] ?? ''));
    $phone = preg_replace('/\s+/', '', (string) ($in['phone'] ?? ''));
    $email = trim((string) ($in['email'] ?? ''));
    $whatsapp = preg_replace('/\s+/', '', (string) ($in['whatsapp'] ?? ''));
    $address = trim((string) ($in['address'] ?? ''));
    $role = strtolower(trim((string) ($in['role'] ?? $pack['role'])));
    $roles = array_keys($pack['roles']);
    if ($role === '' || ($roles !== [] && !in_array($role, $roles, true))) {
        $role = $pack['role'] !== '' ? $pack['role'] : 'employee';
    }
    $password = (string) ($in['password'] ?? '');
    $statusOn = !isset($in['status']) || (string) $in['status'] === '1' || (string) $in['status'] === 'active';
    $me = function_exists('ame_current_user_id') ? ame_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);

    if ($name === '') {
        return ['ok' => false, 'error' => 'নাম খালি রাখা যাবে না।'];
    }
    if ($empId > 0 && $phone === '') {
        return ['ok' => false, 'error' => 'কর্মীর মোবাইল খালি রাখা যাবে না।'];
    }
    if ($userId > 0 && $username === '') {
        return ['ok' => false, 'error' => 'লগইন নাম খালি রাখা যাবে না।'];
    }
    if ($pack['is_sa'] && $role !== 'admin' && !(function_exists('ame_is_super_admin') && ame_is_super_admin())) {
        return ['ok' => false, 'error' => 'সুপার অ্যাডমিনের রোল বদলাতে সুপার অ্যাডমিন লগইন লাগে।'];
    }
    if ($password !== '') {
        if (!function_exists('ame_validate_password')) {
            return ['ok' => false, 'error' => 'পাসওয়ার্ড নিয়ম পাওয়া যায়নি।'];
        }
        $pwErr = ame_validate_password($password);
        if ($pwErr !== null) {
            return ['ok' => false, 'error' => $pwErr];
        }
    }
    if ($userId > 0 && $username !== (string) ($pack['user']['username'] ?? '')) {
        $chk = $db->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        if ($chk) {
            $chk->bind_param('si', $username, $userId);
            $chk->execute();
            $clash = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($clash) {
                return ['ok' => false, 'error' => 'এই লগইন নাম অন্য ইউজারে আছে।'];
            }
        }
    }
    if ($userId > 0 && $me === $userId && !$statusOn) {
        return ['ok' => false, 'error' => 'নিজের লগইন বন্ধ করা যাবে না।'];
    }
    if ($userId > 0 && !$statusOn && $role === 'admin' && function_exists('ame_count_active_admins') && ame_count_active_admins($db, $userId) < 1) {
        return ['ok' => false, 'error' => 'শেষ অ্যাডমিন লগইন বন্ধ করা যাবে না।'];
    }
    if ($userId > 0 && $pack['is_sa'] && !$statusOn) {
        return ['ok' => false, 'error' => 'সুপার অ্যাডমিন লগইন বন্ধ করতে আগে দায়িত্ব সরান।'];
    }

    if ($empId > 0 && is_file(__DIR__ . '/employee_hub.php')) {
        require_once __DIR__ . '/employee_hub.php';
        $photo = ame_profile_save_photo($files, 'emp', $empId);
        $empIn = [
            'id' => $empId,
            'name' => $name,
            'phone' => $phone !== '' ? $phone : (string) ($pack['emp']['phone'] ?? ''),
            'address' => $address,
            'designation' => trim((string) ($in['designation'] ?? $pack['designation'])),
            'employee_code' => (string) ($pack['employee_code'] ?? ''),
            'salary' => (float) ($pack['emp']['salary'] ?? 0),
            'department_id' => (int) ($pack['emp']['department_id'] ?? 0),
            'payment_type' => (string) ($pack['emp']['payment_type'] ?? 'percentage'),
            'service_share_percent' => (float) ($pack['emp']['service_share_percent'] ?? 50),
            'snack_mode' => (string) ($pack['emp']['snack_mode'] ?? 'none'),
            'daily_snack_amount' => (float) ($pack['emp']['daily_snack_amount'] ?? 0),
            'is_engineer' => (int) ($pack['emp']['is_engineer'] ?? 0),
            'is_service' => (int) ($pack['emp']['is_service'] ?? 0),
            'is_repair' => (int) ($pack['emp']['is_repair'] ?? 0),
            'is_sales' => (int) ($pack['emp']['is_sales'] ?? 0),
            'joining_date' => trim((string) ($in['joining_date'] ?? $pack['joining_date'])),
            'status' => $statusOn ? 'active' : 'inactive',
            'user_role' => $role,
            'login_username' => $username !== '' ? $username : (string) ($pack['username'] ?? ''),
            'login_password' => $password,
            'login_role' => $role,
            'login_status' => $statusOn ? 1 : 0,
        ];
        if (function_exists('ame_emp_hub_save_person')) {
            try {
                ame_emp_hub_save_person($db, $empIn, $photo ? ['photo' => ['error' => UPLOAD_ERR_NO_FILE]] : []);
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }
        if ($photo) {
            $up = $db->prepare('UPDATE employees SET photo = ? WHERE id = ?');
            if ($up) {
                $up->bind_param('si', $photo, $empId);
                $up->execute();
                $up->close();
            }
        }
    } else {
        $photo = ame_profile_save_photo($files, 'user', $userId);
        if ($photo) {
            $up = $db->prepare('UPDATE users SET photo = ? WHERE id = ?');
            if ($up) {
                $up->bind_param('si', $photo, $userId);
                $up->execute();
                $up->close();
            }
        }
    }

    if ($userId > 0) {
        $cols = [];
        $cr = @$db->query('SHOW COLUMNS FROM users');
        if ($cr) {
            while ($c = $cr->fetch_assoc()) {
                $cols[] = strtolower((string) ($c['Field'] ?? ''));
            }
        }
        $flag = $statusOn ? 1 : 0;
        $sql = 'UPDATE users SET username = ?, role = ?, phone = ?, email = ?, whatsapp = ?, address = ?, status = ?';
        $types = 'ssssssi';
        $vals = [$username, $role, $phone, $email, $whatsapp, $address, $flag];
        if (in_array('full_name', $cols, true)) {
            $sql .= ', full_name = ?';
            $types .= 's';
            $vals[] = $name;
        }
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ', password = ?';
            $types .= 's';
            $vals[] = $hash;
        }
        $sql .= ' WHERE id = ? LIMIT 1';
        $types .= 'i';
        $vals[] = $userId;
        $st = $db->prepare($sql);
        if (!$st) {
            return ['ok' => false, 'error' => 'প্রোফাইল সংরক্ষণ যায়নি।'];
        }
        $st->bind_param($types, ...$vals);
        if (!$st->execute()) {
            $st->close();
            return ['ok' => false, 'error' => 'প্রোফাইল সংরক্ষণ যায়নি।'];
        }
        $st->close();
        if (function_exists('ame_remember_user_name')) {
            ame_remember_user_name($db, $userId, $name);
        }
        if ($password !== '' && function_exists('ame_bump_auth_version')) {
            ame_bump_auth_version($db, $userId);
        }
        if ($empId > 0 && function_exists('ame_sync_user_employee_identity')) {
            ame_sync_user_employee_identity($db, $userId, $empId);
        }
    }

    if (function_exists('log_activity')) {
        log_activity('Profile edit', 'Settings', $name . ' (#' . $userId . ')');
    }
    return ['ok' => true, 'error' => '', 'user_id' => $userId, 'emp_id' => $empId];
}

function ame_profile_href(int $userId = 0, int $empId = 0): string
{
    if ($userId > 0) {
        return '/modules/settings/user_profile.php?id=' . $userId;
    }
    if ($empId > 0) {
        return '/modules/settings/user_profile.php?emp=' . $empId;
    }
    return '/modules/settings/users.php';
}
