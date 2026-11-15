<?php
/**
 * ইঞ্জিনিয়ার / সার্ভিস / POS কর্মী কোর লক — প্যাচে অননুমোদিত পরিবর্তন ব্লক + নোটিফিকেশন।
 * AME_ENGINEER_BILL_LOCK_REV বদলানোর আগে মালিকের অনুমতি দরকার।
 */
declare(strict_types=1);

if (!defined('AME_ENGINEER_BILL_LOCK_REV')) {
    define('AME_ENGINEER_BILL_LOCK_REV', '10.98.899');
}

function ame_engineer_bill_lock_paths(): array
{
    return [
        'includes/engineer_bill_lock.php',
        'includes/sales/sale_finance.php',
        'includes/employee_hub.php',
        'includes/worker_work_lock.php',
        'includes/worker_desk.php',
        'modules/sales/process_sale.php',
        'modules/hrm/hub_api.php',
        'modules/hrm/employee_list.php',
        'modules/sales/pos_workspace.php',
        'assets/js/pos-payment.js',
        'assets/js/ame-resubmit-guard.js',
        'assets/js/employee-hub.js',
        'assets/js/offline-queue.js',
        'tools/engineer_bill_lock_manifest.sha256',
        'ENGINEER_SERVICE_PROTECTED_BN.txt',
    ];
}

function ame_engineer_bill_lock_protects(string $rel): bool
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    return in_array($rel, ame_engineer_bill_lock_paths(), true);
}

function ame_engineer_bill_lock_manifest_file(?string $root = null): string
{
    $root = $root !== null && $root !== '' ? rtrim($root, '/') : dirname(__DIR__);

    return $root . '/tools/engineer_bill_lock_manifest.sha256';
}

function ame_engineer_bill_lock_log(string $line, ?string $root = null): void
{
    $root = $root !== null && $root !== '' ? rtrim($root, '/') : dirname(__DIR__);
    $dir = $root . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
  $path = $dir . '/engineer_bill_lock.log';
    @file_put_contents($path, date('c') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

function ame_engineer_bill_lock_override_active(?string $root = null): bool
{
    $root = $root !== null && $root !== '' ? rtrim($root, '/') : dirname(__DIR__);
    $flag = $root . '/storage/.ame_engineer_lock_override';
    if (!is_file($flag)) {
        return false;
    }
    $until = (int)@filemtime($flag);
    return $until > 0 && time() <= $until + 3600;
}

/** @return array<string,string> path => sha256 */
function ame_engineer_bill_lock_read_manifest(?string $root = null): array
{
    $file = ame_engineer_bill_lock_manifest_file($root);
    if (!is_file($file)) {
        return [];
    }
    $out = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!preg_match('/^[a-f0-9]{64}\s+(.+)$/', $line, $m)) {
            continue;
        }
        $out[$m[1]] = $m[0];
    }

    return $out;
}

/**
 * @return list<array{path:string,expected:string,actual:string}>
 */
function ame_engineer_bill_lock_verify(?string $root = null): array
{
    $root = $root !== null && $root !== '' ? rtrim($root, '/') : dirname(__DIR__);
    $manifest = ame_engineer_bill_lock_read_manifest($root);
    if ($manifest === []) {
        return [];
    }
    $bad = [];
    foreach ($manifest as $rel => $expected) {
        $path = $root . '/' . $rel;
        if (!is_file($path)) {
            $bad[] = ['path' => $rel, 'expected' => $expected, 'actual' => 'MISSING'];
            continue;
        }
        $actual = hash_file('sha256', $path);
        if (!hash_equals($expected, $actual)) {
            $bad[] = ['path' => $rel, 'expected' => $expected, 'actual' => $actual];
        }
    }

    return $bad;
}

function ame_engineer_bill_lock_queue_admin_notice(array $messages, ?string $root = null): void
{
    if ($messages === []) {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['ame_engineer_lock_notices'] = array_values(array_unique(array_merge(
        (array)($_SESSION['ame_engineer_lock_notices'] ?? []),
        $messages
    )));
}

function ame_engineer_bill_lock_boot(?string $root = null): void
{
    $root = $root !== null && $root !== '' ? rtrim($root, '/') : dirname(__DIR__);
    $bad = ame_engineer_bill_lock_verify($root);
    if ($bad === []) {
        return;
    }
    $lines = [];
    foreach ($bad as $row) {
        $lines[] = 'লক ভাঙা: ' . $row['path'];
    }
    ame_engineer_bill_lock_log('INTEGRITY ' . implode('; ', $lines), $root);
    ame_engineer_bill_lock_queue_admin_notice([
        '⚠️ ইঞ্জিনিয়ার/সার্ভিস সুরক্ষিত কোড পরিবর্তিত হয়েছে (' . count($bad) . ' ফাইল)। অনুমতি ছাড়া আপডেট করবেন না। REV ' . AME_ENGINEER_BILL_LOCK_REV,
    ], $root);
}

function ame_engineer_bill_lock_notify_zip_blocked(array $paths, ?string $root = null): void
{
    if ($paths === []) {
        return;
    }
    $root = $root !== null && $root !== '' ? rtrim($root, '/') : dirname(__DIR__);
    ame_engineer_bill_lock_log('ZIP_BLOCKED ' . implode(', ', $paths), $root);
    ame_engineer_bill_lock_queue_admin_notice([
        '🚫 প্যাচে সুরক্ষিত ইঞ্জিনিয়ার/সার্ভিস ফাইল বদলাতে চেয়েছে কিন্তু লকের কারণে বাদ দেওয়া হয়েছে: ' . implode(', ', array_slice($paths, 0, 5))
        . (count($paths) > 5 ? '…' : ''),
    ], $root);
}

function ame_engineer_bill_lock_render_admin_banner(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    $notes = (array)($_SESSION['ame_engineer_lock_notices'] ?? []);
    if ($notes === []) {
        return '';
    }
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if ($role !== 'admin') {
        return '';
    }
    $html = '<div class="ame-engineer-lock-banner" role="alert" style="margin:8px 12px;padding:12px 14px;border-radius:12px;background:linear-gradient(135deg,#7f1d1d,#450a0a);color:#fff;border:1px solid #fca5a5;font-size:14px;line-height:1.45;">';
    $html .= '<strong>সুরক্ষিত সিস্টেম (ইঞ্জিনিয়ার/সার্ভিস)</strong><ul style="margin:8px 0 0 18px;padding:0;">';
    foreach ($notes as $n) {
        $html .= '<li>' . htmlspecialchars((string)$n, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $html .= '</ul><p style="margin:8px 0 0;font-size:12px;opacity:.9;">লক রিভিশন: '
        . htmlspecialchars(AME_ENGINEER_BILL_LOCK_REV, ENT_QUOTES, 'UTF-8')
        . ' — কোড বদলাতে মালিকের অনুমতি ও tools/pin_engineer_bill_lock.php দরকার।</p></div>';

    return $html;
}