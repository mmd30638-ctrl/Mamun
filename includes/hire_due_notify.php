<?php
/**
 * Hire due reminder — same UX as customer-care due-reminder:
 * next follow-up date, edit, take money. Homepage top notice + header badge.
 */

function hire_due_notify_boot(mysqli $db): void
{
    if (function_exists('hire_term_boot')) {
        hire_term_boot($db);
    }
}

function hire_due_notify_try_load(mysqli $db): bool
{
    $hf = __DIR__ . '/hire_functions.php';
    if (!function_exists('hire_boot') && is_file($hf)) {
        require_once $hf;
    }
    $ht = __DIR__ . '/hire_term.php';
    if (!function_exists('hire_term_boot') && is_file($ht)) {
        require_once $ht;
    }
    if (!function_exists('hire_rental_due') && !function_exists('hire_due_snapshot')) {
        return false;
    }
    try {
        if (function_exists('hire_boot')) {
            hire_boot($db);
        }
        hire_due_notify_boot($db);
        return true;
    } catch (Throwable $e) {
        error_log('hire due boot: ' . $e->getMessage());
        return false;
    }
}

function hire_due_row_badge(array $r, string $today): array
{
    $date = trim((string) ($r['follow_date'] ?? ''));
    $due = (float) ($r['due'] ?? 0);
    if ($date !== '' && $date < $today) {
        return ['kind' => 'overdue', 'class' => 'badge-overdue', 'text' => 'মেয়াদ পার', 'date' => $date];
    }
    if ($date === $today) {
        return ['kind' => 'today', 'class' => 'badge-today', 'text' => 'আজকের তারিখ', 'date' => $date];
    }
    if ($date !== '' && $date > $today) {
        return ['kind' => 'upcoming', 'class' => 'badge-upcoming', 'text' => 'আগামী দিনে', 'date' => $date];
    }
    if ($due > 0.009) {
        return ['kind' => 'overdue', 'class' => 'badge-overdue', 'text' => 'তারিখ নেই', 'date' => '—'];
    }
    return ['kind' => 'upcoming', 'class' => 'badge-upcoming', 'text' => 'নির্ধারিত', 'date' => $date !== '' ? $date : '—'];
}

function hire_due_notify_rows(mysqli $db, bool $home = false): array
{
    $today = date('Y-m-d');
    $out = [
        'today' => $today,
        'overdue_count' => 0,
        'today_count' => 0,
        'upcoming_count' => 0,
        'unpaid_count' => 0,
        'due' => 0.0,
        'unpaid_days' => 0,
        'count' => 0,
        'badge' => 0,
        'accounts' => [],
        'rows' => [],
        'csrf' => function_exists('csrf_token') ? csrf_token() : '',
        'can_edit' => true,
        'can_pay' => true,
    ];
    if (function_exists('hire_can')) {
        $out['can_edit'] = hire_can($db, 'edit');
        $out['can_pay'] = hire_can($db, 'edit');
    }
    if (function_exists('hire_accounts')) {
        $out['accounts'] = hire_accounts($db);
    }
    $sql = "SELECT rc.*, cu.name AS customer_name, cu.phone AS customer_phone, cu.customer_code, cu.receivable_balance
            FROM rental_contracts rc
            LEFT JOIN customers cu ON cu.id = rc.customer_id
            WHERE rc.status IN ('ACTIVE','OVERDUE','PAUSED')
            ORDER BY rc.id DESC
            LIMIT 400";
    $res = @$db->query($sql);
    if (!$res) {
        $res = @$db->query("SELECT * FROM rental_contracts WHERE status IN ('ACTIVE','OVERDUE','PAUSED') ORDER BY id DESC LIMIT 400");
    }
    if (!$res) {
        return $out;
    }
    while ($c = $res->fetch_assoc()) {
        $snap = function_exists('hire_due_snapshot')
            ? hire_due_snapshot($db, $c)
            : ['due' => 0.0, 'daily_rate' => (float) ($c['rent_rate'] ?? 0), 'unpaid_days' => 0, 'term_months' => (int) ($c['term_months'] ?? 0), 'term_days' => (int) ($c['term_days'] ?? 0), 'start_date' => (string) ($c['start_date'] ?? '')];
        $due = (float) $snap['due'];
        $follow = trim((string) ($c['follow_date'] ?? ''));
        if ($home) {
            if ($due <= 0.009 && ($follow === '' || $follow > $today)) {
                continue;
            }
        } elseif ($due <= 0.009 && $follow === '') {
            continue;
        }
        // ভবিষ্যতের follow_date সংশোধন করা থাকলে সেই তারিখই দেখাবে — পুরোনো দিনে ফিরে যাবে না।
        $row = [
            'id' => (int) ($c['id'] ?? 0),
            'rental_no' => (string) ($c['rental_no'] ?? ''),
            'item_name' => (string) ($c['item_name'] ?? ''),
            'customer_id' => (int) ($c['customer_id'] ?? 0),
            'customer_name' => (string) ($c['customer_name'] ?? ''),
            'customer_phone' => (string) ($c['customer_phone'] ?? ($c['phone'] ?? '')),
            'customer_code' => (string) ($c['customer_code'] ?? ''),
            'due' => round($due, 2),
            'unpaid_days' => (int) $snap['unpaid_days'],
            'daily_rate' => (float) $snap['daily_rate'],
            'follow_date' => $follow,
            'follow_amount' => isset($c['follow_amount']) && $c['follow_amount'] !== null && $c['follow_amount'] !== ''
                ? (float) $c['follow_amount']
                : null,
            'follow_note' => (string) ($c['follow_note'] ?? ''),
            'term_months' => (int) $snap['term_months'],
            'term_days' => (int) $snap['term_days'],
            'start_date' => (string) ($snap['start_date'] ?? ($c['start_date'] ?? '')),
            'receivable_balance' => (float) ($c['receivable_balance'] ?? 0),
        ];
        $meta = hire_due_row_badge($row, $today);
        $row['badge'] = $meta;
        if ($meta['kind'] === 'overdue') {
            $out['overdue_count']++;
        } elseif ($meta['kind'] === 'today') {
            $out['today_count']++;
        } else {
            $out['upcoming_count']++;
        }
        if ($due > 0.009) {
            $out['unpaid_count']++;
            $out['due'] += $due;
            $out['unpaid_days'] += (int) $snap['unpaid_days'];
        }
        $out['rows'][] = $row;
    }
    usort($out['rows'], static function ($a, $b) {
        $rank = ['overdue' => 0, 'today' => 1, 'upcoming' => 2];
        $ra = $rank[$a['badge']['kind'] ?? 'upcoming'] ?? 3;
        $rb = $rank[$b['badge']['kind'] ?? 'upcoming'] ?? 3;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        return ($b['unpaid_days'] ?? 0) <=> ($a['unpaid_days'] ?? 0);
    });
    $out['count'] = count($out['rows']);
    $out['due'] = round($out['due'], 2);
    $out['badge'] = $out['overdue_count'] + $out['today_count'];
    if ($out['badge'] === 0) {
        $out['badge'] = $out['unpaid_count'];
    }
    return $out;
}

