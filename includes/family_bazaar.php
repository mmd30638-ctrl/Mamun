<?php
/**
 * পারিবারিক মাসিক বাজার চাহিদা — হিসাব ও মেমো।
 */

function fam_bazaar_month_norm(?string $ym = null): string
{
    $ym = trim((string) ($ym ?? ''));
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        $ym = date('Y-m');
    }
    $y = (int) substr($ym, 0, 4);
    $m = (int) substr($ym, 5, 2);
    if ($y < 2000 || $y > 2100 || $m < 1 || $m > 12) {
        return date('Y-m');
    }
    return sprintf('%04d-%02d', $y, $m);
}

function fam_bazaar_month_label(string $ym): string
{
    $ym = fam_bazaar_month_norm($ym);
    $bn = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
    $en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $i = (int) substr($ym, 5, 2) - 1;
    $name = (function_exists('is_english') && is_english()) ? ($en[$i] ?? $ym) : ($bn[$i] ?? $ym);
    return $name . ' ' . substr($ym, 0, 4);
}

function fam_bazaar_cat_ok(string $cat): bool
{
    return in_array($cat, fam_bazaar_cats(), true);
}

function fam_bazaar_norm_cat(string $cat): string
{
    $cat = trim($cat);
    return fam_bazaar_cat_ok($cat) ? $cat : '';
}

function fam_bazaar_unit_ok(string $unit): bool
{
    $unit = trim($unit);
    if ($unit === '' || $unit === 'পরিমাণমতো') {
        return true;
    }
    return in_array($unit, fam_bazaar_units(), true);
}

function fam_bazaar_bn_num(int $n): string
{
    return strtr((string) $n, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
}

function fam_bazaar_qty_txt(array $row): string
{
    return trim((string) ($row['qty'] ?? '') . ' ' . (string) ($row['unit'] ?? ''));
}

/** @return list<array{name:string,qty:string,unit:string,category:string,notes:string}> */
function fam_bazaar_house_list(): array
{
    $rows = [
        ['লাল চিনি', '৫', 'কেজি', '', ''],
        ['লবণ', '৩', 'কেজি', '', ''],
        ['সাদা আটা', '১', 'কেজি', '', ''],
        ['পাম তেল', '৫', 'লিটার', '', ''],
        ['কালোজিরার তেল', '২০০', 'গ্রাম', '', ''],
        ['গুঁড়া দুধ', '৫০০', 'গ্রাম', '', ''],
        ['চাপাতা', '২৫০', 'গ্রাম', '', ''],
        ['নাগাল খেজুর', '১', 'কেজি', '', ''],
        ['ভালো সুপারি', '৫০০', 'গ্রাম', '', ''],
        ['গরুর গোশত', '২', 'কেজি', '', ''],
        ['জেসমিন সাবান', '২', 'পিস', '', ''],
        ['কাপড় ধোয়ার পাউডার', '৫', 'কেজি', '', ''],
        ['ভিম সাবান', '৫', 'পিস', '', ''],
        ['বাথরুম পারফিউম', '১', 'পিস', '', ''],
        ['ছোট মেডিপ্লাস টুথপেস্ট', '২', 'পিস', '', ''],
        ['হ্যান্ডওয়াশ লিকুইড', '১', 'প্যাকেট', '', ''],
        ['প্যাকেটজাত ছাড়া ভালো মানের কলম্ব নারিকেল তেল', '২', 'লিটার', '', ''],
        ['তাসলিম', '১', 'লিটার', '', ''],
        ['শাম-উল-নহল (ছোট)', 'পরিমাণমতো', '', '', ''],
        ['শিরকা', '৫০০ গ্রাম + ১০ পিস', '', '', 'পাইকারি'],
        ['আল-বাইয়্যিনাত শরীফ (নতুন)', 'পরিমাণমতো', '', '', ''],
        ['তিসিদানার তেল', '১০০', 'গ্রাম', '', ''],
        ['উমাম সাবান', '৫', 'পিস', '', ''],
    ];
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'name' => $r[0],
            'qty' => $r[1],
            'unit' => $r[2],
            'category' => $r[3],
            'notes' => $r[4],
        ];
    }
    return $out;
}

