<?php
/**
 * Error Monitor — শুধু ফাইলে লগ করে, স্ক্রিনে স্ট্যাকট্রেস দেখায় না।
 */

if (!defined('MA_ERROR_MONITOR_LOADED')) {
    define('MA_ERROR_MONITOR_LOADED', true);
}

$ame_mysqli_guard = __DIR__ . '/ame_mysqli_guard.php';
if (is_file($ame_mysqli_guard)) {
    require_once $ame_mysqli_guard;
    if (function_exists('ame_mysqli_patch_db_file')) {
        ame_mysqli_patch_db_file(__DIR__ . '/db.php');
    }
}

function ma_diagnostic_log_path(): string
{
    if (defined('AME_PRIVATE_DATA_DIR')) {
        return AME_PRIVATE_DATA_DIR . '/logs/php_errors.log';
    }
    return dirname(__DIR__, 2) . '/private_data/logs/php_errors.log';
}

function ame_legacy_jsonl_log_path(): string
{
    $home = defined('AME_PRIVATE_DATA_DIR')
        ? dirname(AME_PRIVATE_DATA_DIR)
        : dirname(__DIR__, 2);
    return $home . '/ma_private_logs/application-errors.jsonl';
}

function ame_error_request_id(): string
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    try {
        $id = 'ERR-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
    } catch (Throwable $e) {
        $id = 'ERR-' . date('Ymd-His') . '-' . substr(strtoupper(sha1(uniqid('', true))), 0, 6);
    }
    return $id;
}

function ame_error_is_api_request(): bool
{
    $path = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json')
        || $xhr === 'xmlhttprequest'
        || str_contains($path, '/api/')
        || str_contains($path, '/actions/')
        || str_ends_with($path, '/action.php')
        || str_ends_with($path, '_api.php')
        || str_contains(basename($path), 'process_');
}

function ame_error_module(): string
{
    $path = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    if (preg_match('#/modules/([^/]+)/#', $path, $m)) {
        return preg_replace('/[^a-z0-9_-]/', '', (string)$m[1]) ?: 'unknown';
    }
    return 'core';
}

function ame_error_write_event(string $type, string $message, string $file = '', int $line = 0): string
{
    $id = ame_error_request_id();
    $event = [
        'type' => $type,
        'time' => date('c'),
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'page' => (string)($_SERVER['REQUEST_URI'] ?? $_SERVER['SCRIPT_NAME'] ?? ''),
        'request_id' => $id,
        'module' => ame_error_module(),
        'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
        'user_id' => (int)($_SESSION['user_id'] ?? 0),
    ];
    $path = ame_legacy_jsonl_log_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @file_put_contents(
        $path,
        json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );
    error_log($type . ' [' . $id . '] ' . $message . ($file !== '' ? (' in ' . $file . ':' . $line) : ''));
    $qa = __DIR__ . '/qa_tracker.php';
    if (is_file($qa)) {
        require_once $qa;
        if (function_exists('ame_qa_from_php_event')) {
            ame_qa_from_php_event($event);
        }
    }
    return $id;
}

function ame_error_clear_output(): void
{
    $base = defined('AME_ERROR_BASE_OB_LEVEL') ? (int)AME_ERROR_BASE_OB_LEVEL : 0;
    while (ob_get_level() > $base) {
        @ob_end_clean();
    }
}

