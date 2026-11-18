<?php
/**
 * স্টেজিংয়ের বাগ ট্র্যাক।
 * লগ সবসময় লেখে। ইমেইল ও Sentry তখনই যায়, যখন .env এ ঠিকানা দেওয়া থাকে।
 */

function ame_qa_event_type(string $type): string
{
    $type = strtoupper(trim($type));
    $allowed = ['JAVASCRIPT', 'API', 'FATAL', 'UNCAUGHT_EXCEPTION', 'PHP'];
    return in_array($type, $allowed, true) ? $type : 'PHP';
}

function ame_qa_log_targets(): array
{
    $override = getenv('AME_QA_LOG');
    if (is_string($override) && $override !== '') {
        return [$override];
    }
    $paths = [];
    if (function_exists('ame_legacy_jsonl_log_path')) {
        $paths[] = ame_legacy_jsonl_log_path();
    }
    $paths[] = dirname(__DIR__) . '/storage/qa-logs/qa-events.jsonl';
    $ready = [];
    foreach ($paths as $path) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            $ready[] = $path;
        }
    }
    return $ready;
}

function ame_qa_record(array $event): string
{
    $id = preg_replace('/[^A-Z0-9_-]/i', '', (string)($event['request_id'] ?? ''));
    if ($id === '') {
        $id = function_exists('ame_error_request_id')
            ? ame_error_request_id()
            : ('QA-' . date('Ymd-His'));
    }
    $row = [
        'type' => ame_qa_event_type((string)($event['type'] ?? 'PHP')),
        'time' => date('c'),
        'message' => mb_substr((string)($event['message'] ?? ''), 0, 800, 'UTF-8'),
        'file' => mb_substr((string)($event['file'] ?? ''), 0, 500, 'UTF-8'),
        'line' => max(0, (int)($event['line'] ?? 0)),
        'page' => mb_substr((string)($event['page'] ?? ''), 0, 500, 'UTF-8'),
        'request_id' => $id,
        'module' => mb_substr((string)($event['module'] ?? 'core'), 0, 40, 'UTF-8'),
        'method' => mb_substr((string)($event['method'] ?? ''), 0, 12, 'UTF-8'),
        'status' => max(0, (int)($event['status'] ?? 0)),
        'user_id' => (int)($event['user_id'] ?? 0),
    ];
    $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return $id;
    }
    $legacy = function_exists('ame_legacy_jsonl_log_path') ? ame_legacy_jsonl_log_path() : '';
    foreach (ame_qa_log_targets() as $path) {
        if (!empty($event['skip_legacy']) && $legacy !== '' && $path === $legacy) {
            continue;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    }
    $GLOBALS['ame_qa_php_logged'] = true;
    ame_qa_alert($row);
    return $id;
}

function ame_qa_from_php_event(array $event): void
{
    try {
        $event['skip_legacy'] = true;
        ame_qa_record($event);
    } catch (Throwable $e) {
        error_log('qa tracker skipped: ' . $e->getMessage());
    }
}

function ame_qa_should_alert(): bool
{
    $email = trim((string)getenv('QA_ALERT_EMAIL'));
    $dsn = trim((string)getenv('SENTRY_DSN'));
    if ($email === '' && $dsn === '') {
        return false;
    }
    if (function_exists('ame_is_staging_app') && ame_is_staging_app()) {
        return true;
    }
    $live = strtolower(trim((string)getenv('QA_ALERT_ON_LIVE')));
    return in_array($live, ['1', 'true', 'yes', 'on'], true);
}

function ame_qa_alert_recent(string $key): bool
{
    $dir = dirname(__DIR__) . '/storage/qa-logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $path = $dir . '/alert-stamp.json';
    $stamps = [];
    if (is_file($path)) {
        $decoded = json_decode((string)file_get_contents($path), true);
        if (is_array($decoded)) {
            $stamps = $decoded;
        }
    }
    $now = time();
    foreach ($stamps as $stampKey => $when) {
        if ((int)$when < ($now - 3600)) {
            unset($stamps[$stampKey]);
        }
    }
    if (isset($stamps[$key]) && (int)$stamps[$key] >= ($now - 3600)) {
        return true;
    }
    $stamps[$key] = $now;
    @file_put_contents($path, json_encode($stamps));
    return false;
}