function fam_bazaar_seed_house(mysqli $db, int $demandId): int
{
    $demandId = (int) $demandId;
    if ($demandId <= 0) {
        return 0;
    }
    $have = [];
    foreach (fam_bazaar_lines($db, $demandId, '') as $row) {
        $have[mb_strtolower(trim((string) $row['name']), 'UTF-8')] = true;
    }
    $st = $db->prepare(
        'INSERT INTO family_bazaar_lines (demand_id,category,name,qty,unit,est_price,notes) VALUES (?,?,?,?,?,0,?)'
    );
    if (!$st) {
        return 0;
    }
    $saved = 0;
    foreach (fam_bazaar_house_list() as $item) {
        $key = mb_strtolower($item['name'], 'UTF-8');
        if (isset($have[$key])) {
            continue;
        }
        $cat = $item['category'];
        $name = $item['name'];
        $qty = $item['qty'];
        $unit = $item['unit'];
        $notes = $item['notes'];
        $st->bind_param('isssss', $demandId, $cat, $name, $qty, $unit, $notes);
        if ($st->execute()) {
            $saved++;
            $have[$key] = true;
        }
    }
    $st->close();
    return $saved;
}

function fam_bazaar_house_mark(): string
{
    return 'house-list-23';
}

function fam_bazaar_house_entered(array $demand): bool
{
    return str_contains((string) ($demand['notes'] ?? ''), fam_bazaar_house_mark());
}

/** চলতি মাসের মেমোতে ঘরের তালিকা একবার এন্ট্রি করে রাখে। */
function fam_bazaar_ensure_house_memo(mysqli $db): void
{
    if (function_exists('fam_can_write') && !fam_can_write()) {
        return;
    }
    $ym = fam_bazaar_month_norm(date('Y-m'));
    $demand = fam_bazaar_find($db, $ym);
    if (fam_bazaar_house_entered($demand)) {
        return;
    }
    $demand = fam_bazaar_demand($db, $ym);
    $id = (int) ($demand['id'] ?? 0);
    if ($id < 1) {
        return;
    }
    fam_bazaar_seed_house($db, $id);
    $mark = fam_bazaar_house_mark();
    $notes = trim((string) ($demand['notes'] ?? ''));
    if (!str_contains($notes, $mark)) {
        $notes = trim($notes . "\n" . $mark . ' এন্ট্রি করা হয়েছিল');
    }
    $title = fam_bazaar_month_label($ym) . ' বাজার চাহিদা · এন্ট্রি করা হয়েছিল';
    $st = $db->prepare('UPDATE family_bazaar_demand SET notes=?, title=? WHERE id=?');
    if ($st) {
        $st->bind_param('ssi', $notes, $title, $id);
        $st->execute();
        $st->close();
    }
}

function fam_bazaar_find(mysqli $db, string $ym): array
{
    $ym = fam_bazaar_month_norm($ym);
    $st = $db->prepare('SELECT * FROM family_bazaar_demand WHERE ym = ? LIMIT 1');
    if (!$st) {
        return [];
    }
    $st->bind_param('s', $ym);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: [];
}

function fam_bazaar_demand(mysqli $db, string $ym): array
{
    $ym = fam_bazaar_month_norm($ym);
    $row = fam_bazaar_find($db, $ym);
    if ($row) {
        return $row;
    }
    $memo = 'BZR-' . str_replace('-', '', $ym);
    $title = fam_bazaar_month_label($ym) . ' বাজার চাহিদা';
    $ins = $db->prepare('INSERT INTO family_bazaar_demand (ym, memo_no, title) VALUES (?, ?, ?)');
    if ($ins) {
        $ins->bind_param('sss', $ym, $memo, $title);
        $ins->execute();
        $id = (int) $db->insert_id;
        $ins->close();
        if ($id > 0) {
            return [
                'id' => $id,
                'ym' => $ym,
                'memo_no' => $memo,
                'title' => $title,
                'notes' => '',
            ];
        }
    }
    return fam_bazaar_find($db, $ym);
}

