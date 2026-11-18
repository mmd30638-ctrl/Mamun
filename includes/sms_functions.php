<?php
require_once __DIR__ . '/sms_log_functions.php';
require_once __DIR__ . '/secret_store.php';
require_once __DIR__ . '/sms_sim.php';
require_once __DIR__ . '/sms_templates.php';

function ame_sms_defaults(): array
{
    return [
        'api_url' => '',
        'api_key' => '',
        'sender_id' => '',
        'status' => 'inactive',
        'channel' => 'sim',
        'gateway_phone' => '',
        'device_last_seen' => '',
        'smsgate_url' => 'https://api.sms-gate.app/3rdparty/v1/message',
        'smsgate_user' => '',
        'smsgate_pass' => '',
        'device_token' => '',
        'key_source' => 'none',
        'has_key' => false,
    ];
}

function ame_sms_db_row($db): array
{
    $blank = ['api_url' => '', 'api_key' => '', 'sender_id' => '', 'status' => 'inactive', 'channel' => 'sim', 'gateway_phone' => '', 'device_last_seen' => ''];
    if (!($db instanceof mysqli)) {
        return $blank;
    }
    if (function_exists('ensure_sms_sim_schema')) {
        ensure_sms_sim_schema($db);
    }
    $table = @$db->query("SHOW TABLES LIKE 'sms_settings'");
    if (!$table || $table->num_rows === 0) {
        return $blank;
    }
    $res = @$db->query('SELECT api_url, api_key, sender_id, status, channel, gateway_phone, device_last_seen FROM sms_settings WHERE id = 1');
    if (!$res) {
        $res = @$db->query('SELECT api_url, api_key, sender_id, status, channel FROM sms_settings WHERE id = 1');
    }
    if (!$res) {
        $res = @$db->query('SELECT api_url, api_key, sender_id, status FROM sms_settings WHERE id = 1');
    }
    if ($res && ($row = $res->fetch_assoc())) {
        return array_merge($blank, $row);
    }
    return $blank;
}

function ame_sms_scrub_db_key($db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    @$db->query("UPDATE sms_settings SET api_key = '' WHERE id = 1 AND api_key <> ''");
}

function ame_sms_settings($db): array
{
    $out = ame_sms_defaults();
    $dbRow = ame_sms_db_row($db);
    $file = ame_secret_read('sms');

    $envUrl = ame_env_nonempty('SMS_API_URL');
    $envKey = ame_env_nonempty('SMS_API_KEY');
    $envSid = ame_env_nonempty('SMS_SENDER_ID');
    $envStatus = ame_env_nonempty('SMS_STATUS');

    $out['api_url'] = $envUrl !== '' ? $envUrl : (trim((string)($file['api_url'] ?? '')) ?: (string)$dbRow['api_url']);
    $out['sender_id'] = $envSid !== '' ? $envSid : (trim((string)($file['sender_id'] ?? '')) ?: (string)$dbRow['sender_id']);
    $out['status'] = $envStatus !== '' ? $envStatus : (trim((string)($file['status'] ?? '')) ?: (string)$dbRow['status']);

    $dbKey = trim((string)$dbRow['api_key']);
    $fileKey = trim((string)($file['api_key'] ?? ''));

    if ($envKey !== '') {
        $out['api_key'] = $envKey;
        $out['key_source'] = 'env';
        if ($dbKey !== '') {
            ame_sms_scrub_db_key($db);
        }
    } elseif ($fileKey !== '') {
        $out['api_key'] = $fileKey;
        $out['key_source'] = 'file';
        if ($dbKey !== '') {
            ame_sms_scrub_db_key($db);
        }
    } elseif ($dbKey !== '') {
        $out['api_key'] = $dbKey;
        $out['key_source'] = 'db';
        $migrated = array_merge($file, [
            'api_url' => $out['api_url'],
            'api_key' => $dbKey,
            'sender_id' => $out['sender_id'],
            'status' => $out['status'],
        ]);
        if (ame_secret_write('sms', $migrated)) {
            ame_sms_scrub_db_key($db);
            $out['key_source'] = 'file';
        }
    }

    $out['has_key'] = trim((string)$out['api_key']) !== '';
    $out['channel'] = ame_sms_channel([
        'channel' => $file['channel'] ?? ($dbRow['channel'] ?? 'sim'),
    ]);
    $out['smsgate_url'] = trim((string)($file['smsgate_url'] ?? '')) ?: 'https://api.sms-gate.app/3rdparty/v1/message';
    $out['smsgate_user'] = trim((string)($file['smsgate_user'] ?? ''));
    $out['smsgate_pass'] = trim((string)($file['smsgate_pass'] ?? ''));
    $out['device_token'] = trim((string)($file['device_token'] ?? ''));
    $out['gateway_phone'] = ame_sms_normalize_bd_mobile(
        trim((string)($file['gateway_phone'] ?? '')) ?: (string)($dbRow['gateway_phone'] ?? '')
    );
    $out['device_last_seen'] = trim((string)($file['device_last_seen'] ?? ''))
        ?: trim((string)($dbRow['device_last_seen'] ?? ''));
    $envCh = ame_env_nonempty('SMS_CHANNEL');
    if ($envCh !== '') {
        $out['channel'] = ame_sms_channel(['channel' => $envCh]);
    }
    return $out;
}

