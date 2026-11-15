<?php
/**
 * Partner statement pack (intl-100) — read-only views; totals reconcile with pa_partner_book().
 * INVARIANT: display never writes balances; book['plus'/'minus'/'total'] is source of truth for summary.
 */

declare(strict_types=1);

const PA_STMT_PACK_VER = '10.98.937';
const PA_STMT_DAYS_PER_PAGE = 15;

/**
 * @return array{ym:string,partner_id:int,bounds:array,dash:array,partner:array|null,book:array|null,error:string}
 */
function pa_stmt_pack_context(mysqli $db, string $ym, int $partnerId): array
{
    $ym = pa_month($ym)['ym'];
    $dash = pa_dashboard($db, $ym);
    $bounds = $dash['month']['bounds'];
    $ctx = [
        'ym' => $ym,
        'partner_id' => $partnerId,
        'bounds' => $bounds,
        'dash' => $dash,
        'partner' => null,
        'book' => null,
        'error' => '',
    ];
    if ($partnerId < 1) {
        $ctx['error'] = 'পার্টনার বাছুন।';
        return $ctx;
    }
    $partner = pa_find_partner($db, $partnerId);
    if (!$partner) {
        $ctx['error'] = 'পার্টনার পাওয়া যায়নি।';
        return $ctx;
    }
    $ctx['partner'] = $partner;
    $ctx['book'] = pa_partner_book($db, $partner, $ym);
    return $ctx;
}

function pa_stmt_date_show(string $iso): string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
        return $iso;
    }
    return date('d-m-Y', strtotime($iso));
}

function pa_stmt_days_per_page(): int
{
    return PA_STMT_DAYS_PER_PAGE;
}

/** @deprecated alias — pagination unit is **days**, not row count */
function pa_stmt_rows_per_page(): int
{
    return PA_STMT_DAYS_PER_PAGE;
}

function pa_stmt_nav_href(string $script, string $ym, int $partnerId, int $page = 0): string
{
    $base = basename($script);
    $q = 'month=' . rawurlencode($ym);
    if ($partnerId > 0) {
        $q .= '&partner_id=' . $partnerId;
    }
    if ($page > 1) {
        $q .= '&pg=' . $page;
    }
    return $base . '?' . $q;
}

/**
 * Group ledger lines by calendar day (newest day first).
 *
 * @param list<array<string,mixed>> $lines
 * @return list<array{day:string,show:string,lines:list<array<string,mixed>>}>
 */
function pa_stmt_book_lines_by_day(array $lines): array
{
    $by = [];
    foreach ($lines as $line) {
        $day = (string)($line['date'] ?? '—');
        $by[$day][] = $line;
    }
    krsort($by);
    $out = [];
    foreach ($by as $day => $dayLines) {
        $out[] = [
            'day' => $day,
            'show' => pa_stmt_date_show($day),
            'lines' => $dayLines,
        ];
    }
    return $out;
}

/**
 * Paginate **day groups** (15 days per page).
 *
 * @param list<array{day:string,show:string,lines:list<mixed>,subtotal?:float}> $dayGroups
 * @return array{days:list<array>,page:int,pages:int,total:int,per:int,unit:string}
 */
function pa_stmt_page_slice_days(array $dayGroups, int $page): array
{
    $per = pa_stmt_days_per_page();
    $total = count($dayGroups);
    $pages = max(1, (int)ceil($total / $per));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $per;
    return [
        'days' => array_slice($dayGroups, $offset, $per),
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'per' => $per,
        'unit' => 'day',
    ];
}

/** Line serial number at start of a day-paginated page. */
function pa_stmt_serial_base(array $allDayGroups, int $page): int
{
    $per = pa_stmt_days_per_page();
    $skipDays = max(0, ($page - 1) * $per);
    $serial = 1;
    for ($d = 0; $d < $skipDays && $d < count($allDayGroups); $d++) {
        $serial += count($allDayGroups[$d]['lines']);
    }
    return $serial;
}

function pa_stmt_serial_label(int $n): string
{
    return (string)$n;
}

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array{day:string,show:string,lines:list<array<string,mixed>>,subtotal:float}>
 */
function pa_stmt_group_days(array $rows, string $amountKey, string $dateKey = 'date'): array
{
    $by = [];
    foreach ($rows as $row) {
        $day = (string)($row[$dateKey] ?? '');
        if ($day === '') {
            $day = '—';
        }
        $by[$day][] = $row;
    }
    krsort($by);
    $out = [];
    foreach ($by as $day => $lines) {
        $sub = 0.0;
        foreach ($lines as $ln) {
            $sub = pa_money($sub + (float)($ln[$amountKey] ?? 0));
        }
        $out[] = [
            'day' => $day,
            'show' => pa_stmt_date_show($day),
            'lines' => $lines,
            'subtotal' => $sub,
        ];
    }
    return $out;
}

function pa_stmt_outflow_type_label(string $kind, string $sub = ''): string
{
    if ($kind === 'expense') {
        return 'পার্টনারশিপ খরচ';
    }
    if ($kind === 'payment' || $sub === 'withdrawal') {
        return 'উত্তোলন / পরিশোধ';
    }
    if ($kind === 'capital') {
        return 'মূলধন উত্তোলন';
    }
    if ($kind === 'adjust') {
        return 'সমন্বয়';
    }
    return 'অন্যান্য মাইনাস';
}

/**
 * All minus lines from the partner book — single source, reconciles to book['minus'].
 *
 * @return array{lines:list<array<string,mixed>>,total:float,by_type:array<string,float>}
 */
