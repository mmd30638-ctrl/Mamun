<?php
/**
 * SIM SMS: Hostinger cannot hold a physical SIM.
 * Messages queue here; the shop phone (with SIM) pulls and sends.
 */

function ensure_sms_sim_schema($db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $db->query("CREATE TABLE IF NOT EXISTS sms_outbox (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mobile VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        purpose VARCHAR(100) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        last_error VARCHAR(255) DEFAULT NULL,
        log_id INT NULL,
        claimed_at DATETIME NULL,
        queued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        sent_at DATETIME NULL,
        INDEX idx_sms_out_status (status, queued_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $simCol = @$db->query("SHOW COLUMNS FROM sms_outbox LIKE 'sim_id'");
    if ($simCol && $simCol->num_rows === 0) {
        @$db->query("ALTER TABLE sms_outbox ADD COLUMN sim_id INT NOT NULL DEFAULT 0");
        @$db->query("ALTER TABLE sms_outbox ADD INDEX idx_sms_out_sim (sim_id, status)");
    }

    $db->query("CREATE TABLE IF NOT EXISTS sms_sims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(80) NOT NULL,
        phone VARCHAR(20) NOT NULL DEFAULT '',
        slot TINYINT NOT NULL DEFAULT 1,
        token VARCHAR(64) NOT NULL,
        is_default TINYINT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        last_seen DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        UNIQUE KEY uq_sms_sim_token (token),
        KEY idx_sms_sim_live (status, is_default)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $col = @$db->query("SHOW COLUMNS FROM sms_settings LIKE 'channel'");
    if ($col && $col->num_rows === 0) {
        @$db->query("ALTER TABLE sms_settings ADD COLUMN channel VARCHAR(20) NOT NULL DEFAULT 'sim'");
    }
    foreach ([
        'gateway_phone' => "ALTER TABLE sms_settings ADD COLUMN gateway_phone VARCHAR(20) NOT NULL DEFAULT ''",
        'device_last_seen' => "ALTER TABLE sms_settings ADD COLUMN device_last_seen DATETIME NULL DEFAULT NULL",
    ] as $colName => $ddl) {
        $c = @$db->query("SHOW COLUMNS FROM sms_settings LIKE '{$colName}'");
        if ($c && $c->num_rows === 0) {
            @$db->query($ddl);
        }
    }
    $st = @$db->query("SHOW COLUMNS FROM sms_log LIKE 'status'");
    if ($st && ($row = $st->fetch_assoc())) {
        $type = strtolower((string)($row['Type'] ?? ''));
        if (str_starts_with($type, 'enum(')) {
            @$db->query("ALTER TABLE sms_log MODIFY status VARCHAR(20) NOT NULL DEFAULT 'sent'");
        }
    }
}

function ame_sms_public_base(): string
{
    $https = function_exists('ame_is_https') && ame_is_https();
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }
    return ($https ? 'https' : 'http') . '://' . $host;
}

function ame_sms_e164(string $digits): string
{
    $d = preg_replace('/\D+/', '', $digits) ?? '';
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '880')) {
        return '+' . $d;
    }
    if (strlen($d) === 11 && str_starts_with($d, '01')) {
        return '+88' . $d;
    }
    if (str_starts_with($d, '88')) {
        return '+' . $d;
    }
    return '+' . $d;
}

function ame_sms_sim_secret(): array
{
    $file = function_exists('ame_secret_read') ? ame_secret_read('sms') : [];
    return is_array($file) ? $file : [];
}

function ame_sms_channel(array $sms): string
{
    $ch = strtolower(trim((string)($sms['channel'] ?? 'sim')));
    return $ch === 'api' ? 'api' : 'sim';
}

function ame_sms_device_token(bool $rotate = false): string
{
    $file = ame_sms_sim_secret();
    $tok = trim((string)($file['device_token'] ?? ''));
    if ($rotate || $tok === '' || strlen($tok) < 16) {
        $tok = bin2hex(random_bytes(16));
        $file['device_token'] = $tok;
        if (function_exists('ame_secret_write')) {
            ame_secret_write('sms', $file);
        }
    }
    return $tok;
}

function ame_sms_token_ok(string $given): bool
{
    $want = trim((string)(ame_sms_sim_secret()['device_token'] ?? ''));
    if ($want !== '' && $given !== '' && strlen($want) >= 16 && hash_equals($want, $given)) {
        return true;
    }
    global $db;
    return $db instanceof mysqli && ame_sms_sim_by_token($db, $given) !== null;
}

