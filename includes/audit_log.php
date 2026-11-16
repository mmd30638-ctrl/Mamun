<?php
/**
 * User Activity / Audit Log — same activity_logs table and same users.
 * New modules: ame_audit([...]) or log_activity($action, $module, $details, $extra).
 */

function ame_audit_secret(): string
{
    $env = (string)(getenv('AME_AUDIT_SECRET') ?: '');
    if ($env !== '') {
        return $env;
    }
    $dir = defined('AME_PRIVATE_DATA_DIR') ? AME_PRIVATE_DATA_DIR : dirname(__DIR__, 2) . '/private_data';
    $file = rtrim($dir, '/') . '/audit_secret.txt';
    if (is_file($file)) {
        $got = trim((string)@file_get_contents($file));
        if ($got !== '') {
            return $got;
        }
    }
    $seed = (string)(getenv('DB_NAME') ?: 'aponshop') . '|ame-audit-v1';
    return hash('sha256', $seed);
}

function ame_audit_table_columns(mysqli $db): array
{
    static $cols = null;
    if (is_array($cols)) {
        return $cols;
    }
    $cols = [];
    $res = @$db->query('SHOW COLUMNS FROM activity_logs');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[(string)$row['Field']] = true;
        }
    }
    return $cols;
}

function ame_audit_action_type(string $action): string
{
    $a = mb_strtolower($action, 'UTF-8');
    $map = [
        'login_fail' => ['fail', 'failed', 'lockout', 'ভুল'],
        'login' => ['login', 'signed in', 'লগইন'],
        'logout' => ['logout', 'signed out', 'লগআউট'],
        'password' => ['password', 'পাসওয়ার্ড'],
        'permission' => ['permission', 'role', 'অনুমতি', 'রোল'],
        'approve' => ['approv', 'অনুমোদন'],
        'reject' => ['reject', 'reverse', 'বাতিল', 'রিভার্স'],
        'print' => ['print', 'প্রিন্ট'],
        'export' => ['export', 'backup', 'download', 'csv', 'share', 'এক্সপোর্ট'],
        'payment' => ['payment', 'pay', 'withdraw', 'receive', 'পেমেন্ট', 'জমা', 'উত্তোলন'],
        'delete' => ['delete', 'removed', 'wipe', 'cancel', 'মুছ', 'বাদ'],
        'edit' => ['update', 'edit', 'change', 'adjust', 'বদল', 'সম্পাদনা', 'আপডেট'],
        'add' => ['add', 'create', 'complet', 'save', 'new', 'যোগ', 'তৈরি', 'সম্পন্ন'],
        'view' => ['view', 'open', 'দেখ'],
    ];
    foreach ($map as $type => $needles) {
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($a, $n)) {
                return $type;
            }
        }
    }
    return 'other';
}

function ame_audit_module_key(string $module): string
{
    $m = mb_strtolower(trim($module), 'UTF-8');
    $aliases = [
        'sales' => 'sales',
        'sale' => 'sales',
        'bikri' => 'sales',
        'purchase' => 'purchase',
        'ক্রয়' => 'purchase',
        'inventory' => 'product',
        'product' => 'product',
        'stock' => 'stock',
        'পণ্য' => 'product',
        'customer' => 'customer',
        'ক্রেতা' => 'customer',
        'supplier' => 'supplier',
        'সাপ্লায়ার' => 'supplier',
        'account' => 'accounts',
        'finance' => 'accounts',
        'হিসাব' => 'accounts',
        'expense' => 'expense',
        'খরচ' => 'expense',
        'setting' => 'settings',
        'security' => 'settings',
        'visual' => 'settings',
        'return' => 'returns',
        'ফেরত' => 'returns',
        'hrm' => 'hrm',
        'loan' => 'loans',
        'partner' => 'accounts',
        'family' => 'family',
        'workshop' => 'workshop',
    ];
    foreach ($aliases as $needle => $key) {
        if (str_contains($m, $needle)) {
            return $key;
        }
    }
    return $m !== '' ? preg_replace('/[^a-z0-9_]+/', '', $m) : 'other';
}

