<?php
/**
 * নতুন দোকান: খালি স্কিমা চালিয়ে টেবিল + ডিফল্ট অ্যাডমিন বসানো।
 * DROP / TRUNCATE / DELETE নেই। আগে থেকে টেবিল থাকলে সেগুলো থাকে।
 */

if (!defined('AME_APP_VERSION')) {
    $ameVerFile = dirname(__DIR__) . '/VERSION.txt';
    $ameVer = is_readable($ameVerFile) ? trim((string) file_get_contents($ameVerFile)) : '';
    define('AME_APP_VERSION', $ameVer !== '' ? $ameVer : '10.98.790');
}

function ame_default_admin_username(): string
{
    return 'admin';
}

function ame_default_admin_password(): string
{
    return 'Apon7k#4821';
}

/** password_verify('Apon7k#4821', ...) === true */
function ame_default_admin_hash(): string
{
    return '$2y$10$pBGl3nYeTSfv8WNEBEwm2epsXIR0Dcxutg2JyT8jbdxJBC82B3.fG';
}

function ame_split_sql_statements(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    $raw = preg_replace('/^\s*\/\*.*?\*\//s', '', $raw) ?? $raw;
    $parts = preg_split('/;\s*\n/', $raw) ?: [];
    $out = [];
    foreach ($parts as $sql) {
        $sql = trim($sql);
        if ($sql === '') {
            continue;
        }
        $sql = preg_replace('/^--[^\n]*\n/m', '', $sql) ?? $sql;
        $sql = trim($sql, " \t\n\r\0\x0B;");
        if ($sql === '' || str_starts_with($sql, '--')) {
            continue;
        }
        $out[] = $sql;
    }
    return $out;
}

function ame_run_sql_file(mysqli $db, string $file): array
{
    $report = ['ok' => 0, 'skip' => 0, 'fail' => []];
    if (!is_readable($file)) {
        $report['fail'][] = 'SQL ফাইল পাওয়া যায়নি: ' . $file;
        return $report;
    }
    $raw = (string)file_get_contents($file);
    @$db->query('SET NAMES utf8mb4');
    @$db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (ame_split_sql_statements($raw) as $sql) {
        if (!@$db->query($sql)) {
            $err = (string)$db->error;
            $soft = str_contains($err, 'Duplicate')
                || str_contains($err, 'already exists')
                || str_contains($err, 'Multiple primary key');
            if ($soft) {
                $report['skip']++;
            } else {
                $report['fail'][] = $err;
            }
        } else {
            $report['ok']++;
        }
    }
    @$db->query('SET FOREIGN_KEY_CHECKS=1');
    return $report;
}

function ame_seed_default_admin(mysqli $db): bool
{
    $check = @$db->query("SHOW TABLES LIKE 'users'");
    if (!$check || $check->num_rows === 0) {
        return false;
    }
    $exists = @$db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    if ($exists && $exists->num_rows > 0) {
        return true;
    }
    $user = ame_default_admin_username();
    $hash = ame_default_admin_hash();
    $stmt = @$db->prepare(
        'INSERT INTO users (username, password, role, status, must_change_password, is_super_admin)
         VALUES (?, ?, ?, 1, 1, 1)'
    );
    if (!$stmt) {
        $userEsc = $db->real_escape_string($user);
        $hashEsc = $db->real_escape_string($hash);
        return (bool)@$db->query(
            "INSERT INTO users (username, password, role, status, must_change_password, is_super_admin)
             VALUES ('{$userEsc}', '{$hashEsc}', 'admin', 1, 1, 1)"
        );
    }
    $role = 'admin';
    $stmt->bind_param('sss', $user, $hash, $role);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function ame_first_install(mysqli $db): array
{
    static $running = false;
    if ($running) {
        return ['ok' => 0, 'skip' => 0, 'fail' => [], 'admin' => false, 'reentrant' => true];
    }
    $running = true;
    $schema = dirname(__DIR__) . '/sql/aponshop_empty_tables.sql';
    $report = ame_run_sql_file($db, $schema);
    if (is_file(__DIR__ . '/schema_sync.php')) {
        require_once __DIR__ . '/schema_sync.php';
        if (function_exists('ame_schema_sync_run')) {
            $sync = ame_schema_sync_run($db);
            $report['sync_created'] = $sync['created_tables'] ?? [];
            $report['sync_errors'] = $sync['errors'] ?? [];
        }
    }
    $report['admin'] = ame_seed_default_admin($db);
    $running = false;
    return $report;
}

function ame_install_needed(mysqli $db): bool
{
    $r = @$db->query("SHOW TABLES LIKE 'users'");
    return !($r instanceof mysqli_result) || $r->num_rows === 0;
}