function ame_sms_save($db, string $url, string $key, string $sid, string $status, array $extra = []): bool
{
    $current = ame_sms_settings($db);
    if ($key === '') {
        $key = (string)$current['api_key'];
    }
    $status = $status === 'active' ? 'active' : 'inactive';
    $channel = ame_sms_channel(['channel' => $extra['channel'] ?? ($current['channel'] ?? 'sim')]);

    if (!($db instanceof mysqli)) {
        return false;
    }
    ensure_sms_sim_schema($db);
    $table = @$db->query("SHOW TABLES LIKE 'sms_settings'");
    if (!$table || $table->num_rows === 0) {
        $db->query("CREATE TABLE IF NOT EXISTS sms_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            api_url VARCHAR(255) NOT NULL DEFAULT '',
            api_key VARCHAR(255) NOT NULL DEFAULT '',
            sender_id VARCHAR(80) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'inactive',
            channel VARCHAR(20) NOT NULL DEFAULT 'sim'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    $check = $db->query('SELECT id FROM sms_settings WHERE id = 1');
    if (!$check || $check->num_rows === 0) {
        $db->query("INSERT INTO sms_settings (id, api_url, api_key, sender_id, status, channel) VALUES (1, '', '', '', 'inactive', 'sim')");
    }

    $emptyKey = '';
    $stmt = $db->prepare('UPDATE sms_settings SET api_url=?, api_key=?, sender_id=?, status=?, channel=? WHERE id=1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sssss', $url, $emptyKey, $sid, $status, $channel);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && array_key_exists('gateway_phone', $extra)) {
        $gp = ame_sms_normalize_bd_mobile((string)$extra['gateway_phone']);
        $gstmt = $db->prepare('UPDATE sms_settings SET gateway_phone=? WHERE id=1');
        if ($gstmt) {
            $gstmt->bind_param('s', $gp);
            $gstmt->execute();
            $gstmt->close();
        }
    }

    $payload = ame_sms_sim_secret();
    $payload['api_url'] = $url;
    $payload['api_key'] = $key;
    $payload['sender_id'] = $sid;
    $payload['status'] = $status;
    $payload['channel'] = $channel;
    if (array_key_exists('gateway_phone', $extra)) {
        $payload['gateway_phone'] = ame_sms_normalize_bd_mobile((string)$extra['gateway_phone']);
    }
    if (array_key_exists('smsgate_url', $extra)) {
        $payload['smsgate_url'] = trim((string)$extra['smsgate_url']);
    }
    if (array_key_exists('smsgate_user', $extra)) {
        $payload['smsgate_user'] = trim((string)$extra['smsgate_user']);
    }
    if (!empty($extra['smsgate_pass'])) {
        $payload['smsgate_pass'] = (string)$extra['smsgate_pass'];
    }
    if (empty($payload['device_token']) || !empty($extra['rotate_token'])) {
        $payload['device_token'] = bin2hex(random_bytes(16));
    }
    ame_secret_write('sms', $payload);
    return $ok;
}

function ame_sms_gateway_ready(array $sms): bool
{
    if (strtolower(trim((string)($sms['status'] ?? ''))) !== 'active') {
        return false;
    }
    $url = trim((string)($sms['api_url'] ?? ''));
    $key = trim((string)($sms['api_key'] ?? ''));
    if ($url === '' || $key === '' || !preg_match('#^https?://#i', $url)) {
        return false;
    }
    $hay = strtolower($url . ' ' . $key);
    foreach (['sms_gateway_url', 'example.com', 'your_api_key', 'placeholder', 'localhost'] as $fake) {
        if (str_contains($hay, $fake)) {
            return false;
        }
    }
    return true;
}

function send_sms($mobile, $message, $purpose = 'general', int $simId = 0)
{
    global $db;

    $mobile = preg_replace('/[^0-9]/', '', (string)$mobile);
    $message = trim((string)$message);

    if ($mobile === '' || $message === '') {
        log_sms_attempt($db, $mobile, $message, $purpose, 'failed', 'খালি মোবাইল বা মেসেজ');
        return false;
    }

    if (strlen($mobile) === 11) {
        $mobile = '88' . $mobile;
    }

    $sms = ame_sms_settings($db);

    if (ame_sms_channel($sms) === 'sim') {
        if (!ame_sms_sim_ready($sms)) {
            log_sms_attempt($db, $mobile, $message, $purpose, 'failed', 'সিম এসএমএস বন্ধ — সেটিংসে চালু করুন');
            return false;
        }
        if ($simId > 0) {
            $chosen = ame_sms_sim_get($db, $simId);
            if (!$chosen || $chosen['status'] !== 'active') {
                log_sms_attempt($db, $mobile, $message, $purpose, 'failed', 'পাঠানোর সিম বন্ধ বা নেই');
                return false;
            }
        }
        $qid = ame_sms_queue($db, $mobile, $message, $purpose, $simId);
        if ($qid < 1) {
            log_sms_attempt($db, $mobile, $message, $purpose, 'failed', 'সিম কিউতে জমা হয়নি');
            return false;
        }
        $simRow = $simId > 0 ? ame_sms_sim_get($db, $simId) : ame_sms_sim_default($db);
        $gate = ame_sms_smsgate_push($sms, $mobile, $message, (int)($simRow['slot'] ?? 0));
        if (!empty($gate['ok'])) {
            ame_sms_outbox_ack($db, $qid, 'sent', (string)$gate['note']);
            log_sms_attempt($db, $mobile, $message, $purpose, 'sent', 'সিম ক্লাউড #' . $qid . ' ' . $gate['note']);
            return true;
        }
        $note = $gate['note'] === 'smsgate_skip'
            ? 'সিম কিউ #' . $qid . ' — ফোনের সিম পেজ খোলা রাখুন'
            : 'সিম কিউ #' . $qid . ' — ক্লাউড পরে, ফোন পুল করবে (' . $gate['note'] . ')';
        log_sms_attempt($db, $mobile, $message, $purpose, 'queued', $note);
        return true;
    }

    if (!ame_sms_gateway_ready($sms)) {
        log_sms_attempt(
            $db,
            $mobile,
            $message,
            $purpose,
            'sent',
            'মক/লগ — গেটওয়ে বন্ধ বা ডেমো URL (সেটিংস → এসএমএস)'
        );
        return true;
    }

    $post_data = [
        'api_key' => $sms['api_key'],
        'sender_id' => $sms['sender_id'],
        'to' => $mobile,
        'message' => $message,
    ];

    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sms['api_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            log_sms_attempt($db, $mobile, $message, $purpose, 'failed', $curl_error ?: 'কোনো রেসপন্স আসেনি');
            return false;
        }

        log_sms_attempt($db, $mobile, $message, $purpose, 'sent', substr((string)$response, 0, 500));
        return $response;
    } catch (Throwable $e) {
        error_log('send_sms failed: ' . $e->getMessage());
        log_sms_attempt($db, $mobile, $message, $purpose, 'failed', 'সিস্টেম এরর');
        return false;
    }
}