function ame_audit_device(?string $ua): string
{
    $ua = (string)$ua;
    if ($ua === '') {
        return '';
    }
    if (preg_match('/Android/i', $ua)) {
        return 'Android';
    }
    if (preg_match('/iPhone|iPad/i', $ua)) {
        return 'iOS';
    }
    if (preg_match('/Windows/i', $ua)) {
        return 'Windows';
    }
    if (preg_match('/Macintosh|Mac OS/i', $ua)) {
        return 'Mac';
    }
    if (preg_match('/Linux/i', $ua)) {
        return 'Linux';
    }
    return 'Other';
}

function ame_audit_diff(array $old, array $new): array
{
    $skip = ['password', 'password_hash', 'csrf_token', 'token'];
    $linesOld = [];
    $linesNew = [];
    $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
    foreach ($keys as $k) {
        $lk = strtolower((string)$k);
        if (in_array($lk, $skip, true)) {
            continue;
        }
        $a = $old[$k] ?? null;
        $b = $new[$k] ?? null;
        if (is_array($a)) {
            $a = json_encode($a, JSON_UNESCAPED_UNICODE);
        }
        if (is_array($b)) {
            $b = json_encode($b, JSON_UNESCAPED_UNICODE);
        }
        $as = $a === null ? '' : (string)$a;
        $bs = $b === null ? '' : (string)$b;
        if ($as === $bs) {
            continue;
        }
        $linesOld[] = $k . ': ' . $as;
        $linesNew[] = $k . ': ' . $bs;
    }
    return [implode("\n", $linesOld), implode("\n", $linesNew)];
}

function ame_audit_last_hash(mysqli $db): string
{
    $cols = ame_audit_table_columns($db);
    if (!isset($cols['row_hash'])) {
        return '';
    }
    $r = @$db->query('SELECT row_hash FROM activity_logs WHERE row_hash IS NOT NULL AND row_hash <> \'\' ORDER BY id DESC LIMIT 1');
    if ($r && ($row = $r->fetch_assoc())) {
        return (string)$row['row_hash'];
    }
    return '';
}

function ame_audit_row_hash(array $row, string $prev): string
{
    $parts = [
        (string)($row['user_id'] ?? 0),
        (string)($row['action'] ?? ''),
        (string)($row['module'] ?? ''),
        (string)($row['details'] ?? ''),
        (string)($row['action_type'] ?? ''),
        (string)($row['reference'] ?? ''),
        (string)($row['old_value'] ?? ''),
        (string)($row['new_value'] ?? ''),
        (string)($row['ip_address'] ?? ''),
        $prev,
    ];
    return hash_hmac('sha256', implode('|', $parts), ame_audit_secret());
}