function ame_sms_outbox_counts($db, int $simId = 0, bool $includeLegacy = false): array
{
    $out = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
    if (!($db instanceof mysqli)) {
        return $out;
    }
    ensure_sms_sim_schema($db);
    $where = '1=1';
    if ($simId > 0) {
        $where = $includeLegacy
            ? '(sim_id=' . $simId . ' OR sim_id=0)'
            : 'sim_id=' . $simId;
    }
    $res = @$db->query("SELECT status, COUNT(*) c FROM sms_outbox WHERE {$where} GROUP BY status");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $k = (string)$row['status'];
            if (isset($out[$k])) {
                $out[$k] = (int)$row['c'];
            }
        }
    }
    return $out;
}

function ame_sms_outbox_release_stale($db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    @$db->query("UPDATE sms_outbox SET status='pending', claimed_at=NULL
        WHERE status='sending' AND claimed_at IS NOT NULL AND claimed_at < DATE_SUB(NOW(), INTERVAL 12 MINUTE)");
}

function ame_sms_queue($db, string $mobile, string $message, string $purpose = 'general', int $simId = 0): int
{
    ensure_sms_sim_schema($db);
    if ($simId < 1) {
        $def = ame_sms_sim_default($db);
        $simId = $def ? (int)$def['id'] : 0;
    }
    $stmt = $db->prepare('INSERT INTO sms_outbox (mobile, message, purpose, status, sim_id) VALUES (?,?,?,\'pending\',?)');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('sssi', $mobile, $message, $purpose, $simId);
    $ok = $stmt->execute();
    $id = $ok ? (int)$stmt->insert_id : 0;
    $stmt->close();
    return $id;
}

function ame_sms_outbox_pull($db, int $limit = 5, int $simId = 0, bool $includeLegacy = false): array
{
    ensure_sms_sim_schema($db);
    ame_sms_outbox_release_stale($db);
    $limit = max(1, min(10, $limit));
    $rows = [];
    $where = "status='pending'";
    if ($simId > 0) {
        $where .= $includeLegacy
            ? ' AND (sim_id=' . $simId . ' OR sim_id=0)'
            : ' AND sim_id=' . $simId;
    }
    $res = @$db->query("SELECT id, mobile, message, purpose, sim_id FROM sms_outbox WHERE {$where} ORDER BY id ASC LIMIT " . $limit);
    if (!$res) {
        return $rows;
    }
    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id'];
        $upd = $db->prepare("UPDATE sms_outbox SET status='sending', attempts=attempts+1, claimed_at=NOW() WHERE id=? AND status='pending'");
        if (!$upd) {
            continue;
        }
        $upd->bind_param('i', $id);
        $upd->execute();
        $got = $upd->affected_rows === 1;
        $upd->close();
        if ($got) {
            $rows[] = [
                'id' => $id,
                'mobile' => (string)$row['mobile'],
                'e164' => ame_sms_e164((string)$row['mobile']),
                'message' => (string)$row['message'],
                'purpose' => (string)($row['purpose'] ?? ''),
                'sim_id' => (int)($row['sim_id'] ?? 0),
            ];
        }
    }
    return $rows;
}

function ame_sms_outbox_ack($db, int $id, string $status, string $note = ''): bool
{
    if ($id < 1) {
        return false;
    }
    ensure_sms_sim_schema($db);
    $status = in_array($status, ['sent', 'failed', 'pending'], true) ? $status : 'failed';
    $note = mb_substr($note, 0, 250);
    if ($status === 'pending') {
        $stmt = $db->prepare("UPDATE sms_outbox SET status='pending', claimed_at=NULL, last_error=? WHERE id=? AND status IN ('sending','failed')");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $note, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    if ($status === 'sent') {
        $stmt = $db->prepare("UPDATE sms_outbox SET status='sent', last_error=?, sent_at=NOW() WHERE id=?");
    } else {
        $stmt = $db->prepare("UPDATE sms_outbox SET status='failed', last_error=? WHERE id=?");
    }
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $note, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function ame_sms_smsgate_push(array $sms, string $mobile, string $message, int $simSlot = 0): array
{
    $user = trim((string)($sms['smsgate_user'] ?? ''));
    $pass = trim((string)($sms['smsgate_pass'] ?? ''));
    $url = trim((string)($sms['smsgate_url'] ?? ''));
    if ($url === '') {
        $url = 'https://api.sms-gate.app/3rdparty/v1/message';
    }
    if ($user === '' || $pass === '' || !preg_match('#^https://#i', $url)) {
        return ['ok' => false, 'note' => 'smsgate_skip'];
    }
    $body = [
        'textMessage' => ['text' => $message],
        'phoneNumbers' => [ame_sms_e164($mobile)],
    ];
    if ($simSlot === 1 || $simSlot === 2) {
        $body['simNumber'] = $simSlot;
    }
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_USERPWD => $user . ':' . $pass,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) {
            return ['ok' => false, 'note' => $err ?: 'smsgate_curl'];
        }
        $ok = $code >= 200 && $code < 300;
        return ['ok' => $ok, 'note' => 'smsgate ' . $code . ' ' . substr((string)$body, 0, 180)];
    } catch (Throwable $e) {
        return ['ok' => false, 'note' => 'smsgate_err'];
    }
}

