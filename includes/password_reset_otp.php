<?php
require_once __DIR__ . '/mail_functions.php';
require_once __DIR__ . '/whatsapp_functions.php';
require_once __DIR__ . '/sms_functions.php';

function ensure_password_reset_schema($db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_user_token (user_id, used, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS password_reset_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        otp_hash VARCHAR(64) NOT NULL,
        channel VARCHAR(20) NOT NULL,
        destination VARCHAR(120) NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        used TINYINT(1) NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_user_otp (user_id, used, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ame_user_is_active(array $user): bool
{
    $status = $user['status'] ?? 1;
    if (is_numeric($status)) {
        return (int)$status === 1;
    }
    $status = strtolower(trim((string)$status));
    if ($status === '' || $status === 'active' || $status === '1') {
        return true;
    }
    return !in_array($status, ['inactive', 'disabled', '0', 'off'], true);
}

function ame_mask_email(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '***';
    }
    [$local, $domain] = explode('@', $email, 2);
    $local_mask = mb_substr($local, 0, 2, 'UTF-8') . str_repeat('*', max(2, mb_strlen($local, 'UTF-8') - 2));
    return $local_mask . '@' . $domain;
}

function ame_mask_phone(string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) < 6) {
        return '***';
    }
    return substr($digits, 0, 3) . '****' . substr($digits, -3);
}

function ame_reset_rate_limit_session(): bool
{
    if (!isset($_SESSION['reset_attempts'])) {
        $_SESSION['reset_attempts'] = [];
    }
    $now = time();
    $_SESSION['reset_attempts'] = array_values(array_filter(
        $_SESSION['reset_attempts'],
        static fn($t) => $t > $now - 300
    ));
    if (count($_SESSION['reset_attempts']) >= 8) {
        return true;
    }
    $_SESSION['reset_attempts'][] = $now;
    return false;
}

function ame_reset_phone_variants(string $phone): array
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if ($digits === '') {
        return [];
    }
    $variants = [$digits];
    if (strlen($digits) === 11 && $digits[0] === '0') {
        $variants[] = '88' . substr($digits, 1);
        $variants[] = substr($digits, 1);
    }
    if (strlen($digits) === 10 && $digits[0] === '1') {
        $variants[] = '0' . $digits;
        $variants[] = '88' . $digits;
    }
    if (strlen($digits) === 13 && str_starts_with($digits, '88')) {
        $variants[] = '0' . substr($digits, 2);
    }
    return array_values(array_unique($variants));
}