function ame_audit(array $event): void
{
    global $db;
    if (!($db instanceof mysqli)) {
        return;
    }
    try {
        $cols = ame_audit_table_columns($db);
        if (!isset($cols['action'])) {
            return;
        }

        $action = trim((string)($event['action'] ?? 'Activity'));
        $module = trim((string)($event['module'] ?? 'System'));
        $details = (string)($event['details'] ?? '');
        if ($action === '') {
            $action = 'Activity';
        }

        $uid = (int)($event['user_id'] ?? ($_SESSION['user_id'] ?? 0));
        $uname = trim((string)($event['username'] ?? ($_SESSION['username'] ?? '')));
        if ($uname === '' && $uid > 0) {
            $q = $db->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
            if ($q) {
                $q->bind_param('i', $uid);
                $q->execute();
                $got = $q->get_result()->fetch_assoc();
                $q->close();
                $uname = (string)($got['username'] ?? '');
            }
        }

        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip = function_exists('ame_client_ip') ? ame_client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $page = (string)($event['page_url'] ?? ($_SERVER['REQUEST_URI'] ?? ''));
        if (strlen($page) > 240) {
            $page = substr($page, 0, 240);
        }
        if (strlen($ua) > 240) {
            $ua = substr($ua, 0, 240);
        }

        $oldVal = (string)($event['old_value'] ?? '');
        $newVal = (string)($event['new_value'] ?? '');
        $oldJson = $event['old'] ?? $event['old_json'] ?? null;
        $newJson = $event['new'] ?? $event['new_json'] ?? null;
        if (is_array($oldJson) || is_array($newJson)) {
            $o = is_array($oldJson) ? $oldJson : [];
            $n = is_array($newJson) ? $newJson : [];
            if ($oldVal === '' && $newVal === '') {
                [$oldVal, $newVal] = ame_audit_diff($o, $n);
            }
            $oldJson = json_encode($o, JSON_UNESCAPED_UNICODE);
            $newJson = json_encode($n, JSON_UNESCAPED_UNICODE);
        } else {
            $oldJson = $oldJson !== null ? (string)$oldJson : '';
            $newJson = $newJson !== null ? (string)$newJson : '';
        }

        $row = [
            'user_id' => $uid > 0 ? $uid : null,
            'username' => $uname,
            'action' => $action,
            'module' => $module,
            'details' => $details,
            'action_type' => (string)($event['action_type'] ?? ame_audit_action_type($action)),
            'page_url' => $page,
            'entity_type' => (string)($event['entity_type'] ?? ''),
            'entity_id' => (string)($event['entity_id'] ?? ''),
            'reference' => (string)($event['reference'] ?? ''),
            'product_id' => isset($event['product_id']) ? (int)$event['product_id'] : null,
            'product_name' => (string)($event['product_name'] ?? ''),
            'party_type' => (string)($event['party_type'] ?? ''),
            'party_id' => isset($event['party_id']) ? (int)$event['party_id'] : null,
            'party_name' => (string)($event['party_name'] ?? ''),
            'amount_total' => isset($event['amount_total']) ? (float)$event['amount_total'] : null,
            'amount_paid' => isset($event['amount_paid']) ? (float)$event['amount_paid'] : null,
            'amount_due' => isset($event['amount_due']) ? (float)$event['amount_due'] : null,
            'amount_discount' => isset($event['amount_discount']) ? (float)$event['amount_discount'] : null,
            'old_value' => $oldVal,
            'new_value' => $newVal,
            'old_json' => $oldJson,
            'new_json' => $newJson,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'device_info' => ame_audit_device($ua),
            'session_key' => substr(hash('sha256', (string)session_id()), 0, 32),
        ];

        if ($row['reference'] === '' && preg_match('/\b([A-Z]{1,4}-[A-Z0-9\-]+|\d{4,})\b/', $details, $m)) {
            $row['reference'] = $m[1];
        }

        $prev = ame_audit_last_hash($db);
        $row['prev_hash'] = $prev;
        $row['row_hash'] = ame_audit_row_hash($row, $prev);

        $fields = [];
        $values = [];
        $types = '';
        foreach ($row as $col => $val) {
            if (!isset($cols[$col])) {
                continue;
            }
            $fields[] = '`' . $col . '`';
            $values[] = $val;
            if (in_array($col, ['user_id', 'product_id', 'party_id'], true)) {
                $types .= 'i';
            } elseif (in_array($col, ['amount_total', 'amount_paid', 'amount_due', 'amount_discount'], true)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        if ($fields === []) {
            return;
        }

        $sql = 'INSERT INTO activity_logs (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log('ame_audit prepare: ' . $db->error);
            ame_audit_insert_core($db, $uid, $action, $module, $details);
            return;
        }
        foreach ($values as $i => $v) {
            if ($v === null) {
                $values[$i] = ($types[$i] === 'i' || $types[$i] === 'd') ? 0 : '';
            }
        }
        $stmt->bind_param($types, ...$values);
        if (!$stmt->execute()) {
            error_log('ame_audit execute: ' . $stmt->error);
            $stmt->close();
            ame_audit_insert_core($db, $uid, $action, $module, $details);
            return;
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('ame_audit skipped: ' . $e->getMessage());
        if (isset($db) && $db instanceof mysqli) {
            ame_audit_insert_core(
                $db,
                (int)($event['user_id'] ?? ($_SESSION['user_id'] ?? 0)),
                (string)($event['action'] ?? 'Activity'),
                (string)($event['module'] ?? 'System'),
                (string)($event['details'] ?? '')
            );
        }
    }
}

function ame_audit_insert_core(mysqli $db, int $uid, string $action, string $module, string $details): void
{
    $stmt = $db->prepare('INSERT INTO activity_logs (user_id, action, module, details) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isss', $uid, $action, $module, $details);
    $stmt->execute();
    $stmt->close();
}

function ame_audit_verify_row(array $row): bool
{
    $hash = (string)($row['row_hash'] ?? '');
    if ($hash === '') {
        return true;
    }
    $expect = ame_audit_row_hash($row, (string)($row['prev_hash'] ?? ''));
    return hash_equals($expect, $hash);
}

function ame_audit_date_range(string $preset, string $from = '', string $to = ''): array
{
    $today = date('Y-m-d');
    switch ($preset) {
        case 'yesterday':
            $d = date('Y-m-d', strtotime('-1 day'));
            return [$d, $d];
        case 'week':
            return [date('Y-m-d', strtotime('monday this week')), $today];
        case 'month':
            return [date('Y-m-01'), $today];
        case 'custom':
            $a = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-01');
            $b = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : $today;
            return [$a, $b];
        case 'today':
        default:
            return [$today, $today];
    }
}

function ame_audit_fetch(mysqli $db, array $f): array
{
    $cols = ame_audit_table_columns($db);
    $user = trim((string)($f['user'] ?? ''));
    $module = trim((string)($f['module'] ?? ''));
    $action = trim((string)($f['action'] ?? ''));
    $q = trim((string)($f['q'] ?? ''));
    [$from, $to] = ame_audit_date_range((string)($f['range'] ?? 'today'), (string)($f['from'] ?? ''), (string)($f['to'] ?? ''));

    $sql = 'SELECT l.*, u.username AS user_name, u.role AS user_role
            FROM activity_logs l
            LEFT JOIN users u ON u.id = l.user_id
            WHERE DATE(l.created_at) BETWEEN ? AND ?';
    $types = 'ss';
    $params = [$from, $to];

    if ($user !== '') {
        if (isset($cols['username'])) {
            $sql .= ' AND (u.username = ? OR l.username = ?)';
            $types .= 'ss';
            $params[] = $user;
            $params[] = $user;
        } else {
            $sql .= ' AND u.username = ?';
            $types .= 's';
            $params[] = $user;
        }
    }
    if ($module !== '') {
        $sql .= ' AND (l.module LIKE ?';
        $types .= 's';
        $params[] = '%' . $module . '%';
        if (isset($cols['action_type'])) {
            // also match normalized module stored in details prefix — keep simple
        }
        $sql .= ')';
    }
    if ($action !== '') {
        if (isset($cols['action_type'])) {
            $sql .= ' AND (l.action_type = ? OR l.action LIKE ?)';
            $types .= 'ss';
            $params[] = $action;
            $params[] = '%' . $action . '%';
        } else {
            $sql .= ' AND l.action LIKE ?';
            $types .= 's';
            $params[] = '%' . $action . '%';
        }
    }
    if ($q !== '') {
        $sql .= ' AND (l.details LIKE ? OR l.action LIKE ? OR l.module LIKE ?';
        $types .= 'sss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        if (isset($cols['reference'])) {
            $sql .= ' OR l.reference LIKE ? OR l.product_name LIKE ? OR l.party_name LIKE ?';
            $types .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ')';
    }

    $sql .= ' ORDER BY l.id DESC LIMIT 400';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return ['rows' => [], 'from' => $from, 'to' => $to];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return ['rows' => $rows, 'from' => $from, 'to' => $to];
}

function ame_audit_label_action(string $type, string $fallback): string
{
    $bn = [
        'add' => 'যোগ / তৈরি',
        'edit' => 'সম্পাদনা',
        'delete' => 'মুছে ফেলা',
        'view' => 'দেখা',
        'approve' => 'অনুমোদন',
        'reject' => 'প্রত্যাখ্যান',
        'print' => 'প্রিন্ট',
        'export' => 'এক্সপোর্ট',
        'payment' => 'পেমেন্ট',
        'login' => 'লগইন',
        'logout' => 'লগআউট',
        'login_fail' => 'ব্যর্থ লগইন',
        'password' => 'পাসওয়ার্ড',
        'permission' => 'অনুমতি',
        'other' => 'অন্যান্য',
    ];
    return $bn[$type] ?? ($fallback !== '' ? $fallback : 'কাজ');
}

function ame_audit_money($n): string
{
    if ($n === null || $n === '') {
        return '';
    }
    return '৳' . number_format((float)$n, 0);
}