function ame_sms_sim_ready(array $sms): bool
{
    if (strtolower(trim((string)($sms['status'] ?? ''))) !== 'active') {
        return false;
    }
    if (ame_sms_channel($sms) !== 'sim') {
        return false;
    }
    /* Phone number is strongly recommended in UI; still allow queue if token exists
       so older installs keep working until staff paste the number. */
    return true;
}

function ame_sms_normalize_bd_mobile(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '880') && strlen($d) >= 13) {
        $d = substr($d, 2);
    } elseif (str_starts_with($d, '88') && strlen($d) >= 12) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11 && str_starts_with($d, '01')) {
        return $d;
    }
    return $d;
}

function ame_sms_gateway_phone(array $sms): string
{
    return ame_sms_normalize_bd_mobile((string)($sms['gateway_phone'] ?? ''));
}

function ame_sms_device_connected(array $sms, int $graceSec = 120): bool
{
    $raw = trim((string)($sms['device_last_seen'] ?? ''));
    if ($raw === '') {
        return false;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return false;
    }
    return (time() - $ts) <= max(30, $graceSec);
}

function ame_sms_touch_device($db, int $simId = 0): void
{
    $now = date('Y-m-d H:i:s');
    $file = ame_sms_sim_secret();
    $file['device_last_seen'] = $now;
    if (function_exists('ame_secret_write')) {
        ame_secret_write('sms', $file);
    }
    if ($db instanceof mysqli) {
        ensure_sms_sim_schema($db);
        @$db->query("UPDATE sms_settings SET device_last_seen = '" . $db->real_escape_string($now) . "' WHERE id = 1");
        if ($simId > 0) {
            $stmt = $db->prepare('UPDATE sms_sims SET last_seen=? WHERE id=? AND deleted_at IS NULL');
            if ($stmt) {
                $stmt->bind_param('si', $now, $simId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

function ame_sms_sim_new_token(mysqli $db): string
{
    for ($i = 0; $i < 5; $i++) {
        $token = bin2hex(random_bytes(16));
        $stmt = $db->prepare('SELECT id FROM sms_sims WHERE token=? LIMIT 1');
        if (!$stmt) {
            return $token;
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return $token;
        }
    }
    return bin2hex(random_bytes(16));
}

function ame_sms_sim_row(?array $row): ?array
{
    if (!$row) {
        return null;
    }
    return [
        'id' => (int)$row['id'],
        'label' => (string)$row['label'],
        'phone' => (string)$row['phone'],
        'slot' => ((int)$row['slot'] === 2) ? 2 : 1,
        'token' => (string)$row['token'],
        'is_default' => (int)$row['is_default'] === 1,
        'status' => (string)$row['status'] === 'inactive' ? 'inactive' : 'active',
        'last_seen' => (string)($row['last_seen'] ?? ''),
    ];
}

function ame_sms_sims(mysqli $db): array
{
    ensure_sms_sim_schema($db);
    ame_sms_sim_seed($db);
    $out = [];
    $res = @$db->query("SELECT * FROM sms_sims WHERE deleted_at IS NULL ORDER BY is_default DESC, id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $item = ame_sms_sim_row($row);
            if ($item) {
                $out[] = $item;
            }
        }
    }
    return $out;
}

function ame_sms_sim_default(mysqli $db): ?array
{
    foreach (ame_sms_sims($db) as $sim) {
        if ($sim['is_default'] && $sim['status'] === 'active') {
            return $sim;
        }
    }
    foreach (ame_sms_sims($db) as $sim) {
        if ($sim['status'] === 'active') {
            return $sim;
        }
    }
    return null;
}

function ame_sms_sim_get(mysqli $db, int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    ensure_sms_sim_schema($db);
    $stmt = $db->prepare('SELECT * FROM sms_sims WHERE id=? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = ame_sms_sim_row($stmt->get_result()->fetch_assoc() ?: null);
    $stmt->close();
    return $row;
}

function ame_sms_sim_by_token(mysqli $db, string $token): ?array
{
    $token = trim($token);
    if (strlen($token) < 16) {
        return null;
    }
    ensure_sms_sim_schema($db);
    $stmt = $db->prepare("SELECT * FROM sms_sims WHERE token=? AND deleted_at IS NULL AND status='active' LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = ame_sms_sim_row($stmt->get_result()->fetch_assoc() ?: null);
    $stmt->close();
    return $row;
}

function ame_sms_sim_from_token(mysqli $db, string $token): ?array
{
    $sim = ame_sms_sim_by_token($db, $token);
    if ($sim) {
        return $sim;
    }
    $want = trim((string)(ame_sms_sim_secret()['device_token'] ?? ''));
    if ($want !== '' && $token !== '' && hash_equals($want, $token)) {
        return ame_sms_sim_default($db);
    }
    return null;
}

function ame_sms_sim_seed(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensure_sms_sim_schema($db);
    $res = @$db->query('SELECT COUNT(*) AS c FROM sms_sims WHERE deleted_at IS NULL');
    $count = $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
    if ($count > 0) {
        return;
    }
    $phone = '';
    if (function_exists('ame_sms_settings')) {
        $phone = ame_sms_gateway_phone(ame_sms_settings($db));
    }
    if ($phone === '') {
        return;
    }
    ame_sms_sim_save($db, 0, 'মূল সিম', $phone, 1, 'active');
}

function ame_sms_sim_sync_gateway(mysqli $db, string $phone): void
{
    $phone = ame_sms_normalize_bd_mobile($phone);
    $stmt = $db->prepare('UPDATE sms_settings SET gateway_phone=? WHERE id=1');
    if ($stmt) {
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_sms_sim_save(mysqli $db, int $id, string $label, string $phone, int $slot, string $status): int
{
    ensure_sms_sim_schema($db);
    $label = trim(preg_replace('/\s+/', ' ', $label) ?? '');
    if ($label === '') {
        return 0;
    }
    $label = mb_substr($label, 0, 80);
    $phone = ame_sms_normalize_bd_mobile($phone);
    if (strlen($phone) !== 11) {
        return 0;
    }
    $slot = $slot === 2 ? 2 : 1;
    $status = $status === 'inactive' ? 'inactive' : 'active';
    if ($id > 0) {
        $stmt = $db->prepare('UPDATE sms_sims SET label=?, phone=?, slot=?, status=? WHERE id=? AND deleted_at IS NULL');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ssisi', $label, $phone, $slot, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return 0;
        }
        $row = ame_sms_sim_get($db, $id);
        if (!$row) {
            return 0;
        }
        if ($row && $row['is_default']) {
            ame_sms_sim_sync_gateway($db, $phone);
        }
        return $id;
    }
    $token = ame_sms_sim_new_token($db);
    $countRes = @$db->query('SELECT COUNT(*) AS c FROM sms_sims WHERE deleted_at IS NULL');
    $makeDefault = !$countRes || (int)($countRes->fetch_assoc()['c'] ?? 0) === 0;
    $flag = $makeDefault ? 1 : 0;
    $stmt = $db->prepare('INSERT INTO sms_sims (label, phone, slot, token, is_default, status) VALUES (?,?,?,?,?,?)');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('ssisis', $label, $phone, $slot, $token, $flag, $status);
    $ok = $stmt->execute();
    $newId = $ok ? (int)$stmt->insert_id : 0;
    $stmt->close();
    if ($newId > 0 && $makeDefault) {
        ame_sms_sim_sync_gateway($db, $phone);
    }
    return $newId;
}

function ame_sms_sim_make_default(mysqli $db, int $id): bool
{
    $sim = ame_sms_sim_get($db, $id);
    if (!$sim || $sim['status'] !== 'active') {
        return false;
    }
    $db->query('UPDATE sms_sims SET is_default=0 WHERE deleted_at IS NULL');
    $stmt = $db->prepare('UPDATE sms_sims SET is_default=1, status=\'active\' WHERE id=? AND deleted_at IS NULL');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        ame_sms_sim_sync_gateway($db, $sim['phone']);
    }
    return $ok;
}

function ame_sms_sim_remove(mysqli $db, int $id): bool
{
    $sim = ame_sms_sim_get($db, $id);
    if (!$sim) {
        return false;
    }
    $stmt = $db->prepare('UPDATE sms_sims SET deleted_at=NOW(), is_default=0, status=\'inactive\' WHERE id=? AND deleted_at IS NULL');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $sim['is_default']) {
        $next = null;
        foreach (ame_sms_sims($db) as $row) {
            if ($row['status'] === 'active') {
                $next = $row;
                break;
            }
        }
        if ($next) {
            ame_sms_sim_make_default($db, (int)$next['id']);
        }
    }
    return $ok;
}

function ame_sms_sim_rotate(mysqli $db, int $id): string
{
    if (!ame_sms_sim_get($db, $id)) {
        return '';
    }
    $token = ame_sms_sim_new_token($db);
    $stmt = $db->prepare('UPDATE sms_sims SET token=? WHERE id=? AND deleted_at IS NULL');
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('si', $token, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? $token : '';
}

function ame_sms_sim_connected(array $sim, int $graceSec = 120): bool
{
    $raw = trim((string)($sim['last_seen'] ?? ''));
    if ($raw === '') {
        return false;
    }
    $ts = strtotime($raw);
    return $ts !== false && (time() - $ts) <= max(30, $graceSec);
}

