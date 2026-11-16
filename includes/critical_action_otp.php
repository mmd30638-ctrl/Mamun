<?php
require_once __DIR__ . '/password_reset_otp.php';

function ensure_critical_action_otp_schema($db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS critical_action_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action_key VARCHAR(40) NOT NULL,
        otp_hash VARCHAR(64) NOT NULL,
        channel VARCHAR(20) NOT NULL,
        destination VARCHAR(120) NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        used TINYINT(1) NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_user_action (user_id, action_key, used, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * @return array{channel:string,destination:string,label:string}|null
 */
function ame_user_otp_contact(array $user, bool $prefer_whatsapp = true): ?array
{
    $whatsapp = trim((string)($user['whatsapp'] ?? ''));
    $phone = trim((string)($user['phone'] ?? ''));
    global $db;

    if ($prefer_whatsapp) {
        $wa_num = $whatsapp !== '' ? $whatsapp : $phone;
        if ($wa_num !== '') {
            return [
                'channel' => 'whatsapp',
                'destination' => $wa_num,
                'label' => ame_mask_phone($wa_num),
            ];
        }
    }

    if ($phone !== '') {
        return [
            'channel' => 'sms',
            'destination' => $phone,
            'label' => ame_mask_phone($phone),
        ];
    }

    $email = trim((string)($user['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'channel' => 'email',
            'destination' => $email,
            'label' => ame_mask_email($email),
        ];
    }

    return null;
}

function ame_invalidate_critical_otps($db, int $user_id, string $action_key): void
{
    $stmt = $db->prepare('UPDATE critical_action_otps SET used = 1 WHERE user_id = ? AND action_key = ? AND used = 0');
    $stmt->bind_param('is', $user_id, $action_key);
    $stmt->execute();
    $stmt->close();
}

function ame_too_many_critical_otps($db, int $user_id, string $action_key, int $max = 5, int $window_minutes = 15): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) c FROM critical_action_otps
         WHERE user_id = ? AND action_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
    );
    $stmt->bind_param('isi', $user_id, $action_key, $window_minutes);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count >= $max;
}

/**
 * @return array{ok:bool,mock:bool,otp:?string,message:string,mask:string}
 */
function ame_send_critical_action_otp($db, int $user_id, string $action_key, string $action_label): array
{
    ensure_critical_action_otp_schema($db);
    ensure_user_schema($db);

    $user = ame_reset_user_by_id($db, $user_id);
    if (!$user) {
        return ['ok' => false, 'mock' => false, 'otp' => null, 'message' => 'ইউজার পাওয়া যায়নি।', 'mask' => ''];
    }

    $contact = ame_user_otp_contact($user, true);
    if (!$contact) {
        return [
            'ok' => false,
            'mock' => false,
            'otp' => null,
            'message' => 'প্রোফাইলে WhatsApp বা মোবাইল নম্বর যোগ করুন।',
            'mask' => '',
        ];
    }

    if (ame_too_many_critical_otps($db, $user_id, $action_key)) {
        return [
            'ok' => false,
            'mock' => false,
            'otp' => null,
            'message' => 'অনেকবার OTP চাওয়া হয়েছে। ১৫ মিনিট পর আবার চেষ্টা করুন।',
            'mask' => '',
        ];
    }

    $otp = ame_generate_otp_code();
    $otp_hash = hash('sha256', $otp);
    $channel = $contact['channel'];
    $destination = $contact['destination'];
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $shop_name = function_exists('shop_name') ? shop_name() : 'সিস্টেম';
    $mock = ame_otp_channel_is_mock($channel);

    ame_invalidate_critical_otps($db, $user_id, $action_key);

    $stmt = $db->prepare(
        'INSERT INTO critical_action_otps (user_id, action_key, otp_hash, channel, destination, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return ['ok' => false, 'mock' => false, 'otp' => null, 'message' => 'সিস্টেম ত্রুটি।', 'mask' => ''];
    }
    $stmt->bind_param('isssss', $user_id, $action_key, $otp_hash, $channel, $destination, $expires);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'mock' => false, 'otp' => null, 'message' => 'OTP সেভ হয়নি।', 'mask' => ''];
    }
    $stmt->close();

    $message = ame_sms_text('otp', ['label' => $action_label, 'code' => $otp]);
    $delivered = false;

    if ($channel === 'email') {
        $subject = $action_label . ' OTP — ' . $shop_name;
        $body = '<p style="font-family:Segoe UI,Arial,sans-serif;">' . htmlspecialchars($action_label) . ' এর জন্য OTP: <b style="font-size:24px;letter-spacing:6px;">' . htmlspecialchars($otp) . '</b></p>';
        $delivered = (bool)send_simple_email($destination, $subject, $body);
    } elseif ($channel === 'whatsapp') {
        $delivered = (bool)send_whatsapp_message($destination, $message, 'critical_action');
    } elseif ($channel === 'sms') {
        $delivered = (bool)send_sms($destination, $message, 'critical_action');
    }

    if ($mock) {
        if (function_exists('ame_is_production') && ame_is_production()) {
            return [
                'ok' => false,
                'mock' => false,
                'otp' => null,
                'message' => 'OTP গেটওয়ে কনফিগার করা হয়নি। সেটিংস → SMS/WhatsApp সেটআপ করুন।',
                'mask' => '',
            ];
        }
        return [
            'ok' => true,
            'mock' => true,
            'otp' => $otp,
            'message' => 'গেটওয়ে সেটআপ নেই — নিচে টেস্ট OTP দেখানো হচ্ছে। (শুধু development মোড)',
            'mask' => $contact['label'],
        ];
    }

    $via = $channel === 'whatsapp' ? 'WhatsApp' : ($channel === 'sms' ? 'SMS' : 'ইমেইল');
    return [
        'ok' => $delivered,
        'mock' => false,
        'otp' => null,
        'message' => $delivered ? "{$via}-এ OTP পাঠানো হয়েছে।" : 'OTP পাঠানো যায়নি। সেটিংস যাচাই করুন।',
        'mask' => $contact['label'],
    ];
}

