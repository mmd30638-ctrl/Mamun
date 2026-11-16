<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/sms_functions.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-SMS-Token');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '') {
    $hdr = (string)($_SERVER['HTTP_X_SMS_TOKEN'] ?? '');
    $token = $hdr;
}
if ($token === '' && !empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(\S+)/i', (string)$_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $token = $m[1];
}

if (!ame_sms_token_ok($token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'bad_token'], JSON_UNESCAPED_UNICODE);
    exit;
}

ensure_sms_sim_schema($db);
$action = strtolower((string)($_GET['action'] ?? $_POST['action'] ?? 'pull'));

$actor = ame_sms_sim_from_token($db, $token);
$actorId = (int)($actor['id'] ?? 0);
$actorLegacy = $actor ? (bool)$actor['is_default'] : true;

if ($action === 'ping') {
    if (function_exists('ame_sms_touch_device')) {
        ame_sms_touch_device($db, $actorId);
    }
    $sms = ame_sms_settings($db);
    echo json_encode([
        'ok' => true,
        'counts' => ame_sms_outbox_counts($db, $actorId, $actorLegacy),
        'gateway_phone' => $actor ? (string)$actor['phone'] : ame_sms_gateway_phone($sms),
        'sim' => $actor ? [
            'id' => (int)$actor['id'],
            'label' => (string)$actor['label'],
            'phone' => (string)$actor['phone'],
            'slot' => (int)$actor['slot'],
        ] : null,
        'connected' => $actor ? ame_sms_sim_connected($actor) : ame_sms_device_connected($sms),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'ack') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $st = strtolower((string)($_POST['status'] ?? $_GET['status'] ?? 'sent'));
    $note = trim((string)($_POST['note'] ?? $_GET['note'] ?? 'sim_phone'));
    $own = $db->prepare('SELECT sim_id FROM sms_outbox WHERE id=? LIMIT 1');
    $owned = false;
    if ($own) {
        $own->bind_param('i', $id);
        $own->execute();
        $ownRow = $own->get_result()->fetch_assoc();
        $own->close();
        $rowSim = (int)($ownRow['sim_id'] ?? -1);
        $owned = $ownRow && (
            $actorId < 1
            || $rowSim === $actorId
            || ($actorLegacy && $rowSim === 0)
        );
    }
    if (!$owned) {
        echo json_encode(['ok' => false, 'id' => $id], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ok = ame_sms_outbox_ack($db, $id, $st, $note);
    if ($ok && in_array($st, ['sent', 'failed'], true)) {
        $row = null;
        $q = $db->prepare('SELECT mobile, message, purpose FROM sms_outbox WHERE id=?');
        if ($q) {
            $q->bind_param('i', $id);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $q->close();
        }
        if ($row) {
            log_sms_attempt($db, (string)$row['mobile'], (string)$row['message'], (string)$row['purpose'], $st, 'সিম ফোন #' . $id . ' ' . $note);
        }
    }
    echo json_encode(['ok' => $ok, 'id' => $id, 'status' => $st], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = (int)($_GET['limit'] ?? 5);
$messages = ame_sms_outbox_pull($db, $limit, $actorId, $actorLegacy);
if ($actor) {
    foreach ($messages as &$message) {
        $message['sim_label'] = (string)$actor['label'];
        $message['sim_phone'] = (string)$actor['phone'];
        $message['sim_slot'] = (int)$actor['slot'];
    }
    unset($message);
}
echo json_encode([
    'ok' => true,
    'messages' => $messages,
    'counts' => ame_sms_outbox_counts($db, $actorId, $actorLegacy),
], JSON_UNESCAPED_UNICODE);
exit;
