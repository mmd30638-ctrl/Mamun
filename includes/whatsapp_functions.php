<?php
require_once __DIR__ . '/sms_log_functions.php';
require_once __DIR__ . '/secret_store.php';

function ensure_whatsapp_schema($db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS whatsapp_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        api_url VARCHAR(255) NOT NULL DEFAULT 'https://graph.facebook.com/v21.0',
        access_token VARCHAR(500) NOT NULL DEFAULT '',
        phone_number_id VARCHAR(40) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'inactive',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $res = $db->query('SELECT id FROM whatsapp_settings WHERE id = 1');
    if ($res && $res->num_rows === 0) {
        $db->query("INSERT INTO whatsapp_settings (id, status) VALUES (1, 'inactive')");
    }
}

function ame_whatsapp_settings($db): array
{
    $defaults = [
        'api_url' => 'https://graph.facebook.com/v21.0',
        'access_token' => '',
        'phone_number_id' => '',
        'status' => 'inactive',
        'token_source' => 'none',
        'has_token' => false,
    ];
    if (!($db instanceof mysqli)) {
        return $defaults;
    }
    ensure_whatsapp_schema($db);
    $row = $defaults;
    $res = @$db->query('SELECT api_url, access_token, phone_number_id, status FROM whatsapp_settings WHERE id = 1');
    if ($res && ($found = $res->fetch_assoc())) {
        $row = array_merge($row, $found);
    }
    $file = ame_secret_read('whatsapp');
    $envUrl = ame_env_nonempty('WHATSAPP_API_URL');
    $envToken = ame_env_nonempty('WHATSAPP_ACCESS_TOKEN');
    $envPhone = ame_env_nonempty('WHATSAPP_PHONE_NUMBER_ID');
    $envStatus = ame_env_nonempty('WHATSAPP_STATUS');

    $out = $defaults;
    $out['api_url'] = $envUrl !== '' ? $envUrl : (trim((string)($file['api_url'] ?? '')) ?: (string)$row['api_url']);
    $out['phone_number_id'] = $envPhone !== '' ? $envPhone : (trim((string)($file['phone_number_id'] ?? '')) ?: (string)$row['phone_number_id']);
    $out['status'] = $envStatus !== '' ? $envStatus : (trim((string)($file['status'] ?? '')) ?: (string)$row['status']);

    $dbToken = trim((string)($row['access_token'] ?? ''));
    $fileToken = trim((string)($file['access_token'] ?? ''));
    if ($envToken !== '') {
        $out['access_token'] = $envToken;
        $out['token_source'] = 'env';
        if ($dbToken !== '') {
            @$db->query("UPDATE whatsapp_settings SET access_token = '' WHERE id = 1 AND access_token <> ''");
        }
    } elseif ($fileToken !== '') {
        $out['access_token'] = $fileToken;
        $out['token_source'] = 'file';
        if ($dbToken !== '') {
            @$db->query("UPDATE whatsapp_settings SET access_token = '' WHERE id = 1 AND access_token <> ''");
        }
    } elseif ($dbToken !== '') {
        $out['access_token'] = $dbToken;
        $out['token_source'] = 'db';
        if (ame_secret_write('whatsapp', [
            'api_url' => $out['api_url'],
            'access_token' => $dbToken,
            'phone_number_id' => $out['phone_number_id'],
            'status' => $out['status'],
        ])) {
            @$db->query("UPDATE whatsapp_settings SET access_token = '' WHERE id = 1 AND access_token <> ''");
            $out['token_source'] = 'file';
        }
    }
    $out['has_token'] = trim((string)$out['access_token']) !== '';
    return $out;
}

function ame_whatsapp_save($db, string $api_url, string $phone_number_id, string $access_token, string $status): bool
{
    $current = ame_whatsapp_settings($db);
    if ($access_token === '') {
        $access_token = (string)$current['access_token'];
    }
    $status = $status === 'active' ? 'active' : 'inactive';
    if ($api_url === '' || !preg_match('#^https?://#i', $api_url)) {
        $api_url = 'https://graph.facebook.com/v21.0';
    }
    ensure_whatsapp_schema($db);
    $empty = '';
    $stmt = $db->prepare('UPDATE whatsapp_settings SET api_url = ?, phone_number_id = ?, access_token = ?, status = ? WHERE id = 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssss', $api_url, $phone_number_id, $empty, $status);
    $ok = $stmt->execute();
    $stmt->close();
    ame_secret_write('whatsapp', [
        'api_url' => $api_url,
        'access_token' => $access_token,
        'phone_number_id' => $phone_number_id,
        'status' => $status,
    ]);
    return $ok;
}

function ame_whatsapp_ready(array $wa): bool
{
    if (strtolower(trim((string)($wa['status'] ?? ''))) !== 'active') {
        return false;
    }
    $token = trim((string)($wa['access_token'] ?? ''));
    $phone_id = trim((string)($wa['phone_number_id'] ?? ''));
    $url = trim((string)($wa['api_url'] ?? ''));
    if ($token === '' || $phone_id === '' || $url === '') {
        return false;
    }
    foreach (['your_token', 'example', 'placeholder'] as $fake) {
        if (stripos($token . $phone_id, $fake) !== false) {
            return false;
        }
    }
    return true;
}

function ame_normalize_bd_mobile(string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) === 11 && $digits[0] === '0') {
        return '88' . $digits;
    }
    if (strlen($digits) === 10 && $digits[0] === '1') {
        return '88' . $digits;
    }
    return $digits;
}

function send_whatsapp_message($mobile, $message, $purpose = 'general')
{
    global $db;

    $mobile = ame_normalize_bd_mobile((string)$mobile);
    $message = trim((string)$message);
    if ($mobile === '' || $message === '') {
        log_sms_attempt($db, $mobile, $message, 'whatsapp:' . $purpose, 'failed', 'খালি মোবাইল বা মেসেজ');
        return false;
    }

    $wa = ame_whatsapp_settings($db);
    if (!ame_whatsapp_ready($wa)) {
        log_sms_attempt(
            $db,
            $mobile,
            $message,
            'whatsapp:' . $purpose,
            'sent',
            'মক/লগ — WhatsApp গেটওয়ে বন্ধ (সেটিংস → WhatsApp)'
        );
        return true;
    }

    $base = rtrim((string)$wa['api_url'], '/');
    $endpoint = $base . '/' . rawurlencode((string)$wa['phone_number_id']) . '/messages';
    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $mobile,
        'type' => 'text',
        'text' => ['preview_url' => false, 'body' => $message],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $wa['access_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http >= 200 && $http < 300) {
        log_sms_attempt($db, $mobile, $message, 'whatsapp:' . $purpose, 'sent', substr((string)$response, 0, 500));
        return true;
    }

    log_sms_attempt(
        $db,
        $mobile,
        $message,
        'whatsapp:' . $purpose,
        'failed',
        'HTTP ' . $http . ' — ' . ($curl_error ?: substr((string)$response, 0, 300))
    );
    return false;
}