/** @return list<array<string,mixed>> */
function fam_bazaar_lines(mysqli $db, int $demandId, string $cat = ''): array
{
    $demandId = (int) $demandId;
    if ($demandId <= 0) {
        return [];
    }
    if ($cat !== '' && fam_bazaar_cat_ok($cat)) {
        $st = $db->prepare(
            'SELECT * FROM family_bazaar_lines WHERE demand_id = ? AND category = ? ORDER BY id ASC'
        );
        $st->bind_param('is', $demandId, $cat);
    } else {
        $st = $db->prepare('SELECT * FROM family_bazaar_lines WHERE demand_id = ? ORDER BY id ASC');
        $st->bind_param('i', $demandId);
    }
    if (!$st) {
        return [];
    }
    $st->execute();
    $res = $st->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
    return $rows;
}

/** @return array{items:int,bought:int,est:float,paid:float,by_cat:array<string,array{items:int,bought:int,est:float,paid:float}>} */
function fam_bazaar_stats(array $lines): array
{
    $out = [
        'items' => 0,
        'bought' => 0,
        'est' => 0.0,
        'paid' => 0.0,
        'by_cat' => [],
    ];
    foreach (fam_bazaar_cats() as $c) {
        $out['by_cat'][$c] = ['items' => 0, 'bought' => 0, 'est' => 0.0, 'paid' => 0.0];
    }
    foreach ($lines as $row) {
        $cat = fam_bazaar_norm_cat((string) ($row['category'] ?? ''));
        if ($cat === '') {
            $cat = 'ক্যাটাগরি নেই';
        }
        if (!isset($out['by_cat'][$cat])) {
            $out['by_cat'][$cat] = ['items' => 0, 'bought' => 0, 'est' => 0.0, 'paid' => 0.0];
        }
        $est = round((float) ($row['est_price'] ?? 0), 2);
        $paid = round((float) ($row['paid_price'] ?? 0), 2);
        $bought = !empty($row['is_bought']);
        $out['items']++;
        $out['est'] = round($out['est'] + $est, 2);
        $out['by_cat'][$cat]['items']++;
        $out['by_cat'][$cat]['est'] = round($out['by_cat'][$cat]['est'] + $est, 2);
        if ($bought) {
            $out['bought']++;
            $spend = $paid > 0.009 ? $paid : $est;
            $out['paid'] = round($out['paid'] + $spend, 2);
            $out['by_cat'][$cat]['bought']++;
            $out['by_cat'][$cat]['paid'] = round($out['by_cat'][$cat]['paid'] + $spend, 2);
        }
    }
    return $out;
}

/** @return list<array<string,mixed>> */
function fam_bazaar_month_rows(mysqli $db): array
{
    $sql = "SELECT d.ym, d.memo_no, d.title,
                   COUNT(l.id) AS items,
                   SUM(CASE WHEN l.is_bought=1 THEN 1 ELSE 0 END) AS bought,
                   COALESCE(SUM(l.est_price),0) AS est,
                   COALESCE(SUM(CASE WHEN l.is_bought=1 THEN IF(l.paid_price>0, l.paid_price, l.est_price) ELSE 0 END),0) AS paid
            FROM family_bazaar_demand d
            LEFT JOIN family_bazaar_lines l ON l.demand_id = d.id
            GROUP BY d.id
            ORDER BY d.ym DESC
            LIMIT 36";
    $res = @$db->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function fam_bazaar_line_spend(array $row): float
{
    $paid = round((float) ($row['paid_price'] ?? 0), 2);
    $est = round((float) ($row['est_price'] ?? 0), 2);
    if (!empty($row['is_bought'])) {
        return $paid > 0.009 ? $paid : $est;
    }
    return 0.0;
}
