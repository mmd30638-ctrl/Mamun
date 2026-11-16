<?php
/**
 * সব চুক্তি তালিকা — JOIN ছাড়া সরাসরি rental_contracts।
 * কাস্টমার না থাকলেও সারি বাদ হয় না।
 */

function hire_page_is_live(string $status): bool
{
    return in_array(strtoupper(trim($status)), ['ACTIVE', 'OVERDUE', 'PAUSED'], true);
}

function hire_page_is_done(string $status): bool
{
    return in_array(strtoupper(trim($status)), ['COMPLETED', 'RETURNED'], true);
}

function hire_page_load_contracts(mysqli $db): array
{
    $rows = [];
    $res = @$db->query('SELECT * FROM rental_contracts ORDER BY id DESC LIMIT 500');
    if (!$res) {
        return $rows;
    }
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    if ($rows === []) {
        return $rows;
    }
    $ids = [];
    foreach ($rows as $r) {
        $id = (int) ($r['customer_id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $cust = [];
    if ($ids !== []) {
        $q = @$db->query('SELECT id, name, phone FROM customers WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
        if ($q) {
            while ($c = $q->fetch_assoc()) {
                $cust[(int) $c['id']] = $c;
            }
        }
    }
    foreach ($rows as &$r) {
        $c = $cust[(int) ($r['customer_id'] ?? 0)] ?? null;
        $r['customer_name'] = (string) ($c['name'] ?? '');
        $r['customer_phone'] = (string) ($c['phone'] ?? '');
        $r['_st'] = strtoupper(trim((string) ($r['status'] ?? '')));
        $r['_live'] = hire_page_is_live($r['_st']);
        $r['_start'] = substr((string) ($r['start_date'] ?? $r['created_at'] ?? ''), 0, 10);
    }
    unset($r);
    return $rows;
}

function hire_page_contract_counts(array $all): array
{
    $counts = ['all' => count($all), 'live' => 0, 'INACTIVE' => 0, 'COMPLETED' => 0, 'CANCELLED' => 0];
    foreach ($all as $r) {
        $st = (string) ($r['_st'] ?? '');
        if (hire_page_is_live($st)) {
            $counts['live']++;
        }
        if ($st === 'INACTIVE') {
            $counts['INACTIVE']++;
        }
        if (hire_page_is_done($st)) {
            $counts['COMPLETED']++;
        }
        if ($st === 'CANCELLED') {
            $counts['CANCELLED']++;
        }
    }
    return $counts;
}

function hire_page_filter_contracts(array $all, string $status, string $q, string $from, string $to, int $cid): array
{
    $q = trim($q);
    $qLow = $q === '' ? '' : (function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q));
    $out = [];
    foreach ($all as $r) {
        $st = (string) ($r['_st'] ?? '');
        if ($cid > 0 && (int) ($r['customer_id'] ?? 0) !== $cid) {
            continue;
        }
        if ($status === 'live' && empty($r['_live'])) {
            continue;
        } elseif ($status === 'COMPLETED' && !hire_page_is_done($st)) {
            continue;
        } elseif ($status !== '' && $status !== 'live' && $status !== 'COMPLETED' && $st !== $status) {
            continue;
        }
        $start = (string) ($r['_start'] ?? '');
        if ($from !== '' && $start !== '' && $start < $from) {
            continue;
        }
        if ($to !== '' && $start !== '' && $start > $to) {
            continue;
        }
        if ($qLow !== '') {
            $hay = trim(
                ($r['rental_no'] ?? '') . ' ' .
                ($r['item_name'] ?? '') . ' ' .
                ($r['customer_name'] ?? '') . ' ' .
                ($r['customer_phone'] ?? '') . ' ' .
                ($r['serial_no'] ?? '') . ' ' .
                ($r['vehicle_no'] ?? '')
            );
            $hayL = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);
            if (!str_contains($hayL, $qLow)) {
                continue;
            }
        }
        $out[] = $r;
    }
    usort($out, static function ($a, $b) {
        $la = !empty($a['_live']) ? 0 : 1;
        $lb = !empty($b['_live']) ? 0 : 1;
        if ($la !== $lb) {
            return $la <=> $lb;
        }
        return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });
    return $out;
}