function ame_qa_sentry_endpoint(string $dsn): ?array
{
    $parts = parse_url(trim($dsn));
    if (!is_array($parts) || empty($parts['host']) || empty($parts['user']) || empty($parts['path'])) {
        return null;
    }
    $project = trim((string)$parts['path'], '/');
    if ($project === '' || !ctype_digit($project)) {
        return null;
    }
    $scheme = (string)($parts['scheme'] ?? 'https');
    $port = isset($parts['port']) ? (':' . $parts['port']) : '';
    return [
        'url' => $scheme . '://' . $parts['host'] . $port . '/api/' . $project . '/store/',
        'key' => (string)$parts['user'],
    ];
}

function ame_qa_alert(array $row): void
{
    if (!ame_qa_should_alert()) {
        return;
    }
    $key = sha1($row['type'] . '|' . $row['module'] . '|' . $row['message']);
    if (ame_qa_alert_recent($key)) {
        return;
    }
    $dsn = trim((string)getenv('SENTRY_DSN'));
    $endpoint = $dsn !== '' ? ame_qa_sentry_endpoint($dsn) : null;
    if ($endpoint !== null && function_exists('curl_init')) {
        $payload = json_encode([
            'message' => $row['message'],
            'level' => 'error',
            'platform' => 'php',
            'logger' => 'aponshop',
            'tags' => [
                'module' => $row['module'],
                'type' => $row['type'],
            ],
            'extra' => [
                'request_id' => $row['request_id'],
                'page' => $row['page'],
                'status' => $row['status'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ch = curl_init($endpoint['url']);
        if ($ch !== false && $payload !== false) {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Sentry-Auth: Sentry sentry_version=7, sentry_key=' . $endpoint['key'] . ', sentry_client=aponshop-qa/1.0',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 2,
            ]);
            @curl_exec($ch);
            curl_close($ch);
        }
    }
    $email = trim((string)getenv('QA_ALERT_EMAIL'));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $mail = __DIR__ . '/mail_functions.php';
    if (!is_file($mail)) {
        return;
    }
    require_once $mail;
    if (!function_exists('send_simple_email')) {
        return;
    }
    $subject = 'অপনশপ স্টেজিং এরর ' . $row['request_id'];
    $body = '<p><b>' . htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8') . '</b> '
        . htmlspecialchars($row['module'], ENT_QUOTES, 'UTF-8') . '</p><p>'
        . htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8') . '</p><p>'
        . htmlspecialchars($row['page'], ENT_QUOTES, 'UTF-8') . '</p>';
    @send_simple_email($email, $subject, $body);
}

function ame_qa_log_http_status(): void
{
    if (!empty($GLOBALS['ame_qa_php_logged']) || PHP_SAPI === 'cli') {
        return;
    }
    if (!function_exists('ame_error_is_api_request') || !ame_error_is_api_request()) {
        return;
    }
    $status = http_response_code();
    if (!is_int($status) || $status < 400) {
        return;
    }
    $page = (string)($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($page, 'client-error.php')) {
        return;
    }
    ame_qa_record([
        'type' => 'API',
        'message' => 'HTTP ' . $status,
        'page' => $page,
        'status' => $status,
        'module' => function_exists('ame_error_module') ? ame_error_module() : 'core',
        'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
        'user_id' => (int)($_SESSION['user_id'] ?? 0),
    ]);
}

if (PHP_SAPI !== 'cli' && empty($GLOBALS['ame_qa_shutdown'])) {
    $GLOBALS['ame_qa_shutdown'] = true;
    register_shutdown_function('ame_qa_log_http_status');
}