function ame_reset_find_user($db, string $login): ?array
{
    if (!($db instanceof mysqli)) {
        return null;
    }
    ensure_user_schema($db);

    $login = trim($login);
    if ($login === '') {
        return null;
    }

    $cols = [];
    $res = @$db->query('SHOW COLUMNS FROM users');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    $has_email = in_array('email', $cols, true);
    $has_phone = in_array('phone', $cols, true);
    $has_whatsapp = in_array('whatsapp', $cols, true);

    $select = 'SELECT id, username, status';
    $select .= $has_email ? ', email' : ", '' AS email";
    $select .= $has_phone ? ', phone' : ", '' AS phone";
    $select .= $has_whatsapp ? ', whatsapp' : ", '' AS whatsapp";
    $select .= ' FROM users WHERE username = ?';
    $types = 's';
    $params = [$login];

    if ($has_email && filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $select .= ' OR email = ?';
        $types .= 's';
        $params[] = $login;
    }

    if ($has_phone) {
        foreach (ame_reset_phone_variants($login) as $variant) {
            $select .= ' OR phone = ?';
            $types .= 's';
            $params[] = $variant;
        }
    }

    if ($has_whatsapp) {
        foreach (ame_reset_phone_variants($login) as $variant) {
            $select .= ' OR whatsapp = ?';
            $types .= 's';
            $params[] = $variant;
        }
    }

    $select .= ' LIMIT 1';
    $stmt = $db->prepare($select);
    if (!$stmt) {
        error_log('ame_reset_find_user prepare failed: ' . $db->error);
        return null;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function ame_user_reset_channels(array $user): array
{
    global $db;
    $channels = [];
    $email = trim((string)($user['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $channels['email'] = [
            'label' => 'Gmail / ইমেইল',
            'hint' => ame_mask_email($email),
            'destination' => $email,
        ];
    }
    $phone = trim((string)($user['phone'] ?? ''));
    if ($phone !== '') {
        $channels['sms'] = [
            'label' => 'মোবাইল SMS',
            'hint' => ame_mask_phone($phone),
            'destination' => $phone,
        ];
    }
    $whatsapp = trim((string)($user['whatsapp'] ?? ''));
    if ($whatsapp !== '') {
        $channels['whatsapp'] = [
            'label' => 'WhatsApp',
            'hint' => ame_mask_phone($whatsapp),
            'destination' => $whatsapp,
        ];
    } elseif ($phone !== '') {
        $channels['whatsapp'] = [
            'label' => 'WhatsApp (মোবাইল)',
            'hint' => ame_mask_phone($phone),
            'destination' => $phone,
        ];
    }
    return $channels;
}

function ame_otp_channel_is_mock(string $channel): bool
{
    global $db;
    if ($channel === 'email') {
        return !(defined('BREVO_API_KEY') && BREVO_API_KEY !== '');
    }
    if ($channel === 'whatsapp') {
        return !ame_whatsapp_ready(ame_whatsapp_settings($db));
    }
    if ($channel === 'sms') {
        return !ame_sms_gateway_ready(ame_sms_settings($db));
    }
    return true;
}

function ame_generate_otp_code(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function ame_too_many_otp_requests($db, int $user_id, int $max = 5, int $window_minutes = 15): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) c FROM password_reset_otps WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
    );
    $stmt->bind_param('ii', $user_id, $window_minutes);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count >= $max;
}

function ame_invalidate_active_otps($db, int $user_id): void
{
    $stmt = $db->prepare('UPDATE password_reset_otps SET used = 1 WHERE user_id = ? AND used = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * @return array{ok:bool,mock:bool,otp:?string,message:string}
 */
function ame_send_password_reset_otp($db, array $user, string $channel): array
{
    ensure_password_reset_schema($db);
    $channels = ame_user_reset_channels($user);
    if (!isset($channels[$channel])) {
        return ['ok' => false, 'mock' => false, 'otp' => null, 'message' => 'মাধ্যম পাওয়া যায়নি।'];
    }

    $otp = ame_generate_otp_code();
    $otp_hash = hash('sha256', $otp);
    $destination = $channels[$channel]['destination'];
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $shop_name = function_exists('shop_name') ? shop_name() : 'সিস্টেম';
    $mock = ame_otp_channel_is_mock($channel);

    ame_invalidate_active_otps($db, (int)$user['id']);

    $stmt = $db->prepare(
        'INSERT INTO password_reset_otps (user_id, otp_hash, channel, destination, expires_at) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        error_log('password_reset_otps insert prepare failed: ' . $db->error);
        return ['ok' => false, 'mock' => false, 'otp' => null, 'message' => 'সিস্টেম ত্রুটি। পরে চেষ্টা করুন।'];
    }
    $uid = (int)$user['id'];
    $stmt->bind_param('issss', $uid, $otp_hash, $channel, $destination, $expires);
    if (!$stmt->execute()) {
        error_log('password_reset_otps insert failed: ' . $stmt->error);
        $stmt->close();
        return ['ok' => false, 'mock' => false, 'otp' => null, 'message' => 'OTP সেভ হয়নি।'];
    }
    $stmt->close();

    $message = ame_sms_text('otp', ['label' => 'পাসওয়ার্ড রিসেট', 'code' => $otp]);
    $delivered = false;

    if ($channel === 'email') {
        $subject = 'পাসওয়ার্ড রিসেট OTP — ' . $shop_name;
        $body = '
            <div style="font-family:Segoe UI,Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#f8fafc;border-radius:16px;">
                <h2 style="color:#004D40;margin:0 0 12px;">পাসওয়ার্ড রিসেট কোড</h2>
                <p style="color:#334155;line-height:1.6;">প্রিয় ' . htmlspecialchars((string)$user['username']) . ',</p>
                <p style="color:#334155;line-height:1.6;">আপনার একাউন্টে পাসওয়ার্ড রিসেটের জন্য নিচের OTP ব্যবহার করুন:</p>
                <p style="text-align:center;margin:24px 0;">
                    <span style="display:inline-block;background:#004D40;color:#fff;font-size:28px;font-weight:800;letter-spacing:8px;padding:14px 22px;border-radius:12px;">' . htmlspecialchars($otp) . '</span>
                </p>
                <p style="color:#64748b;font-size:13px;">মেয়াদ ১০ মিনিট। আপনি অনুরোধ না করলে এই ইমেইল উপেক্ষা করুন।</p>
            </div>';
        $delivered = (bool)send_simple_email($destination, $subject, $body);
    } elseif ($channel === 'whatsapp') {
        $delivered = (bool)send_whatsapp_message($destination, $message, 'password_reset');
    } elseif ($channel === 'sms') {
        $delivered = (bool)send_sms($destination, $message, 'password_reset');
    }

    if ($mock) {
        if (function_exists('ame_is_production') && ame_is_production()) {
            return [
                'ok' => false,
                'mock' => false,
                'otp' => null,
                'message' => 'OTP গেটওয়ে কনফিগার করা হয়নি। সেটিংস → SMS/WhatsApp/Brevo সেটআপ করুন অথবা অ্যাডমিনের সাথে যোগাযোগ করুন।',
            ];
        }
        return [
            'ok' => true,
            'mock' => true,
            'otp' => $otp,
            'message' => 'গেটওয়ে সেটআপ নেই — নিচে টেস্ট OTP দেখানো হচ্ছে। (শুধু development মোড)',
        ];
    }

    return [
        'ok' => $delivered,
        'mock' => false,
        'otp' => null,
        'message' => $delivered ? 'OTP পাঠানো হয়েছে।' : 'OTP পাঠানো যায়নি। সেটিংস যাচাই করুন।',
    ];
}

function ame_verify_password_reset_otp($db, int $user_id, string $otp, bool $lock = false): ?array
{
    ensure_password_reset_schema($db);
    $otp = preg_replace('/[^0-9]/', '', $otp);
    if (strlen($otp) !== 6) {
        return null;
    }
    $otp_hash = hash('sha256', $otp);
    $lock_clause = $lock ? ' FOR UPDATE' : '';
    $stmt = $db->prepare(
        "SELECT pro.*, u.username FROM password_reset_otps pro
         JOIN users u ON u.id = pro.user_id
         WHERE pro.user_id = ? AND pro.used = 0 AND pro.expires_at > NOW()
         ORDER BY pro.id DESC LIMIT 1" . $lock_clause
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    if (!hash_equals((string)$row['otp_hash'], $otp_hash)) {
        $inc = $db->prepare('UPDATE password_reset_otps SET attempts = attempts + 1 WHERE id = ?');
        $inc->bind_param('i', $row['id']);
        $inc->execute();
        $inc->close();
        if ((int)$row['attempts'] + 1 >= 5) {
            $ban = $db->prepare('UPDATE password_reset_otps SET used = 1 WHERE id = ?');
            $ban->bind_param('i', $row['id']);
            $ban->execute();
            $ban->close();
        }
        return null;
    }

    return $row;
}

function ame_mark_otp_used($db, int $otp_id): void
{
    $stmt = $db->prepare('UPDATE password_reset_otps SET used = 1 WHERE id = ?');
    $stmt->bind_param('i', $otp_id);
    $stmt->execute();
    $stmt->close();
}

function ame_clear_password_reset_session(): void
{
    unset(
        $_SESSION['reset_user_id'],
        $_SESSION['reset_channel'],
        $_SESSION['reset_destination_mask'],
        $_SESSION['reset_step'],
        $_SESSION['reset_verified'],
        $_SESSION['reset_otp_id'],
        $_SESSION['reset_username'],
        $_SESSION['reset_otp_preview'],
        $_SESSION['reset_mock_notice']
    );
}

function ame_reset_user_by_id($db, int $user_id): ?array
{
    if (!($db instanceof mysqli) || $user_id <= 0) {
        return null;
    }
    ensure_user_schema($db);
    $cols = [];
    $res = @$db->query('SHOW COLUMNS FROM users');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    $has_email = in_array('email', $cols, true);
    $has_phone = in_array('phone', $cols, true);
    $has_whatsapp = in_array('whatsapp', $cols, true);
    $select = 'SELECT id, username, status';
    $select .= $has_email ? ', email' : ", '' AS email";
    $select .= $has_phone ? ', phone' : ", '' AS phone";
    $select .= $has_whatsapp ? ', whatsapp' : ", '' AS whatsapp";
    $select .= ' FROM users WHERE id = ? LIMIT 1';
    $stmt = $db->prepare($select);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}
