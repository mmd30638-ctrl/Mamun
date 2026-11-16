<?php
/**
 * Module-level circuit breaker.
 *
 * This does not pretend that a failed database/authentication core can be
 * isolated. It contains failures in independent business modules after the
 * shared bootstrap and database connection are healthy.
 */

function ame_module_control_catalog(): array
{
    return [
        'sales' => 'বিক্রয় ও POS',
        'purchase' => 'পণ্য ক্রয়',
        'inventory' => 'স্টক ও ইনভেন্টরি',
        'customers' => 'কাস্টমার',
        'suppliers' => 'সাপ্লায়ার',
        'hrm' => 'কর্মী ও বেতন',
        'workshop' => 'সার্ভিস ও মেরামত',
        'accounts' => 'হিসাব ও লেনদেন',
        'partners' => 'পার্টনার হিসাব',
        'returns' => 'বিক্রয় ফেরত',
        'reports' => 'রিপোর্ট',
        'rental' => 'ভাড়া',
        'loans' => 'ঋণ',
        'family' => 'পারিবারিক হিসাব',
        'settings_optional' => 'ঐচ্ছিক সেটিংস টুল',
    ];
}

function ame_module_control_ensure(mysqli $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (function_exists('ame_schema_version_ready') && ame_schema_version_ready($db)) {
        $ready = true;
        return true;
    }
    try {
        $ready = (bool)$db->query("CREATE TABLE IF NOT EXISTS module_controls (
            module_key VARCHAR(40) NOT NULL PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            reason VARCHAR(255) NULL,
            updated_by INT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('module control schema unavailable: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

function ame_request_business_module(): ?string
{
    $path = strtolower(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    if (!str_contains($path, '/modules/')) {
        return null;
    }
    $rules = [
        '/modules/purchase/returns/' => 'purchase',
        '/modules/purchase/' => 'purchase',
        '/modules/sales/' => 'sales',
        '/modules/inventory/' => 'inventory',
        '/modules/customers/' => 'customers',
        '/modules/suppliers/' => 'suppliers',
        '/modules/hrm/' => 'hrm',
        '/modules/workshop/' => 'workshop',
        '/modules/accounts/' => 'accounts',
        '/modules/finance/' => 'accounts',
        '/modules/expenses/' => 'accounts',
        '/modules/partners/' => 'partners',
        '/modules/returns/' => 'returns',
        '/modules/reports/' => 'reports',
        '/modules/rental/' => 'rental',
        '/modules/loans/' => 'loans',
        '/modules/bloan/' => 'loans',
        '/modules/borrow/' => 'loans',
        '/modules/family/' => 'family',
    ];
    foreach ($rules as $needle => $module) {
        if (str_contains($path, $needle)) {
            return $module;
        }
    }
    if (str_contains($path, '/modules/settings/')) {
        $critical = [
            'module_controls.php',
            'process_module_control.php',
            'easy_update.php',
            'process_easy_update.php',
            'process_easy_update_put.php',
            'process_update_rollback.php',
            'error_diagnostics.php',
            'system_health.php',
            'backup.php',
            'process_run_backup.php',
            'process_backup_download.php',
            'banners.php',
            'process_banners.php',
            'role_control.php',
            'process_role_control.php',
            'users.php',
            'reset_password.php',
            'process_reset_password.php',
            'restore.php',
            'logout.php',
        ];
        return in_array(basename($path), $critical, true) ? null : 'settings_optional';
    }
    return null;
}

function ame_module_control_row(mysqli $db, string $module): array
{
    if (!isset(ame_module_control_catalog()[$module]) || !ame_module_control_ensure($db)) {
        return ['enabled' => 1, 'reason' => ''];
    }
    try {
        $stmt = $db->prepare('SELECT enabled, reason, updated_at FROM module_controls WHERE module_key = ? LIMIT 1');
        if (!$stmt) {
            return ['enabled' => 1, 'reason' => ''];
        }
        $stmt->bind_param('s', $module);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: ['enabled' => 1, 'reason' => ''];
    } catch (Throwable $e) {
        error_log('module control read failed: ' . $e->getMessage());
        return ['enabled' => 1, 'reason' => ''];
    }
}

function ame_module_control_set(mysqli $db, string $module, bool $enabled, string $reason, int $userId): bool
{
    if (!isset(ame_module_control_catalog()[$module]) || !ame_module_control_ensure($db)) {
        return false;
    }
    $reason = mb_substr(trim($reason), 0, 255, 'UTF-8');
    $value = $enabled ? 1 : 0;
    $stmt = $db->prepare(
        'INSERT INTO module_controls (module_key, enabled, reason, updated_by)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), reason=VALUES(reason),
             updated_by=VALUES(updated_by), updated_at=NOW()'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sisi', $module, $value, $reason, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function ame_module_control_block(mysqli $db): void
{
    $module = ame_request_business_module();
    if ($module === null || strtolower((string)($_SESSION['role'] ?? '')) === 'admin') {
        return;
    }
    $state = ame_module_control_row($db, $module);
    if ((int)($state['enabled'] ?? 1) === 1) {
        return;
    }
    $label = ame_module_control_catalog()[$module] ?? $module;
    $reason = trim((string)($state['reason'] ?? ''));
    $errorId = 'MOD-' . strtoupper($module) . '-' . date('YmdHi');
    if (function_exists('ame_error_is_api_request') && ame_error_is_api_request()) {
        http_response_code(503);
        header('Content-Type: application/json; charset=UTF-8');
        header('Retry-After: 300');
        echo json_encode([
            'ok' => false,
            'error' => 'module_temporarily_disabled',
            'module' => $module,
            'message' => $label . ' সাময়িকভাবে বন্ধ আছে।',
            'error_id' => $errorId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 300');
    echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>মডিউল সাময়িক বন্ধ</title></head>';
    echo '<body style="margin:0;background:#fff;color:#10233f;font-family:system-ui,sans-serif"><main style="min-height:100vh;display:grid;place-items:center;padding:20px;box-sizing:border-box">';
    echo '<section style="width:min(580px,100%);background:#fff;border:1px solid #fde68a;border-top:7px solid #f59e0b;border-radius:18px;padding:24px;box-sizing:border-box;box-shadow:0 14px 36px rgba(15,23,42,.1)">';
    echo '<h1 style="margin:0 0 10px;color:#b45309">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' সাময়িকভাবে বন্ধ</h1>';
    echo '<p>এই মডিউলে রক্ষণাবেক্ষণ চলছে। অন্য মডিউলগুলো স্বাভাবিকভাবে ব্যবহার করতে পারবেন।</p>';
    if ($reason !== '') {
        echo '<p style="background:#fffbeb;padding:10px 12px;border-radius:10px">' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '<p><b>Reference:</b> ' . htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="/index.php" style="display:inline-block;background:#0b1e4d;color:#fff;padding:11px 16px;border-radius:10px;text-decoration:none;font-weight:800">হোমে যান</a>';
    echo '</section></main></body></html>';
    exit;
}