function hire_due_save_follow(mysqli $db, int $contract_id, string $date, ?float $amount, string $note): void
{
    if ($contract_id <= 0) {
        throw new RuntimeException('চুক্তি সিলেক্ট করা নেই।');
    }
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new RuntimeException('তারিখ দেওয়া আবশ্যক।');
    }
    $noteVal = $note !== '' ? $note : null;
    if ($amount === null) {
        $stmt = $db->prepare('UPDATE rental_contracts SET follow_date = ?, follow_amount = NULL, follow_note = ? WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('সংরক্ষণ যায়নি।');
        }
        $stmt->bind_param('ssi', $date, $noteVal, $contract_id);
    } else {
        $stmt = $db->prepare('UPDATE rental_contracts SET follow_date = ?, follow_amount = ?, follow_note = ? WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('সংরক্ষণ যায়নি।');
        }
        $stmt->bind_param('sdsi', $date, $amount, $noteVal, $contract_id);
    }
    $stmt->execute();
    $stmt->close();
    if (function_exists('hire_history')) {
        hire_history($db, $contract_id, 'follow', '', $date . ' · ' . ($amount ?? ''), $noteVal ?? 'পরবর্তী তারিখ');
    }
}

function hire_due_footer_inject(): void
{
    // Rent due is part of the main customer balance. The separate hire banner
    // is not shown; modules/customers/due-reminder.php is the only due notice.
    return;
    if (!isset($GLOBALS['db']) || !($GLOBALS['db'] instanceof mysqli)) {
        return;
    }
    $db = $GLOBALS['db'];
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $is_home = ($script === '/index.php' || $script === '/' || $script === '');
    $on_reminder = str_contains($script, '/customers/hire/due-reminder.php');
    if (!hire_due_notify_try_load($db)) {
        return;
    }
    if (function_exists('hire_can') && !hire_can($db, 'view')) {
        return;
    }
    try {
        $payload = hire_due_notify_rows($db, false);
    } catch (Throwable $e) {
        error_log('hire due rows: ' . $e->getMessage());
        return;
    }
    $ver = htmlspecialchars(
        defined('AME_ASSET_VERSION') ? (string) AME_ASSET_VERSION : (string) ($GLOBALS['sys_version'] ?? '1.0'),
        ENT_QUOTES,
        'UTF-8'
    );
    echo '<link rel="stylesheet" href="/assets/css/hire-due-home.css?v=' . $ver . '">' . "\n";
    $home_rows = [];
    $today = $payload['today'];
    foreach ($payload['rows'] as $row) {
        $follow = (string) ($row['follow_date'] ?? '');
        $due = (float) ($row['due'] ?? 0);
        if ($due > 0.009 || ($follow !== '' && $follow <= $today)) {
            $home_rows[] = $row;
        }
    }
    if (!$on_reminder) {
        $home_payload = $payload;
        $home_payload['rows'] = $is_home ? $home_rows : [];
        $home_payload['home'] = $is_home;
        echo '<script>window.AME_HIRE_DUE=' . json_encode($home_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
    }
    echo '<script>window.AME_HIRE_DUE_BADGE=' . json_encode([
        'count' => (int) $payload['badge'],
        'due' => $payload['due'],
        'unpaid_days' => $payload['unpaid_days'],
        'href' => '/modules/customers/hire/due-reminder.php',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
    if ($is_home) {
        include __DIR__ . '/../modules/customers/hire/due-reminder-edit-modal.php';
    }
    if (!$on_reminder) {
        echo '<script src="/assets/js/hire-due-home.js?v=' . $ver . '" defer></script>' . "\n";
    }
}