function ame_verify_critical_action_otp($db, int $user_id, string $action_key, string $otp): ?array
{
    ensure_critical_action_otp_schema($db);
    $otp = preg_replace('/[^0-9]/', '', $otp);
    if (strlen($otp) !== 6) {
        return null;
    }
    $otp_hash = hash('sha256', $otp);
    $stmt = $db->prepare(
        'SELECT * FROM critical_action_otps
         WHERE user_id = ? AND action_key = ? AND used = 0 AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->bind_param('is', $user_id, $action_key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    if (!hash_equals((string)$row['otp_hash'], $otp_hash)) {
        $inc = $db->prepare('UPDATE critical_action_otps SET attempts = attempts + 1 WHERE id = ?');
        $inc->bind_param('i', $row['id']);
        $inc->execute();
        $inc->close();
        if ((int)$row['attempts'] + 1 >= 5) {
            $ban = $db->prepare('UPDATE critical_action_otps SET used = 1 WHERE id = ?');
            $ban->bind_param('i', $row['id']);
            $ban->execute();
            $ban->close();
        }
        return null;
    }

    return $row;
}

function ame_mark_critical_otp_used($db, int $otp_id): void
{
    $stmt = $db->prepare('UPDATE critical_action_otps SET used = 1 WHERE id = ?');
    $stmt->bind_param('i', $otp_id);
    $stmt->execute();
    $stmt->close();
}

function ame_validate_bd_phone(string $phone): bool
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) === 11 && $digits[0] === '0' && $digits[1] === '1') {
        return true;
    }
    if (strlen($digits) === 10 && $digits[0] === '1') {
        return true;
    }
    if (strlen($digits) === 13 && str_starts_with($digits, '8801')) {
        return true;
    }
    return false;
}

function ame_normalize_bd_phone_display(string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) === 10 && $digits[0] === '1') {
        return '0' . $digits;
    }
    if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
        return '0' . substr($digits, 2);
    }
    return $digits;
}

function ame_save_user_whatsapp($db, int $user_id, string $whatsapp): bool
{
    ensure_user_schema($db);
    $whatsapp = ame_normalize_bd_phone_display(trim($whatsapp));
    if (!ame_validate_bd_phone($whatsapp)) {
        return false;
    }
    $stmt = $db->prepare('UPDATE users SET whatsapp = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $whatsapp, $user_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