function ame_error_render(string $id, int $status = 500): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Cache-Control: no-store');
    }
    if (ame_error_is_api_request()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'ok' => false,
            'error' => 'request_failed',
            'message' => 'অনুরোধটি সম্পন্ন হয়নি। আবার চেষ্টা করুন।',
            'error_id' => $id,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    $safeId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
    echo '<title>সাময়িক সমস্যা</title></head><body style="margin:0;background:#fff;color:#10233f;font-family:system-ui,-apple-system,sans-serif">';
    echo '<main style="min-height:100vh;display:grid;place-items:center;padding:20px;box-sizing:border-box"><section style="width:min(560px,100%);border:1px solid #fecaca;border-top:7px solid #dc2626;border-radius:18px;padding:24px;box-sizing:border-box;box-shadow:0 14px 36px rgba(15,23,42,.12);background:#fff">';
    echo '<div style="font-size:34px">⚠️</div><h1 style="margin:8px 0;color:#b91c1c;font-size:24px">এই পেজে সাময়িক সমস্যা হয়েছে</h1>';
    echo '<p style="line-height:1.65;margin:0 0 10px">অন্য মডিউলগুলো ব্যবহার করা যাবে। আবার চেষ্টা করুন, আগের পেজে ফিরুন অথবা হোমে যান।</p>';
    echo '<p style="padding:10px 12px;background:#fff7ed;border-radius:10px;color:#9a3412"><b>Error ID:</b> <code>' . $safeId . '</code></p>';
    echo '<div style="display:flex;gap:9px;flex-wrap:wrap"><button onclick="location.reload()" style="border:0;border-radius:10px;padding:11px 16px;background:#0e8f52;color:#fff;font-weight:800">আবার চেষ্টা</button>';
    echo '<button onclick="history.length>1?history.back():location.assign(\'/index.php\')" style="border:0;border-radius:10px;padding:11px 16px;background:#1565c0;color:#fff;font-weight:800">পেছনে</button>';
    echo '<a href="/index.php" style="border-radius:10px;padding:11px 16px;background:#0b1e4d;color:#fff;font-weight:800;text-decoration:none">হোম</a></div>';
    echo '</section></main></body></html>';
}

function ame_error_log_is_noise(string $message): bool
{
    $message = trim($message);
    if ($message === '') {
        return false;
    }
    if (preg_match('/^\[.+?\]\s*(.*)$/s', $message, $m)) {
        $message = trim((string) $m[1]);
    }
    if (str_contains($message, 'LEDGER LOCK skip')) {
        return true;
    }
    if (str_contains($message, 'ame_pre_domain_keep_later_payments')
        && (str_contains($message, 'skip') || str_contains($message, 'LEDGER LOCK'))) {
        return true;
    }
    if (str_contains($message, 'ame_restore_pre_domain_party_amounts')
        && (str_contains($message, 'skip') || str_contains($message, 'LEDGER LOCK'))) {
        return true;
    }
    if (str_contains($message, 'অপর্যাপ্ত ব্যালেন্স') || str_contains($message, 'Insufficient')) {
        return true;
    }
    return false;
}

function ame_error_entry_is_noise(array $entry): bool
{
    $blob = trim(implode("\n", [
        (string) ($entry['type'] ?? ''),
        (string) ($entry['message'] ?? ''),
        (string) ($entry['file'] ?? ''),
    ]));
    return ame_error_log_is_noise($blob);
}

function ame_parse_php_error_log(string $path, int $limit = 40): array
{
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice($lines, -240);
    $entries = [];
    $current = null;
    foreach ($lines as $line) {
        if (preg_match('/^\[(.+?)\]\s+(.*)$/', $line, $m)) {
            if (is_array($current) && !ame_error_entry_is_noise($current)) {
                $entries[] = $current;
            }
            $current = [
                'type' => 'PHP',
                'time' => $m[1],
                'message' => $m[2],
                'file' => '',
                'line' => 0,
                'page' => '',
                'request_id' => '',
            ];
            if (preg_match('/in (.+) on line (\d+)/', $m[2], $lm)) {
                $current['file'] = $lm[1];
                $current['line'] = (int) $lm[2];
            }
        } elseif (is_array($current)) {
            $current['message'] .= "\n" . $line;
            if (preg_match('/in (.+) on line (\d+)/', $line, $lm) && $current['file'] === '') {
                $current['file'] = $lm[1];
                $current['line'] = (int) $lm[2];
            }
        }
    }
    if (is_array($current) && !ame_error_entry_is_noise($current)) {
        $entries[] = $current;
    }
    return array_slice(array_reverse($entries), 0, $limit);
}

function ame_parse_jsonl_error_log(string $path, int $limit = 20): array
{
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice(array_reverse($lines), 0, max(40, $limit * 4));
    $entries = [];
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry) && !ame_error_entry_is_noise($entry)) {
            $entries[] = $entry;
        }
        if (count($entries) >= $limit) {
            break;
        }
    }
    return $entries;
}

/**
 * গত $hours ঘণ্টায় লগ ফাইলে কতগুলো এরর এন্ট্রি জমা হয়েছে — সেটিংস/ড্যাশবোর্ডে
 * ছোট্ট একটা ব্যাজ দেখাতে ব্যবহার হয়, যাতে সমস্যা দ্রুত চোখে পড়ে (পেজ খুলে
 * দেখতে হয় না)। খরচ কমাতে লগ ফাইলের শেষ ৩০০ লাইনের বেশি পড়া হয় না।
 */
