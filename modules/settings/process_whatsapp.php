<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/whatsapp_functions.php';
admin_only();
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: whatsapp_config.php');
    exit();
}

ensure_whatsapp_schema($db);
$api_url = trim((string)($_POST['api_url'] ?? 'https://graph.facebook.com/v21.0'));
$phone_number_id = trim((string)($_POST['phone_number_id'] ?? ''));
$access_token = trim((string)($_POST['access_token'] ?? ''));
$status = strtolower(trim((string)($_POST['status'] ?? 'inactive'))) === 'active' ? 'active' : 'inactive';

if ($api_url === '' || !preg_match('#^https?://#i', $api_url)) {
    $api_url = 'https://graph.facebook.com/v21.0';
}

ame_whatsapp_save($db, $api_url, $phone_number_id, $access_token, $status);

try {
    log_activity('save', 'whatsapp_settings', 'WhatsApp OTP gateway updated');
} catch (Throwable $e) {
}

header('Location: whatsapp_config.php?saved=1');
exit();