function pa_stmt_outflow_from_book(array $book): array
{
    $lines = [];
    $byType = ['expense' => 0.0, 'withdrawal' => 0.0, 'capital' => 0.0, 'other' => 0.0];
    foreach ($book['lines'] as $line) {
        $amt = pa_money($line['minus'] ?? 0);
        if ($amt < 0.009) {
            continue;
        }
        $kind = (string)($line['kind'] ?? '');
        $sub = (string)($line['sub'] ?? '');
        $bucket = 'other';
        if ($kind === 'expense') {
            $bucket = 'expense';
        } elseif ($kind === 'payment' || $sub === 'withdrawal') {
            $bucket = 'withdrawal';
        } elseif ($kind === 'capital') {
            $bucket = 'capital';
        }
        $byType[$bucket] = pa_money($byType[$bucket] + $amt);
        $lines[] = [
            'date' => (string)($line['date'] ?? ''),
            'kind' => $kind,
            'sub' => $sub,
            'type' => $bucket,
            'type_label' => pa_stmt_outflow_type_label($kind, $sub),
            'description' => (string)($line['description'] ?? ''),
            'reason' => (string)($line['minus_reason'] ?? ''),
            'amount' => $amt,
        ];
    }
    usort($lines, static function ($a, $b) {
        $c = strcmp((string)$b['date'], (string)$a['date']);
        return $c !== 0 ? $c : strcmp((string)$a['type_label'], (string)$b['type_label']);
    });
    $total = 0.0;
    foreach ($lines as $ln) {
        $total = pa_money($total + (float)$ln['amount']);
    }
    return [
        'lines' => $lines,
        'total' => $total,
        'by_type' => $byType,
        'book_minus' => pa_money($book['minus'] ?? 0),
        'reconciled' => abs($total - pa_money($book['minus'] ?? 0)) < 0.02,
    ];
}

/**
 * Product-level profit shares for one partner; split_rows targets book['plus'].
 *
 * @return array{lines:list<array<string,mixed>>,total:float,computed:float,reconciled:bool,more:bool}
 */
function pa_stmt_profit_for_partner(mysqli $db, array $partner, array $bounds, array $book): array
{
    $pid = (int)($partner['id'] ?? 0);
    $target = pa_money($book['plus'] ?? 0);
    $ledger = pa_ledger_sale_lines($db, $bounds['from'], $bounds['to'], 2001);
    $raw = $ledger['lines'];
    $more = (bool)($ledger['more'] ?? false);
    if ($raw === [] && $target > 0.009) {
        foreach ($book['lines'] as $line) {
            if (($line['kind'] ?? '') !== 'profit') {
                continue;
            }
            $p = pa_money($line['plus'] ?? 0);
            if ($p < 0.009) {
                continue;
            }
            $raw[] = [
                'invoice_date' => (string)($line['date'] ?? ''),
                'invoice_no' => '',
                'customer_name' => '',
                'customer_code' => '',
                'customer_id' => 0,
                'product_name' => (string)($line['description'] ?? 'লাভ'),
                'profit' => $p,
                'sale_amount' => 0.0,
            ];
        }
    }
    $amounts = [];
    foreach ($raw as $i => $row) {
        $amounts[$i] = pa_money($row['profit'] ?? 0);
    }
    $pcts = pa_active_share_pcts($db);
    $splits = $amounts !== [] ? pa_split_rows($amounts, $pcts, 'gross', [$pid => $target]) : [];
    $lines = [];
    $computed = 0.0;
    foreach ($raw as $i => $row) {
        $share = pa_money($splits[$i][$pid] ?? 0);
        if (abs($share) < 0.009) {
            continue;
        }
        $computed = pa_money($computed + $share);
        $code = trim((string)($row['customer_code'] ?? ''));
        $cid = (int)($row['customer_id'] ?? 0);
        $who = trim((string)($row['customer_name'] ?? ''));
        if ($code !== '') {
            $whoLabel = $code . ($who !== '' ? ' · ' . $who : '');
        } elseif ($cid > 0) {
            $whoLabel = '#' . $cid . ($who !== '' ? ' · ' . $who : '');
        } else {
            $whoLabel = $who !== '' ? $who : '—';
        }
        $lines[] = [
            'date' => (string)($row['invoice_date'] ?? ''),
            'customer_label' => $whoLabel,
            'product_name' => trim((string)($row['product_name'] ?? '')),
            'invoice_no' => trim((string)($row['invoice_no'] ?? '')),
            'sale_amount' => pa_money($row['sale_amount'] ?? 0),
            'profit_share' => $share,
        ];
    }
    return [
        'lines' => $lines,
        'total' => $target,
        'computed' => $computed,
        'reconciled' => abs($computed - $target) < 0.02,
        'more' => $more,
    ];
}

/**
 * @return array{opening:float,plus:float,minus:float,total:float,by_type:array<string,float>,formula:string}
 */
function pa_stmt_summary_figures(array $book, array $outflow): array
{
    $opening = pa_money($book['opening'] ?? 0);
    $plus = pa_money($book['plus'] ?? 0);
    $minus = pa_money($book['minus'] ?? 0);
    $total = pa_money($book['total'] ?? 0);
    $by = $outflow['by_type'] ?? [];
    $formula = pa_taka($opening) . ' + ' . pa_book_signed($plus, '+') . ' − ' . pa_book_signed($minus, '-') . ' = ' . pa_taka($total);
    return [
        'opening' => $opening,
        'plus' => $plus,
        'minus' => $minus,
        'total' => $total,
        'by_type' => $by,
        'formula' => $formula,
        'reconciled' => (bool)($outflow['reconciled'] ?? false),
    ];
}