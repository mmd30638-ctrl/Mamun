<?php
/**
 * Live operator work desk — who did what, by day / date / month / year.
 */

function ame_live_work_allowed(?mysqli $db = null): bool
{
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if (in_array($role, ['admin', 'superadmin', 'owner', 'manager', 'accountant'], true)) {
        return true;
    }
    if (function_exists('ame_is_admin_like') && ame_is_admin_like()) {
        return true;
    }
    if (function_exists('ame_hrm_is_admin') && ame_hrm_is_admin()) {
        return true;
    }
    if ($db instanceof mysqli && function_exists('has_permission')) {
        return has_permission($db, 'm-acc') || has_permission($db, 'm-sales-report');
    }
    return false;
}

function ame_live_work_guard(?mysqli $db = null): void
{
    if (!ame_live_work_allowed($db)) {
        echo "<script>alert('অনুমতি নেই!'); window.location='/index.php';</script>";
        exit;
    }
}

function ame_live_work_today(): string
{
    return date('Y-m-d');
}

function ame_live_work_span(array $in): array
{
    $today = ame_live_work_today();
    $mode = strtolower(trim((string)($in['mode'] ?? 'live')));
    $date = trim((string)($in['date'] ?? ''));
    $month = trim((string)($in['month'] ?? ''));
    $year = trim((string)($in['year'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = $today;
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = substr($today, 0, 7);
    }
    if (!preg_match('/^\d{4}$/', $year)) {
        $year = substr($today, 0, 4);
    }
    $live = false;
    $chart = 'hours';
    switch ($mode) {
        case 'yesterday':
            $d = date('Y-m-d', strtotime('-1 day'));
            $from = $to = $d;
            $date = $d;
            break;
        case 'week':
            $from = date('Y-m-d', strtotime('monday this week'));
            $to = $today;
            $chart = 'days';
            break;
        case 'month':
            $from = $month . '-01';
            $to = date('Y-m-t', strtotime($from));
            $chart = 'days';
            $date = $from;
            break;
        case 'year':
            $from = $year . '-01-01';
            $to = $year . '-12-31';
            $chart = 'months';
            break;
        case 'date':
            $from = $to = $date;
            break;
        case 'live':
        case 'today':
        default:
            $mode = $mode === 'today' ? 'today' : 'live';
            $live = $mode === 'live';
            $from = $to = $today;
            $date = $today;
            break;
    }
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $fromTs = $from . ' 00:00:00';
    $toTs = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
    return [
        'mode' => $mode,
        'live' => $live,
        'from' => $from,
        'to' => $to,
        'from_ts' => $fromTs,
        'to_ts' => $toTs,
        'date' => $date,
        'month' => $month,
        'year' => $year,
        'chart' => $chart,
        'single_day' => $from === $to,
    ];
}

function ame_live_work_module_bn(string $module): string
{
    $k = function_exists('ame_audit_module_key') ? ame_audit_module_key($module) : strtolower($module);
    $map = [
        'sales' => 'বিক্রয়',
        'purchase' => 'ক্রয়',
        'product' => 'পণ্য',
        'stock' => 'স্টক',
        'customer' => 'ক্রেতা',
        'supplier' => 'সাপ্লায়ার',
        'accounts' => 'হিসাব',
        'expense' => 'খরচ',
        'settings' => 'সেটিংস',
        'returns' => 'ফেরত',
        'hrm' => 'কর্মী',
        'loans' => 'ঋণ',
        'family' => 'পরিবার',
        'workshop' => 'ওয়ার্কশপ',
        'reports' => 'রিপোর্ট',
        'security' => 'লগইন',
    ];
    return $map[$k] ?? ($module !== '' ? $module : 'অন্যান্য');
}

function ame_live_work_people(mysqli $db): array
{
    $rows = [];
    $sql = "SELECT u.id, u.username, u.role, u.status, u.employee_id,
                   e.name AS emp_name, e.employee_code, e.phone, e.photo, e.designation
            FROM users u
            LEFT JOIN employees e ON e.id = u.employee_id
            WHERE COALESCE(u.status, 'active') <> 'deleted'
            ORDER BY COALESCE(NULLIF(e.name,''), u.username) ASC";
    $res = @$db->query($sql);
    if (!$res) {
        $res = @$db->query('SELECT id, username, role, status, employee_id FROM users ORDER BY username ASC');
    }
    if (!$res) {
        return $rows;
    }
    while ($u = $res->fetch_assoc()) {
        $name = trim((string)($u['emp_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($u['username'] ?? ''));
        }
        if ($name === '') {
            $name = '#' . (int)$u['id'];
        }
        $code = trim((string)($u['employee_code'] ?? ''));
        $photo = '';
        if (function_exists('ame_emp_hub_photo_url') && !empty($u['photo'])) {
            $photo = ame_emp_hub_photo_url($u['photo']);
        }
        $rows[] = [
            'id' => (int)$u['id'],
            'employee_id' => (int)($u['employee_id'] ?? 0),
            'name' => $name,
            'username' => (string)($u['username'] ?? ''),
            'role' => (string)($u['role'] ?? ''),
            'code' => $code,
            'phone' => (string)($u['phone'] ?? ''),
            'designation' => (string)($u['designation'] ?? ''),
            'photo' => $photo,
            'label' => $code !== '' ? ($code . ' · #' . (int)$u['id']) : ('#' . (int)$u['id']),
        ];
    }
    return $rows;
}

function ame_live_work_payload(mysqli $db, array $in): array
{
    if (is_file(__DIR__ . '/audit_log.php')) {
        require_once __DIR__ . '/audit_log.php';
    }
    $span = ame_live_work_span($in);
    $userId = (int)($in['user'] ?? $in['user_id'] ?? 0);
    $people = ame_live_work_people($db);
    $byId = [];
    foreach ($people as $p) {
        $byId[$p['id']] = $p;
    }

    $hours = array_fill(0, 24, 0);
    $days = [];
    $months = array_fill(1, 12, 0);
    $stats = [];
    $events = [];
    $total = 0;
    $liveN = 0;
    $money = 0.0;
    $salesN = 0;
    $salesAmt = 0.0;

    $fromTs = $span['from_ts'];
    $toTs = $span['to_ts'];
    $userSql = $userId > 0 ? ' AND l.user_id = ' . $userId : '';

    $cols = function_exists('ame_audit_table_columns') ? ame_audit_table_columns($db) : [];
    $hasAmt = isset($cols['amount_total']);
    $amtSel = $hasAmt ? 'SUM(CASE WHEN l.amount_total IS NULL THEN 0 ELSE l.amount_total END) AS amt' : '0 AS amt';

    $agg = @$db->query(
        "SELECT l.user_id,
                COUNT(*) AS n,
                MIN(l.created_at) AS first_at,
                MAX(l.created_at) AS last_at,
                {$amtSel}
         FROM activity_logs l
         WHERE l.created_at >= '" . $db->real_escape_string($fromTs) . "'
           AND l.created_at < '" . $db->real_escape_string($toTs) . "'
           {$userSql}
         GROUP BY l.user_id"
    );
    if ($agg) {
        while ($r = $agg->fetch_assoc()) {
            $uid = (int)$r['user_id'];
            $n = (int)$r['n'];
            $total += $n;
            $stats[$uid] = [
                'n' => $n,
                'first_at' => (string)$r['first_at'],
                'last_at' => (string)$r['last_at'],
                'amt' => round((float)$r['amt'], 2),
                'sales_n' => 0,
                'sales_amt' => 0.0,
            ];
            $money += (float)$r['amt'];
        }
    }

    $hourQ = @$db->query(
        "SELECT HOUR(l.created_at) h, COUNT(*) n
         FROM activity_logs l
         WHERE l.created_at >= '" . $db->real_escape_string($fromTs) . "'
           AND l.created_at < '" . $db->real_escape_string($toTs) . "'
           {$userSql}
         GROUP BY HOUR(l.created_at)"
    );
    if ($hourQ && $span['single_day']) {
        while ($r = $hourQ->fetch_assoc()) {
            $h = (int)$r['h'];
            if ($h >= 0 && $h <= 23) {
                $hours[$h] = (int)$r['n'];
            }
        }
    }

    if ($span['chart'] === 'days') {
        $dayQ = @$db->query(
            "SELECT DATE(l.created_at) d, COUNT(*) n
             FROM activity_logs l
             WHERE l.created_at >= '" . $db->real_escape_string($fromTs) . "'
               AND l.created_at < '" . $db->real_escape_string($toTs) . "'
               {$userSql}
             GROUP BY DATE(l.created_at)
             ORDER BY d ASC"
        );
        if ($dayQ) {
            while ($r = $dayQ->fetch_assoc()) {
                $days[] = ['d' => (string)$r['d'], 'n' => (int)$r['n']];
            }
        }
    }
    if ($span['chart'] === 'months') {
        $monQ = @$db->query(
            "SELECT MONTH(l.created_at) m, COUNT(*) n
             FROM activity_logs l
             WHERE l.created_at >= '" . $db->real_escape_string($fromTs) . "'
               AND l.created_at < '" . $db->real_escape_string($toTs) . "'
               {$userSql}
             GROUP BY MONTH(l.created_at)"
        );
        if ($monQ) {
            while ($r = $monQ->fetch_assoc()) {
                $m = (int)$r['m'];
                if ($m >= 1 && $m <= 12) {
                    $months[$m] = (int)$r['n'];
                }
            }
        }
    }

    $saleUser = $userId > 0 ? ' AND created_by = ' . $userId : '';
    $saleQ = @$db->query(
        "SELECT created_by, COUNT(*) n, COALESCE(SUM(net_payable),0) amt
         FROM sales
         WHERE COALESCE(sale_date, created_at) >= '" . $db->real_escape_string($fromTs) . "'
           AND COALESCE(sale_date, created_at) < '" . $db->real_escape_string($toTs) . "'
           AND (status IS NULL OR status NOT IN ('draft','cancelled','canceled'))
           {$saleUser}
         GROUP BY created_by"
    );
    if ($saleQ) {
        while ($r = $saleQ->fetch_assoc()) {
            $uid = (int)$r['created_by'];
            if ($uid <= 0) {
                continue;
            }
            if (!isset($stats[$uid])) {
                $stats[$uid] = [
                    'n' => 0,
                    'first_at' => '',
                    'last_at' => '',
                    'amt' => 0.0,
                    'sales_n' => 0,
                    'sales_amt' => 0.0,
                ];
            }
            $stats[$uid]['sales_n'] = (int)$r['n'];
            $stats[$uid]['sales_amt'] = round((float)$r['amt'], 2);
            $salesN += (int)$r['n'];
            $salesAmt += (float)$r['amt'];
        }
    }

    $evBits = ['l.id', 'l.user_id', 'l.action', 'l.module', 'l.details', 'l.created_at', 'u.username'];
    foreach (['action_type', 'reference', 'party_name', 'product_name'] as $c) {
        if (isset($cols[$c])) {
            $evBits[] = 'l.' . $c;
        }
    }
    if ($hasAmt) {
        $evBits[] = 'l.amount_total';
    }
    $evSql = 'SELECT ' . implode(', ', $evBits) . '
              FROM activity_logs l
              LEFT JOIN users u ON u.id = l.user_id
              WHERE l.created_at >= ? AND l.created_at < ?' . ($userId > 0 ? ' AND l.user_id = ?' : '') . '
              ORDER BY l.id DESC LIMIT 80';
    $ev = $db->prepare($evSql);
    if ($ev) {
        if ($userId > 0) {
            $ev->bind_param('ssi', $fromTs, $toTs, $userId);
        } else {
            $ev->bind_param('ss', $fromTs, $toTs);
        }
        $ev->execute();
        $res = $ev->get_result();
        while ($r = $res->fetch_assoc()) {
            $uid = (int)$r['user_id'];
            $who = $byId[$uid]['name'] ?? trim((string)($r['username'] ?? ''));
            if ($who === '') {
                $who = $uid > 0 ? ('#' . $uid) : 'সিস্টেম';
            }
            $type = trim((string)($r['action_type'] ?? ''));
            if ($type === '' && function_exists('ame_audit_action_type')) {
                $type = ame_audit_action_type((string)$r['action']);
            }
            $title = function_exists('ame_audit_label_action')
                ? ame_audit_label_action($type, (string)$r['action'])
                : (string)$r['action'];
            $detail = trim((string)($r['details'] ?? ''));
            if ($detail === '' && !empty($r['party_name'])) {
                $detail = (string)$r['party_name'];
            }
            if ($detail === '' && !empty($r['product_name'])) {
                $detail = (string)$r['product_name'];
            }
            if ($detail === '' && !empty($r['reference'])) {
                $detail = (string)$r['reference'];
            }
            $events[] = [
                'id' => (int)$r['id'],
                'user_id' => $uid,
                'who' => $who,
                'code' => $byId[$uid]['label'] ?? '',
                'title' => $title,
                'action' => (string)$r['action'],
                'type' => $type !== '' ? $type : 'other',
                'module' => ame_live_work_module_bn((string)$r['module']),
                'detail' => $detail,
                'amount' => isset($r['amount_total']) ? round((float)$r['amount_total'], 2) : null,
                'at' => (string)$r['created_at'],
                'clock' => $r['created_at'] ? date('h:i A', strtotime((string)$r['created_at'])) : '',
            ];
        }
        $ev->close();
    }

    $now = time();
    $board = [];
    foreach ($people as $p) {
        $st = $stats[$p['id']] ?? [
            'n' => 0, 'first_at' => '', 'last_at' => '', 'amt' => 0.0, 'sales_n' => 0, 'sales_amt' => 0.0,
        ];
        $lastTs = $st['last_at'] !== '' ? strtotime($st['last_at']) : 0;
        $isLive = $lastTs > 0 && ($now - $lastTs) <= 180;
        if ($isLive) {
            $liveN++;
        }
        if ($userId > 0 && $p['id'] !== $userId) {
            continue;
        }
        $ini = '';
        $parts = preg_split('/\s+/u', $p['name']) ?: [];
        foreach ($parts as $w) {
            if ($w === '') {
                continue;
            }
            $ini .= mb_substr($w, 0, 1);
            if (mb_strlen($ini) >= 2) {
                break;
            }
        }
        $board[] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'label' => $p['label'],
            'role' => $p['role'],
            'designation' => $p['designation'],
            'photo' => $p['photo'],
            'initials' => $ini !== '' ? $ini : '·',
            'n' => (int)$st['n'],
            'sales_n' => (int)$st['sales_n'],
            'sales_amt' => round((float)$st['sales_amt'], 2),
            'amt' => round((float)$st['amt'], 2),
            'first_at' => (string)$st['first_at'],
            'last_at' => (string)$st['last_at'],
            'last_clock' => $st['last_at'] !== '' ? date('h:i A', strtotime($st['last_at'])) : '',
            'live' => $isLive,
        ];
    }
    usort($board, static function ($a, $b) {
        if ($a['live'] !== $b['live']) {
            return $a['live'] ? -1 : 1;
        }
        if ($a['n'] !== $b['n']) {
            return $b['n'] <=> $a['n'];
        }
        return strcasecmp($a['name'], $b['name']);
    });

    $hourMax = max($hours) ?: 1;
    $hourRows = [];
    for ($h = 0; $h < 24; $h++) {
        $hourRows[] = [
            'h' => $h,
            'label' => sprintf('%02d', $h),
            'n' => $hours[$h],
            'pct' => (int)round(100 * $hours[$h] / $hourMax),
        ];
    }
    $monthNames = ['', 'জানু', 'ফেব্রু', 'মার্চ', 'এপ্রি', 'মে', 'জুন', 'জুলা', 'আগস্ট', 'সেপ্ট', 'অক্টো', 'নভে', 'ডিসে'];
    $monthRows = [];
    $monMax = max($months) ?: 1;
    for ($m = 1; $m <= 12; $m++) {
        $monthRows[] = [
            'm' => $m,
            'label' => $monthNames[$m],
            'n' => $months[$m],
            'pct' => (int)round(100 * $months[$m] / $monMax),
        ];
    }
    $dayMax = 1;
    foreach ($days as $d) {
        $dayMax = max($dayMax, (int)$d['n']);
    }
    foreach ($days as $i => $d) {
        $days[$i]['pct'] = (int)round(100 * $d['n'] / $dayMax);
        $days[$i]['label'] = date('d', strtotime($d['d']));
    }

    $active = 0;
    foreach ($board as $p) {
        if ($p['n'] > 0 || $p['sales_n'] > 0) {
            $active++;
        }
    }

    return [
        'ok' => true,
        'now' => date('Y-m-d H:i:s'),
        'now_clock' => date('h:i:s A'),
        'now_bangla' => date('l, d F Y'),
        'span' => $span,
        'user' => $userId,
        'kpis' => [
            'people' => count($board),
            'active' => $active,
            'live' => $liveN,
            'actions' => $total,
            'sales_n' => $salesN,
            'sales_amt' => round($salesAmt, 2),
        ],
        'people' => $board,
        'hours' => $hourRows,
        'days' => $days,
        'months' => $monthRows,
        'events' => $events,
        'empty' => $total === 0 && $salesN === 0,
        'directory' => array_map(static function ($p) {
            return [
                'id' => $p['id'],
                'name' => $p['name'],
                'label' => $p['label'],
            ];
        }, $people),
    ];
}
