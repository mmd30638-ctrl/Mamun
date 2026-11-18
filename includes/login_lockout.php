<?php
/**
 * Server-side login throttle. Session counters are not enough —
 * clearing the cookie must not reset the lock.
 */

const AME_LOGIN_WINDOW_SECONDS = 900;
const AME_LOGIN_MAX_PER_USERNAME = 7;
const AME_LOGIN_MAX_PER_IP = 18;
const AME_LOGIN_LOCK_SECONDS = 900;

function ame_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '0.0.0.0';
}

function ame_ensure_login_lockout_schema(mysqli $db): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;

    $db->query(
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(190) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            success TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_time (username, attempted_at),
            KEY idx_ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $cols = [];
    $res = @$db->query("SHOW COLUMNS FROM users");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    if ($cols && !in_array('locked_until', $cols, true)) {
        $db->query(
            'ALTER TABLE users ADD COLUMN locked_until DATETIME NULL DEFAULT NULL'
        );
    }
}

function ame_login_count_failures(mysqli $db, string $field, string $value): int
{
    if (!in_array($field, ['ip', 'username'], true)) {
        return 0;
    }
    $sql = "SELECT COUNT(*) AS c FROM login_attempts
            WHERE success = 0 AND {$field} = ?
              AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $window = AME_LOGIN_WINDOW_SECONDS;
    $stmt->bind_param('si', $value, $window);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0);
}

function ame_login_is_user_locked(mysqli $db, string $username): bool
{
    if ($username === '') {
        return false;
    }
    $stmt = $db->prepare(
        'SELECT locked_until FROM users WHERE username = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['locked_until'])) {
        return false;
    }
    return strtotime((string)$row['locked_until']) > time();
}

function ame_login_guard(mysqli $db, string $username): ?string
{
    ame_ensure_login_lockout_schema($db);
    $ip = ame_client_ip();

    if (ame_login_count_failures($db, 'ip', $ip) >= AME_LOGIN_MAX_PER_IP) {
        return 'এই নেটওয়ার্ক থেকে অনেকবার ভুল চেষ্টা হয়েছে। ১৫ মিনিট পর আবার চেষ্টা করুন।';
    }

    if ($username !== '' && ame_login_is_user_locked($db, $username)) {
        return 'এই একাউন্ট কিছুক্ষণের জন্য বন্ধ আছে। ১৫ মিনিট পর আবার চেষ্টা করুন।';
    }

    if ($username !== '' && ame_login_count_failures($db, 'username', $username) >= AME_LOGIN_MAX_PER_USERNAME) {
        return 'অনেকবার ভুল পাসওয়ার্ড দেওয়া হয়েছে। ১৫ মিনিট পর আবার চেষ্টা করুন।';
    }

    return null;
}

function ame_login_record(mysqli $db, string $username, bool $success): void
{
    ame_ensure_login_lockout_schema($db);
    $ip = ame_client_ip();
    $ok = $success ? 1 : 0;
    $stmt = $db->prepare(
        'INSERT INTO login_attempts (username, ip, success) VALUES (?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('ssi', $username, $ip, $ok);
        $stmt->execute();
        $stmt->close();
    }

    try {
        if (function_exists('log_activity')) {
            $uid = 0;
            $look = $db->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            if ($look) {
                $look->bind_param('s', $username);
                $look->execute();
                $got = $look->get_result()->fetch_assoc();
                $look->close();
                $uid = (int)($got['id'] ?? 0);
            }
            $prev = $_SESSION['user_id'] ?? null;
            $prevName = $_SESSION['username'] ?? null;
            if ($uid > 0) {
                $_SESSION['user_id'] = $uid;
                $_SESSION['username'] = $username;
            }
            log_activity(
                $success ? 'Login' : 'Failed Login',
                'Security',
                $success
                    ? ('সফল লগইন · ' . $username)
                    : ('ব্যর্থ লগইন · ' . ($username !== '' ? $username : 'অজানা')),
                [
                    'user_id' => $uid,
                    'username' => $username,
                    'action_type' => $success ? 'login' : 'login_fail',
                    'entity_type' => 'user',
                    'entity_id' => $uid > 0 ? (string)$uid : $username,
                    'reference' => $username,
                ]
            );
            if ($prev === null) {
                unset($_SESSION['user_id']);
            } else {
                $_SESSION['user_id'] = $prev;
            }
            if ($prevName === null) {
                unset($_SESSION['username']);
            } else {
                $_SESSION['username'] = $prevName;
            }
        }
    } catch (Throwable $e) {
        error_log('login audit skipped: ' . $e->getMessage());
    }

    if ($success && $username !== '') {
        $clear = $db->prepare(
            'UPDATE users SET locked_until = NULL WHERE username = ? LIMIT 1'
        );
        if ($clear) {
            $clear->bind_param('s', $username);
            $clear->execute();
            $clear->close();
        }
        return;
    }

    if (!$success && $username !== ''
        && ame_login_count_failures($db, 'username', $username) >= AME_LOGIN_MAX_PER_USERNAME
    ) {
        $lock = $db->prepare(
            'UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND)
             WHERE username = ? LIMIT 1'
        );
        if ($lock) {
            $secs = AME_LOGIN_LOCK_SECONDS;
            $lock->bind_param('is', $secs, $username);
            $lock->execute();
            $lock->close();
        }
    }
}