function ame_recent_error_count(int $hours = 24): int
{
    $path = ma_diagnostic_log_path();
    if (!is_file($path) || !is_readable($path)) {
        return 0;
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice($lines, -300);
    $cutoff = time() - ($hours * 3600);
    $count = 0;
    foreach ($lines as $line) {
        if (!preg_match('/^\[(.+?)\]\s*(.*)$/', $line, $m)) {
            continue;
        }
        if (ame_error_log_is_noise((string) $m[2])) {
            continue;
        }
        $ts = strtotime($m[1]);
        if ($ts !== false && $ts >= $cutoff) {
            $count++;
        }
    }
    return $count;
}

/**
 * এরর লগ ফাইল খালি করে দেয় (ফাইলটা মুছে ফেলে না, শুধু ভেতরের লেখা সাফ করে) —
 * কোনো কোড বা ডেটা বদলায় না, শুধু পুরনো/সমাধান হয়ে যাওয়া এরর এন্ট্রি সরিয়ে
 * ড্যাশবোর্ডের সতর্কতা-সংখ্যা রিসেট করার জন্য।
 */
function ame_clear_diagnostic_logs(): bool
{
    $ok = true;
    foreach ([ma_diagnostic_log_path(), ame_legacy_jsonl_log_path()] as $path) {
        if ($path !== '' && is_file($path)) {
            if (@file_put_contents($path, '') === false) {
                $ok = false;
            }
        }
    }
    return $ok;
}

if (!defined('AME_ERROR_BASE_OB_LEVEL')) {
    define('AME_ERROR_BASE_OB_LEVEL', ob_get_level());
}

if (!function_exists('handleFatalError')) {
    function handleFatalError(): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_USER_ERROR], true)) {
            $id = ame_error_write_event(
                'FATAL',
                (string)($error['message'] ?? 'Fatal error'),
                (string)($error['file'] ?? ''),
                (int)($error['line'] ?? 0)
            );
            ame_error_clear_output();
            ame_error_render($id, 500);
        }
    }
    register_shutdown_function('handleFatalError');
}

set_exception_handler(static function (Throwable $error): void {
    $id = ame_error_write_event(
        'UNCAUGHT_EXCEPTION',
        $error->getMessage(),
        $error->getFile(),
        $error->getLine()
    );
    ame_error_clear_output();
    ame_error_render($id, 500);
});

function ame_update_request_is_control_plane(): bool
{
    return in_array(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')), [
        'easy_update.php',
        'process_easy_update.php',
        'process_easy_update_put.php',
        'process_update_rollback.php',
    ], true);
}

function ame_update_is_actively_locked(): bool
{
    $path = dirname(__DIR__, 2) . '/.ame-update.lock';
    if (!is_file($path)) {
        return false;
    }
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return false;
    }
    $available = @flock($handle, LOCK_EX | LOCK_NB);
    if ($available) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
        return false;
    }
    @fclose($handle);
    return true;
}

if (PHP_SAPI !== 'cli' && !ame_update_request_is_control_plane() && ame_update_is_actively_locked()) {
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 20');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="10"><title>নিরাপদ আপডেট চলছে</title></head>';
    echo '<body style="margin:0;background:#fff;color:#10233f;font-family:system-ui,sans-serif"><main style="min-height:100vh;display:grid;place-items:center;padding:20px;box-sizing:border-box">';
    echo '<section style="width:min(540px,100%);background:#fff;border:1px solid #bfdbfe;border-top:7px solid #1565c0;border-radius:18px;padding:24px;box-sizing:border-box;box-shadow:0 14px 36px rgba(15,23,42,.1)">';
    echo '<h1 style="margin:0 0 10px;color:#0b1e4d">নিরাপদ আপডেট চলছে</h1><p style="line-height:1.65">ফাইল অর্ধেক অবস্থায় দেখানো বন্ধ রাখতে সাইটটি কয়েক সেকেন্ডের জন্য সুরক্ষিত আছে। এই পেজ নিজে থেকেই আবার চেষ্টা করবে।</p>';
    echo '<button onclick="location.reload()" style="border:0;border-radius:10px;padding:11px 16px;background:#0e8f52;color:#fff;font-weight:800">এখন আবার চেষ্টা</button>';
    echo '</section></main></body></html>';
    exit;
}
