<?php
/* 10.98.718 — পাতা সব ডিভাইসে মাঝখানে, মোবাইলে পর্দা ভরে, মোট সারির কলাম চওড়া; 10.98.717 — সব ডিভাইসে পাতা মাঝখানে, বড় লেখা, শেষে মোট লাইন, উত্তোলন সারি, লাভ−খরচ সূত্র, পার্টনার-রঙ, সরাসরি শেয়ার (শুধু দেখানো)। 10.98.716 — সম্পূর্ণ পার্টনার স্টেটমেন্ট: বিক্রয় লাভ + ক্রয় + খরচ (প্রতি পাতায় ২০ লাইন), কাস্টমার আইডি, পার্টনার-ভিত্তিক অংশ, সই। শুধু পড়া/দেখানো — কোনো ব্যালেন্স বা হিসাবের নিয়ম বদলায় না। 10.98.715 — ২০ লাইনের পাতা */
require_once '../../includes/config.php';
require_once '../../includes/partnership_functions.php';
require_once '../../includes/partner_accounting.php';
pa_guard();
ensure_partner_accounting_schema($db);

$ym = (string)($_GET['month'] ?? date('Y-m'));
$dash = pa_dashboard($db, $ym);
$bounds = $dash['month']['bounds'];
$ym = $bounds['ym'];

$rf_raw = trim((string)($_GET['rf'] ?? $_GET['lf'] ?? ''));
$rt_raw = trim((string)($_GET['rt'] ?? $_GET['lt'] ?? ''));
$pa_rep = pa_resolve_report_range($db, $ym, $rf_raw, $rt_raw);
$from = $pa_rep['from'];
$to = $pa_rep['to'];
$range_on = !$pa_rep['full_month'];

$partners = [];
foreach ($dash['active'] as $a) {
    $partners[] = $a['partner'];
}
$partner_names = [];
$partner_by_id = [];
foreach ($partners as $p) {
    $partner_names[] = (string)$p['name'];
    $partner_by_id[(int)$p['id']] = $p;
}
$partner_label = $partner_names ? implode(' ও ', $partner_names) : 'পার্টনার';
$pcts = pa_active_share_pcts($db);

/*
 * হিসাবের মাস: তারিখ-সীমা বাছা থাকলে সেই সীমার মাসগুলো, না থাকলে বাছা মাস।
 * সাবেক ব্যালেন্স = প্রথম মাসের শুরুর ব্যালেন্স, সর্বমোট = শেষ মাসের শেষ ব্যালেন্স —
 * সবই আগের লেজার ফাংশন (pa_partner_book, pa_partner_month_cut) থেকে; এখানে নতুন কোনো হিসাব নেই।
 */
$months = [];
if ($range_on) {
    $cur = substr($from, 0, 7);
    $end_m = substr($to, 0, 7);
    while ($cur <= $end_m && count($months) < 12) {
        $months[] = $cur;
        $cur = date('Y-m', strtotime($cur . '-01 +1 month'));
    }
} else {
    $months[] = $ym;
}
if (!$months) {
    $months[] = $ym;
}
$month_data = [];
foreach ($months as $m) {
    $month_data[$m] = ($m === $ym && isset($dash['month'])) ? $dash['month'] : pa_month_totals($db, $m);
}
$first_m = $months[0];
$last_m = $months[count($months) - 1];
$first_bounds = pa_month($first_m);
$last_bounds = pa_month($last_m);
$month_label = $first_m === $last_m ? (string)$first_bounds['label'] : ((string)$first_bounds['label'] . ' – ' . (string)$last_bounds['label']);
$multi_month = count($months) > 1;
$partial_range = $range_on && ($from > $first_bounds['from'] || $to < $last_bounds['to']);

$ym_key = str_replace('-', '', $first_m);

$exp_cats = pa_expense_category_labels($db);
$expenses = [];
$total_exp = 0.0;
if (!$multi_month) {
    $exp_pack = pa_expense_lines($db, $from, $to, 2000);
    foreach ($exp_pack['lines'] as $row) {
        $sh = [];
        foreach ($row['by_partner'] ?? [] as $pid => $v) {
            $sh[(int)$pid] = pa_money($v);
        }
        $expenses[] = [
            'no' => 'EXP-' . (int)($row['id'] ?? 0),
            'date' => (string)($row['date'] ?? ''),
            'category' => (string)($row['category'] ?? 'খরচ'),
            'desc' => trim((string)($row['description'] ?? '')),
            'amount' => pa_money($row['amount'] ?? 0),
            'shares' => $sh,
        ];
    }
    $total_exp = pa_money($exp_pack['total'] ?? 0);
} else {
foreach ($months as $m) {
    $mrows = $month_data[$m]['expenses'] ?? [];
    $amts = [];
    foreach ($mrows as $ex) {
        $amts[] = pa_money($ex['amount'] ?? 0);
    }
    $targets = [];
    foreach ($partners as $pp) {
        $targets[(int)$pp['id']] = (float)pa_partner_month_cut($db, $month_data[$m], $pp)['expense'];
    }
    $split = pa_split_rows($amts, $pcts, 'expense', $targets);
    foreach ($mrows as $i => $ex) {
        $amt = $amts[$i];
        $total_exp += $amt;
        $desc = trim((string)($ex['description'] ?? ''));
        $note = trim((string)($ex['note'] ?? ''));
        $expenses[] = [
            'no' => pa_expense_voucher_label($ex),
            'date' => (string)($ex['expense_date'] ?? ''),
            'category' => (string)($exp_cats[$ex['category'] ?? ''] ?? ($ex['category'] ?? 'খরচ')),
            'desc' => trim(($desc !== '' ? $desc : $note) . (trim((string)($ex['payee'] ?? '')) !== '' ? ' · ' . trim((string)$ex['payee']) : '')),
            'amount' => $amt,
            'shares' => $split[$i] ?? [],
        ];
    }
}
    $total_exp = round($total_exp, 2);
}

$pack = pa_ledger_sale_lines($db, $from, $to, 2000);
$lines = array_reverse($pack['lines'] ?? []);
$more = !empty($pack['more']);
$by_month_idx = [];
foreach ($lines as $i => $l) {
    $by_month_idx[substr((string)$l['invoice_date'], 0, 7)][] = $i;
}
foreach ($by_month_idx as $m => $idxs) {
    $amts = [];
    foreach ($idxs as $i) {
        $amts[] = (float)$lines[$i]['profit'];
    }
    $targets = null;
    $mb = pa_month((string)$m);
    $month_full_in_range = !$range_on && $from <= $mb['from'] && $to >= $mb['to'];
    if (!$more && $range_on) {
        $range_profit = 0.0;
        foreach ($idxs as $i) {
            $range_profit += (float)$lines[$i]['profit'];
        }
        $range_profit = round($range_profit, 2);
        $rc = pa_share_cuts($range_profit, 0.0, $pcts);
        $targets = [];
        foreach ($partners as $pp) {
            $targets[(int)$pp['id']] = (float)($rc[(int)$pp['id']]['gross'] ?? 0);
        }
    } elseif (!$more && $month_full_in_range && isset($month_data[$m])) {
        $targets = [];
        foreach ($partners as $pp) {
            $targets[(int)$pp['id']] = (float)pa_partner_month_cut($db, $month_data[$m], $pp)['gross'];
        }
    }
    $split = pa_split_rows($amts, $pcts, 'gross', $targets);
    foreach ($idxs as $k => $i) {
        $lines[$i]['shares'] = $split[$k] ?? [];
    }
}
$total = 0.0;
$total_sale = 0.0;
$total_sale_qty = 0.0;
$sales_share_sum = [];
foreach ($lines as $l) {
    $total += (float)$l['profit'];
    $total_sale += (float)($l['sale_amount'] ?? 0);
    $total_sale_qty += (float)($l['qty'] ?? 0);
    foreach (($l['shares'] ?? []) as $pid => $v) {
        $sales_share_sum[$pid] = pa_money(($sales_share_sum[$pid] ?? 0) + $v);
    }
}
$total = round($total, 2);
$total_sale = round($total_sale, 2);
$total_sale_qty = round($total_sale_qty, 3);
$exp_share_sum = [];
foreach ($expenses as $r) {
    foreach ($r['shares'] as $pid => $v) {
        $exp_share_sum[$pid] = pa_money(($exp_share_sum[$pid] ?? 0) + $v);
    }
}

$ppack = pa_ledger_purchase_lines($db, $from, $to, 2000);
$purchases = $ppack['lines'] ?? [];
$pur_more = !empty($ppack['more']);
$total_pur = 0.0;
foreach ($purchases as $r) {
    $total_pur += (float)$r['total_cost'];
}
$total_pur = round($total_pur, 2);

$wd_items = [];
foreach ($partners as $pp) {
    foreach ($months as $m) {
        $wd = pa_book_withdrawals((array)(pa_partner_book($db, $pp, $m)['lines'] ?? []));
        foreach ($wd['items'] as $wi) {
            $d = substr((string)($wi['date'] ?? ''), 0, 10);
            if ($d !== '' && ($d < $from || $d > $to)) {
                continue;
            }
            $wi['partner'] = (string)$pp['name'];
            $wi['pid'] = (int)$pp['id'];
            $wd_items[] = $wi;
        }
    }
}
usort($wd_items, static function ($a, $b) {
    return strcmp((string)$a['date'], (string)$b['date']);
});
$book_rows = [];
$sum_open = $sum_profit = $sum_exp = $sum_other = $sum_draw = $sum_total = 0.0;
if (!$multi_month) {
    $stmt_sum = pa_statement_period_summary($db, $ym, $from, $to, $partners, $pcts);
    $book_rows = $stmt_sum['rows'];
    $sum_open = (float)$stmt_sum['sums']['open'];
    $sum_profit = (float)$stmt_sum['sums']['profit'];
    $sum_exp = (float)$stmt_sum['sums']['exp'];
    $sum_draw = (float)$stmt_sum['sums']['draw'];
    $sum_other = (float)$stmt_sum['sums']['other'];
    $sum_total = (float)$stmt_sum['sums']['total'];
} else {
    foreach ($partners as $pp) {
        $open = (float)(pa_opening($db, $pp, $first_m)['opening'] ?? 0);
        $tot = (float)pa_partner_book($db, $pp, $last_m)['total'];
        $gross = 0.0;
        $ecut = 0.0;
        foreach ($months as $m) {
            $cut = pa_partner_month_cut($db, $month_data[$m], $pp);
            $gross += (float)$cut['gross'];
            $ecut += (float)$cut['expense'];
        }
        $gross = round($gross, 2);
        $ecut = round($ecut, 2);
        $draw = 0.0;
        foreach ($months as $m) {
            $wd = pa_book_withdrawals((array)(pa_partner_book($db, $pp, $m)['lines'] ?? []));
            $draw = round($draw + $wd['withdrawn'], 2);
        }
        $other = round($tot - $open - $gross + $ecut + $draw, 2);
        $book_rows[] = [
            'p' => $pp,
            'open' => $open,
            'profit' => $gross,
            'exp' => $ecut,
            'net' => round($gross - $ecut, 2),
            'draw' => $draw,
            'other' => $other,
            'total' => $tot,
        ];
        $sum_open += $open;
        $sum_profit += $gross;
        $sum_exp += $ecut;
        $sum_draw += $draw;
        $sum_other += $other;
        $sum_total += $tot;
    }
}
$sum_open = round($sum_open, 2);
$sum_profit = round($sum_profit, 2);
$sum_exp = round($sum_exp, 2);
$sum_other = round($sum_other, 2);
$sum_draw = round($sum_draw, 2);
$sum_total = round($sum_total, 2);
$show_other = false;
foreach ($book_rows as $br) {
    if (abs($br['other']) > 0.009) {
        $show_other = true;
    }
}

$bn = static function ($v): string {
    return strtr((string)$v, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
};
$fmt_date = static function (string $d) {
    return preg_match('/^\d{4}-\d{2}-\d{2}/', $d) ? date('d-m-Y', strtotime(substr($d, 0, 10))) : '';
};
$h = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
$mny = static function ($v): string {
    $v = round((float)$v, 2);
    return ($v < -0.004 ? '−' : '') . '৳' . number_format(abs($v), 2);
};
$mny_bn = static function ($v) use ($mny, $bn): string {
    return $bn($mny($v));
};
$sgn = static function ($v, string $sign): string {
    $v = round(abs((float)$v), 2);
    if ($v < 0.005) {
        return '৳0.00';
    }
    return ($sign === '-' ? '−' : '+') . '৳' . number_format($v, 2);
};
$tone = static function ($v): string {
    return ((float)$v < -0.004) ? 'neg' : 'pos';
};

$period = $range_on
    ? ($fmt_date($from) . ' থেকে ' . $fmt_date($to))
    : (string)($bounds['label'] ?? $ym);

$doc_in = (string)($_GET['doc'] ?? 'all');
$doc = in_array($doc_in, ['all', 'sales', 'purchase', 'expense'], true) ? $doc_in : 'all';
$PER_PAGE = 20;
$uncat = 'অন্যান্য খাত';
$sec_list = $doc === 'all' ? ['sales', 'expense', 'purchase'] : [$doc];

$sales_rows = [];
foreach ($lines as $i => $r) {
    $r['category_name'] = trim((string)($r['category_name'] ?? '')) !== '' ? trim((string)$r['category_name']) : $uncat;
    $r['_i'] = $i;
    $sales_rows[] = $r;
}
$pur_rows = [];
foreach ($purchases as $i => $r) {
    $r['category_name'] = trim((string)($r['category_name'] ?? '')) !== '' ? trim((string)$r['category_name']) : $uncat;
    $r['_i'] = $i;
    $pur_rows[] = $r;
}
$by_cat_date = static function (string $dateKey): callable {
    return static function ($x, $y) use ($dateKey) {
        $c = strcmp((string)$x['category_name'], (string)$y['category_name']);
        if ($c !== 0) {
            return $c;
        }
        $d = strcmp((string)$x[$dateKey], (string)$y[$dateKey]);
        return $d !== 0 ? $d : ($x['_i'] <=> $y['_i']);
    };
};
usort($sales_rows, $by_cat_date('invoice_date'));
usort($pur_rows, $by_cat_date('date'));

$cat_stat = [];
foreach ($sales_rows as $r) {
    $k = (string)$r['category_name'];
    if (!isset($cat_stat[$k])) {
        $cat_stat[$k] = ['n' => 0, 'qty' => 0.0, 'sale' => 0.0, 'profit' => 0.0, 'shares' => []];
    }
    $cat_stat[$k]['n']++;
    $cat_stat[$k]['qty'] = round(($cat_stat[$k]['qty'] ?? 0) + (float)($r['qty'] ?? 0), 3);
    $cat_stat[$k]['sale'] += (float)$r['sale_amount'];
    $cat_stat[$k]['profit'] += (float)$r['profit'];
    foreach (($r['shares'] ?? []) as $pid => $v) {
        $cat_stat[$k]['shares'][$pid] = pa_money(($cat_stat[$k]['shares'][$pid] ?? 0) + $v);
    }
}
$pur_stat = [];
foreach ($pur_rows as $r) {
    $k = (string)$r['category_name'];
    if (!isset($pur_stat[$k])) {
        $pur_stat[$k] = ['n' => 0, 'qty' => 0.0, 'cost' => 0.0];
    }
    $pur_stat[$k]['n']++;
    $pur_stat[$k]['qty'] = round(($pur_stat[$k]['qty'] ?? 0) + (float)($r['qty'] ?? 0), 3);
    $pur_stat[$k]['cost'] += (float)$r['total_cost'];
}
$exp_cat_stat = [];
foreach ($expenses as $r) {
    $k = (string)$r['category'];
    if (!isset($exp_cat_stat[$k])) {
        $exp_cat_stat[$k] = ['n' => 0, 'amount' => 0.0];
    }
    $exp_cat_stat[$k]['n']++;
    $exp_cat_stat[$k]['amount'] += (float)$r['amount'];
}

$paginate_cat = static function (array $rows, string $type) use ($PER_PAGE): array {
    $pages = [];
    $cur = [];
    $used = 0;
    $last_cat = null;
    $prev_cat = null;
    $cat_rows_open = 0;
    $push_cat_end = static function (?string $cat) use (&$cur, &$used, &$cat_rows_open): void {
        if ($cat === null || $cat_rows_open < 1) {
            return;
        }
        $cur[] = ['t' => 'catend', 'name' => $cat];
        $used++;
        $cat_rows_open = 0;
    };
    foreach ($rows as $r) {
        $cat_name = (string)$r['category_name'];
        if ($last_cat !== null && $last_cat !== $cat_name) {
            $push_cat_end($last_cat);
        }
        $new_cat = ($used === 0 || $last_cat !== $cat_name);
        $need = $new_cat ? 2 : 1;
        if ($used + $need > $PER_PAGE) {
            $push_cat_end($last_cat);
            $pages[] = $cur;
            $cur = [];
            $used = 0;
            $last_cat = null;
            $new_cat = true;
        }
        if ($new_cat) {
            $cur[] = ['t' => 'cat', 'name' => $cat_name, 'cont' => ($used === 0 && $prev_cat === $cat_name)];
            $used++;
            $last_cat = $cat_name;
            $cat_rows_open = 0;
        }
        $cur[] = ['t' => $type, 'r' => $r];
        $used++;
        $cat_rows_open++;
        $prev_cat = $cat_name;
    }
    $push_cat_end($last_cat);
    if ($cur) {
        $pages[] = $cur;
    }
    return $pages;
};

$pages = [];
$add_pages = static function (string $sec, array $chunks, string $empty_msg) use (&$pages, $PER_PAGE): void {
    if (!$chunks) {
        $chunks[] = [['t' => 'empty', 'msg' => $empty_msg]];
    }
    $li = count($chunks) - 1;
    if (count($chunks[$li]) + 1 > $PER_PAGE && ($chunks[$li][0]['t'] ?? '') !== 'empty') {
        $moved = [];
        $last = array_pop($chunks[$li]);
        array_unshift($moved, $last);
        $tail = $chunks[$li][count($chunks[$li]) - 1] ?? null;
        if ($tail !== null && $tail['t'] === 'cat') {
            array_unshift($moved, array_pop($chunks[$li]));
        } elseif ($sec !== 'expense' && isset($last['r']['category_name'])) {
            array_unshift($moved, ['t' => 'cat', 'name' => $last['r']['category_name'], 'cont' => true]);
        }
        $chunks[] = $moved;
        $li++;
    }
    $chunks[$li][] = ['t' => 'tot'];
    foreach ($chunks as $items) {
        $pages[] = ['sec' => $sec, 'items' => $items];
    }
};
$has_data = ['sales' => (bool)$sales_rows, 'purchase' => (bool)$pur_rows, 'expense' => (bool)$expenses];
foreach ($sec_list as $sec) {
    if ($doc === 'all' && !$has_data[$sec]) {
        continue;
    }
    if ($sec === 'sales') {
        $add_pages('sales', $paginate_cat($sales_rows, 's'), 'এই সময়ে যুক্ত খাতের কোনো বিক্রি নেই।');
    } elseif ($sec === 'purchase') {
        $add_pages('purchase', $paginate_cat($pur_rows, 'p'), 'এই সময়ে যুক্ত খাতের কোনো ক্রয় নেই।');
    } else {
        $chunks = [];
        foreach (array_chunk($expenses, $PER_PAGE) as $chunk) {
            $pg = [];
            foreach ($chunk as $r) {
                $pg[] = ['t' => 'e', 'r' => $r];
            }
            $chunks[] = $pg;
        }
        $add_pages('expense', $chunks, 'এই সময়ে কোনো খরচ নেই।');
    }
}
if (!$pages) {
    $add_pages('sales', [], 'এই সময়ে যুক্ত খাতের কোনো বিক্রি, ক্রয় বা খরচ নেই।');
}
$mini_rows = static function (array $stat, int $max = 5) use ($bn): array {
    $out = [];
    $rest = null;
    if (count($stat) <= $max + 1) {
        $max = count($stat);
    }
    foreach ($stat as $name => $st) {
        if (count($out) < $max) {
            $out[] = [$name, $st];
        } else {
            if ($rest === null) {
                $rest = ['n' => 0, 'sale' => 0.0, 'profit' => 0.0, 'cost' => 0.0, 'amount' => 0.0, 'c' => 0, 'shares' => []];
            }
            $rest['c']++;
            foreach (['n', 'qty', 'sale', 'profit', 'cost', 'amount'] as $f) {
                $rest[$f] += (float)($st[$f] ?? 0);
            }
            foreach (($st['shares'] ?? []) as $pid => $v) {
                $rest['shares'][$pid] = pa_money(($rest['shares'][$pid] ?? 0) + $v);
            }
        }
    }
    if ($rest !== null) {
        $out[] = ['অন্যান্য (' . $bn($rest['c']) . 'টি খাত)', $rest];
    }
    return $out;
};

$show_sales_mini = in_array('sales', $sec_list, true) && $cat_stat;
$show_pur_mini = in_array('purchase', $sec_list, true) && $pur_stat;
$show_exp_mini = in_array('expense', $sec_list, true) && $exp_cat_stat;
$blocks = [];
if ($show_sales_mini) {
    $rows_ = $mini_rows($cat_stat, 8);
    $blocks[] = ['k' => 'sales', 'h' => 134 + 33 * count($rows_), 'rows' => $rows_];
}
if ($show_pur_mini) {
    $rows_ = $mini_rows($pur_stat, 8);
    $blocks[] = ['k' => 'purchase', 'h' => 134 + 33 * count($rows_), 'rows' => $rows_];
}
if ($show_exp_mini) {
    $rows_ = $mini_rows($exp_cat_stat, 8);
    $blocks[] = ['k' => 'exp', 'h' => 134 + 33 * count($rows_), 'rows' => $rows_];
}
if ($wd_items) {
    $wd_show = array_slice($wd_items, 0, 8);
    $blocks[] = ['k' => 'wd', 'h' => 134 + 33 * (count($wd_show) + (count($wd_items) > count($wd_show) ? 1 : 0)), 'rows' => $wd_show];
}
$cat_pages = [];
$cur_blocks = [];
$cur_h = 0;
foreach ($blocks as $blk) {
    if ($cur_blocks && $cur_h + $blk['h'] > 820) {
        $cat_pages[] = $cur_blocks;
        $cur_blocks = [];
        $cur_h = 0;
    }
    $cur_blocks[] = $blk;
    $cur_h += $blk['h'];
}
if ($cur_blocks) {
    $cat_pages[] = $cur_blocks;
}
foreach ($cat_pages as $cb) {
    $pages[] = ['sec' => 'cats', 'items' => $cb];
}
$pages[] = ['sec' => 'summary', 'items' => [['t' => 'set']]];
$page_count = count($pages);

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$addr = function_exists('shop_address') ? (string)shop_address() : '';
$logo = function_exists('shop_logo_url') ? shop_logo_url('/assets/img/header-logo.png') : '/assets/img/header-logo.png';
$printed = date('d-m-Y');
$ver = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '1', ENT_QUOTES, 'UTF-8');

$net_profit_rows = [];
foreach ($book_rows as $br) {
    $net_profit_rows[] = isset($br['net']) ? round((float)$br['net'], 2) : round($br['profit'] - $br['exp'], 2);
}
$sum_period_net = round($sum_profit - $sum_exp, 2);
$sum_net = round($sum_profit - $sum_exp, 2);

$sum_word = ($multi_month || $partial_range) ? 'এ সময়ের' : 'এ মাসের';
$doc_meta = [
    'all' => ['PST-', 'পার্টনার স্টেটমেন্ট', 'PARTNER STATEMENT', 'partner-statement-'],
    'sales' => ['PSS-', 'বিক্রয় লাভ স্টেটমেন্ট', 'SALES PROFIT STATEMENT', 'partner-sales-profit-'],
    'purchase' => ['PSP-', 'ক্রয় স্টেটমেন্ট', 'PURCHASE STATEMENT', 'partner-purchases-'],
    'expense' => ['PSE-', 'খরচ স্টেটমেন্ট', 'EXPENSE STATEMENT', 'partner-expenses-'],
][$doc];
$doc_no = $doc_meta[0] . $ym_key . ($range_on ? '-R' : '');
$doc_title = $doc_meta[1];
$doc_kicker = $doc_meta[2];
$doc_period = ($doc === 'expense') ? $month_label : ($range_on ? $period : $month_label);
$file_base = $doc_meta[3] . $first_m . ($range_on ? ('-' . $from . '_' . $to) : '');
$qs = static function (string $d) use ($ym, $from, $to, $range_on): string {
    $href = pa_statement_share_href($ym, $from, $to, $d);
    return $href;
};
$back = 'partner_accounting.php' . pa_report_qs($ym, $from, $to, ['tab' => ($doc === 'expense' ? 'draw' : 'capital')]) . ($doc === 'expense' ? '#paExpBox' : '#paPeriodReport');

$share_lines = [$shop, 'পার্টনার: ' . $partner_label, $doc_title . ' · ' . $doc_period];
if (in_array('sales', $sec_list, true)) {
    $share_lines[] = 'বিক্রয়: ' . $bn(count($lines)) . 'টি · বিক্রয় মূল্য ' . $mny($total_sale) . ' · লাভ ' . $mny($total);
    foreach ($cat_stat as $cn => $cs) {
        $share_lines[] = '  ' . $cn . ': ' . $bn($cs['n']) . 'টি · লাভ ' . $mny($cs['profit']);
    }
}
if (in_array('purchase', $sec_list, true)) {
    $share_lines[] = 'ক্রয়: ' . $bn(count($purchases)) . 'টি · মোট ' . $mny($total_pur);
    foreach ($pur_stat as $cn => $cs) {
        $share_lines[] = '  ' . $cn . ': ' . $bn($cs['n']) . 'টি · ' . $mny($cs['cost']);
    }
}
if (in_array('expense', $sec_list, true)) {
    $share_lines[] = 'খরচ: ' . $bn(count($expenses)) . 'টি · মোট ' . $mny($total_exp);
}
foreach ($book_rows as $i => $br) {
    $share_lines[] = $br['p']['name'] . ': সাবেক ' . $mny($br['open']) . ' + লাভ ' . $mny($br['profit']) . ' − খরচ ' . $mny($br['exp'])
        . (abs($br['draw']) > 0.009 ? (' − উত্তোলন ' . $mny($br['draw'])) : '')
        . (abs($br['other']) > 0.009 ? (' ' . ($br['other'] < 0 ? '−' : '+') . ' অন্যান্য ' . $mny(abs($br['other']))) : '')
        . ' = ' . $mny($br['total']);
}
$share_lines[] = $sum_word . ' নিট লাভ (লাভ − খরচ): ' . $mny($sum_net);
$share_lines[] = 'দুজনের সর্বমোট ব্যালেন্স: ' . $mny($sum_total);
$share_text = implode("\n", $share_lines);

$np = max(1, count($book_rows));
$pw = $np <= 2 ? 90 : 72;
$share_cols = str_repeat('<col style="width:' . $pw . 'px">', count($book_rows));
$cols = [
    'sales' => '<col style="width:28px"><col style="width:100px"><col style="width:116px"><col><col style="width:52px"><col style="width:98px"><col style="width:86px">' . $share_cols,
    'purchase' => '<col style="width:28px"><col style="width:104px"><col style="width:124px"><col><col style="width:54px"><col style="width:90px"><col style="width:104px">',
    'expense' => '<col style="width:28px"><col style="width:118px"><col style="width:102px"><col><col style="width:96px">' . $share_cols,
];
$ncols = ['sales' => 7 + count($book_rows), 'purchase' => 7, 'expense' => 5 + count($book_rows)];
$pcol = $np <= 2
    ? 195
    : max(100, min(150, (int)floor((748 - 200 - 150) / $np)));
$qty_fmt = static function ($q): string {
    $q = (float)$q;
    return rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.');
};
$sec_title = [
    'sales' => ['বিক্রয় লাভ · খাত অনুযায়ী', $range_on ? $period : $month_label, 'sales'],
    'purchase' => ['ক্রয় · খাত অনুযায়ী', $range_on ? $period : $month_label, 'purchase'],
    'expense' => ['মাসের সব খরচ', $month_label, 'exp'],
];
$sec_count = [
    'sales' => $bn(count($lines)) . 'টি ইনভয়েস',
    'purchase' => $bn(count($purchases)) . 'টি ক্রয়',
    'expense' => $bn(count($expenses)) . 'টি খরচ',
];
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/partner-sales-share.css?v=<?php echo $ver; ?>">
<link rel="stylesheet" href="/assets/css/partner-statement.css?v=<?php echo $ver; ?>">
<div class="psp-wrap">
    <div class="psp-bar no-print">
        <a class="psp-back" href="<?php echo $h($back); ?>">← ফিরে যান</a>
        <div class="psp-docs">
            <a class="psp-doc-tab<?php echo $doc === 'all' ? ' on' : ''; ?>" href="<?php echo $h($qs('all')); ?>">সম্পূর্ণ স্টেটমেন্ট</a>
            <a class="psp-doc-tab<?php echo $doc === 'sales' ? ' on' : ''; ?>" href="<?php echo $h($qs('sales')); ?>">বিক্রয় লাভ</a>
            <a class="psp-doc-tab<?php echo $doc === 'purchase' ? ' on' : ''; ?>" href="<?php echo $h($qs('purchase')); ?>">ক্রয়</a>
            <a class="psp-doc-tab<?php echo $doc === 'expense' ? ' on' : ''; ?>" href="<?php echo $h($qs('expense')); ?>">মাসের খরচ</a>
        </div>
        <div class="psp-actions">
            <button type="button" class="psp-btn" data-psp="print">প্রিন্ট</button>
            <button type="button" class="psp-btn pdf" data-psp="pdf">PDF</button>
            <button type="button" class="psp-btn img" data-psp="image">ছবি</button>
            <button type="button" class="psp-btn share" data-psp="share">শেয়ার</button>
            <button type="button" class="psp-btn wa" data-psp="wa">হোয়াটসঅ্যাপ</button>
            <button type="button" class="psp-btn soft" data-psp="copy">লেখা কপি</button>
        </div>
        <div class="psp-prep" id="pspPrep" hidden></div>
        <div class="psp-msg" id="pspMsg" hidden></div>
        <p class="psp-hint">মোট <?php echo $h($bn($page_count)); ?>টি পাতা · প্রতি পাতায় সর্বোচ্চ <?php echo $h($bn($PER_PAGE)); ?> লাইন। শেষ পাতায় সাবেক ব্যালেন্স, লাভ, খরচ বাদ, নিট লাভ ও দুই পার্টনারের সর্বমোট ব্যালেন্স। শেষ পাতার সইয়ের ঘরে চাপলে সই করার বোর্ড খুলবে — «শেয়ার» চাপলে সই সহ পুরো ফাইল সরাসরি শেয়ার হবে।</p>
    </div>

    <div class="psp-stage" id="pspStage" data-file="<?php echo $h($file_base); ?>" data-text="<?php echo $h($share_text); ?>" data-shop="<?php echo $h($shop); ?>" data-sigkey="<?php echo $h($ym_key . ($range_on ? ('-R' . str_replace('-', '', $from) . str_replace('-', '', $to)) : '')); ?>">
        <?php
        $serial = ['sales' => 0, 'purchase' => 0, 'expense' => 0];
        foreach ($pages as $pi => $pg):
            $page_no = $pi + 1;
            $sec = $pg['sec'];
            $page_items = $pg['items'];
            ?>
        <section class="psp-page pst<?php
            echo $sec === 'summary' ? ' psp-page-summary' : '';
            echo $sec === 'cats' ? ' psp-page-cats' : '';
        ?>" data-page="<?php echo $page_no; ?>" data-sec="<?php echo $h($sec); ?>">
            <div class="psp-topbar"></div>
            <header class="psp-head">
                <div class="psp-brand">
                    <img src="<?php echo $h($logo); ?>" alt="<?php echo $h($shop); ?>" class="psp-logo" crossorigin="anonymous">
                    <div class="psp-brand-txt">
                        <div class="psp-shop"><?php echo $h($shop); ?></div>
                        <?php if ($addr !== ''): ?><div class="psp-addr"><?php echo $h($addr); ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="psp-doc">
                    <div class="psp-doc-kicker"><?php echo $h($doc_kicker); ?></div>
                    <div class="psp-doc-title"><?php echo $h($doc_title); ?></div>
                    <div class="psp-doc-grid">
                        <span>স্টেটমেন্ট নং</span><b><?php echo $h($doc_no); ?></b>
                        <span>সময়কাল</span><b><?php echo $h($doc_period); ?></b>
                        <span>ইস্যু তারিখ</span><b><?php echo $h($printed); ?></b>
                    </div>
                </div>
            </header>
            <div class="psp-partners">
                <?php if (!$partners): ?>
                    <div class="psp-pcell c1"><strong class="psp-partner-name">পার্টনার</strong></div>
                <?php else: foreach ($partners as $pi2 => $pp):
                    $pp_share = (float)($pp['share_percentage'] ?? 0); ?>
                    <div class="psp-pcell c<?php echo ($pi2 % 4) + 1; ?>">
                        <span class="psp-pno"><?php echo $h($bn($pi2 + 1)); ?></span>
                        <strong class="psp-partner-name"><?php echo $h($pp['name']); ?></strong>
                        <?php if ($pp_share > 0): ?><span class="psp-partner-share">শেয়ার <?php echo $h($bn(rtrim(rtrim(number_format($pp_share, 2, '.', ''), '0'), '.'))); ?>%</span><?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="psp-body">
            <?php if ($sec === 'sales' || $sec === 'purchase' || $sec === 'expense'):
                $st = $sec_title[$sec]; ?>
                <div class="psp-sec <?php echo $h($st[2]); ?>">
                    <div class="psp-sec-title">
                        <span class="psp-sec-dot"></span>
                        <span class="psp-sec-name"><?php echo $h($st[0]); ?></span>
                        <span class="psp-sec-sub"><?php echo $h($st[1]); ?></span>
                        <span class="psp-sec-count"><?php echo $h($sec_count[$sec]); ?></span>
                    </div>
                    <table class="pst-table" data-ame-no-page="1">
                        <colgroup><?php echo $cols[$sec]; ?></colgroup>
                        <thead>
                            <?php if ($sec === 'sales'): ?>
                            <tr><th>নং</th><th>তারিখ<small>ইনভয়েস</small></th><th>কাস্টমার<small>আইডি</small></th><th>পণ্য</th><th class="num">পরিমাণ</th><th class="num">বিক্রয়</th><th class="num">লাভ</th><?php foreach ($book_rows as $bi => $br): ?><th class="num psh psh<?php echo ($bi % 4) + 1; ?>">পার্টনার <?php echo $h($bn($bi + 1)); ?><small>লাভের অংশ</small></th><?php endforeach; ?></tr>
                            <?php elseif ($sec === 'purchase'): ?>
                            <tr><th>নং</th><th>তারিখ<small>মেমো নং</small></th><th>সরবরাহকারী<small>আইডি</small></th><th>পণ্য</th><th class="num">পরিমাণ</th><th class="num">দর</th><th class="num">মোট ক্রয়</th></tr>
                            <?php else: ?>
                            <tr><th>নং</th><th>তারিখ<small>ভাউচার</small></th><th>খাত</th><th>বিবরণ</th><th class="num">পরিমাণ</th><?php foreach ($book_rows as $bi => $br): ?><th class="num psh psh<?php echo ($bi % 4) + 1; ?>">পার্টনার <?php echo $h($bn($bi + 1)); ?><small>খরচ কাটা</small></th><?php endforeach; ?></tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                        <?php foreach ($page_items as $it):
                            if ($it['t'] === 'empty'): ?>
                            <tr class="pst-empty"><td colspan="<?php echo (int)$ncols[$sec]; ?>"><?php echo $h($it['msg']); ?></td></tr>
                            <?php elseif ($it['t'] === 'cat' || $it['t'] === 'catend'):
                                $cs = $sec === 'sales'
                                    ? ($cat_stat[$it['name']] ?? ['n' => 0, 'qty' => 0, 'sale' => 0, 'profit' => 0, 'shares' => []])
                                    : ($pur_stat[$it['name']] ?? ['n' => 0, 'qty' => 0, 'cost' => 0]);
                                if ($it['t'] === 'cat'): ?>
                            <tr class="pst-cat pst-cat-hdr">
                                <td colspan="<?php echo (int)$ncols[$sec]; ?>">
                                    <div class="pst-cat-wrap">
                                        <span class="pst-cat-tag">খাত</span>
                                        <b class="pst-cat-title"><?php echo $h($it['name']); ?></b>
                                        <?php if (!empty($it['cont'])): ?><span class="pst-cat-cont">চলমান</span><?php endif; ?>
                                        <span class="pst-cat-n"><?php echo $h($bn($cs['n'] ?? 0)); ?>টি লাইন</span>
                                    </div>
                                </td>
                            </tr>
                            <?php elseif ($sec === 'sales'):
                                $cp = (float)($cs['profit'] ?? 0); ?>
                            <tr class="pst-cat-end">
                                <td colspan="4" class="pst-sum-lbl"><b>খাত মোট</b><small><?php echo $h($it['name']); ?></small></td>
                                <td class="num c-qty"><span class="pst-sum-k">পরিমাণ</span><b><?php echo $h($bn($qty_fmt($cs['qty'] ?? 0))); ?></b></td>
                                <td class="num c-amt"><span class="pst-sum-k">বিক্রয়</span><b><?php echo $h($mny_bn($cs['sale'] ?? 0)); ?></b></td>
                                <td class="num <?php echo $tone($cp); ?>"><span class="pst-sum-k">লাভ</span><b><?php echo $h($mny_bn($cp)); ?></b></td>
                                <?php foreach ($book_rows as $bi => $br):
                                    $cv = (float)($cs['shares'][(int)$br['p']['id']] ?? 0); ?>
                                <td class="num psh psh<?php echo ($bi % 4) + 1; ?> <?php echo $tone($cv); ?>"><span class="pst-sum-k">পাৎ. <?php echo $h($bn($bi + 1)); ?></span><b><?php echo $h($mny_bn($cv)); ?></b></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php elseif ($sec === 'purchase'): ?>
                            <tr class="pst-cat-end pst-cat-end-pur">
                                <td colspan="4" class="pst-sum-lbl"><b>খাত মোট</b><small><?php echo $h($it['name']); ?></small></td>
                                <td class="num c-qty"><span class="pst-sum-k">পরিমাণ</span><b><?php echo $h($bn($qty_fmt($cs['qty'] ?? 0))); ?></b></td>
                                <td class="num c-amt"><span class="pst-sum-k">দর</span>—</td>
                                <td class="num c-pur"><span class="pst-sum-k">ক্রয়</span><b><?php echo $h($mny_bn($cs['cost'] ?? 0)); ?></b></td>
                            </tr>
                            <?php endif; ?>
                            <?php elseif ($it['t'] === 's'): $r = $it['r']; $serial['sales']++;
                                $cid = $r['customer_code'] !== '' ? $r['customer_code'] : ((int)$r['customer_id'] > 0 ? (string)$r['customer_id'] : ''); ?>
                            <tr>
                                <td class="sl"><?php echo $h($bn($serial['sales'])); ?></td>
                                <td class="c-two c-dt"><span class="cut d1"><?php echo $h($fmt_date((string)$r['invoice_date'])); ?></span><span class="cut d2"><?php echo $h($r['invoice_no']); ?></span></td>
                                <td class="c-two c-cus"><span class="cut nm"><?php echo $h($r['customer_name']); ?></span><span class="cut cid">আইডি: <?php echo $cid !== '' ? $h($cid) : '—'; ?></span></td>
                                <td class="c-prod"><span class="pcl"><?php echo $h($r['product_name']); ?></span></td>
                                <td class="num c-qty"><?php echo $h($bn($qty_fmt($r['qty'] ?? 0))); ?></td>
                                <td class="num c-amt"><?php echo $h($mny($r['sale_amount'])); ?></td>
                                <td class="num <?php echo $tone($r['profit']); ?>"><?php echo $h($mny($r['profit'])); ?></td>
                                <?php foreach ($book_rows as $bi => $br): ?><td class="num psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($mny($r['shares'][(int)$br['p']['id']] ?? 0)); ?></td><?php endforeach; ?>
                            </tr>
                            <?php elseif ($it['t'] === 'p'): $r = $it['r']; $serial['purchase']++; ?>
                            <tr>
                                <td class="sl"><?php echo $h($bn($serial['purchase'])); ?></td>
                                <td class="c-two c-dt"><span class="cut d1"><?php echo $h($fmt_date((string)$r['date'])); ?></span><span class="cut d2"><?php echo $h($r['memo_no']); ?></span></td>
                                <td class="c-two c-cus"><span class="cut nm"><?php echo $h($r['party_name'] !== '' ? $r['party_name'] : '—'); ?></span><span class="cut cid">আইডি: <?php echo $r['party_code'] !== '' ? $h($r['party_code']) : '—'; ?></span></td>
                                <td class="c-prod"><span class="pcl"><?php echo $h($r['product_name']); ?></span></td>
                                <td class="num c-qty"><?php echo $h($bn($qty_fmt($r['qty']))); ?></td>
                                <td class="num c-amt"><?php echo $h($mny($r['unit_price'])); ?></td>
                                <td class="num c-pur"><?php echo $h($mny($r['total_cost'])); ?></td>
                            </tr>
                            <?php elseif ($it['t'] === 'e'): $r = $it['r']; $serial['expense']++; ?>
                            <tr>
                                <td class="sl"><?php echo $h($bn($serial['expense'])); ?></td>
                                <td class="c-two c-dt"><span class="cut d1"><?php echo $h($fmt_date($r['date'])); ?></span><span class="cut d2"><?php echo $h($r['no']); ?></span></td>
                                <td class="c-cat"><span class="pcl"><?php echo $h($r['category']); ?></span></td>
                                <td class="c-prod"><span class="pcl"><?php echo $h($r['desc']); ?></span></td>
                                <td class="num neg"><?php echo $h($mny($r['amount'])); ?></td>
                                <?php foreach ($book_rows as $bi => $br): ?><td class="num psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($sgn($r['shares'][(int)$br['p']['id']] ?? 0, '-')); ?></td><?php endforeach; ?>
                            </tr>
                            <?php elseif ($it['t'] === 'tot'): ?>
                            <?php if ($sec === 'sales'):
                                $page_profit = 0.0;
                                $page_sale = 0.0;
                                $page_qty = 0.0;
                                $page_sh = [];
                                foreach ($page_items as $pi_it) {
                                    if (($pi_it['t'] ?? '') !== 's' || !isset($pi_it['r'])) {
                                        continue;
                                    }
                                    $pr = $pi_it['r'];
                                    $page_profit += (float)($pr['profit'] ?? 0);
                                    $page_sale += (float)($pr['sale_amount'] ?? 0);
                                    $page_qty += (float)($pr['qty'] ?? 0);
                                    foreach (($pr['shares'] ?? []) as $pid => $sv) {
                                        $page_sh[(int)$pid] = pa_money(($page_sh[(int)$pid] ?? 0) + $sv);
                                    }
                                }
                                $page_profit = round($page_profit, 2);
                                $page_sale = round($page_sale, 2);
                                $page_qty = round($page_qty, 3);
                                $sales_page_count = 0;
                                foreach ($pages as $pgx) {
                                    if (($pgx['sec'] ?? '') === 'sales') {
                                        $sales_page_count++;
                                    }
                                }
                                $show_page_tot = $sales_page_count > 1;
                                if ($show_page_tot): ?>
                            <tr class="pst-ptrow">
                                <td colspan="4"><b>এই পাতার মোট</b></td>
                                <td class="num c-qty"><?php echo $h($bn($qty_fmt($page_qty))); ?></td>
                                <td class="num c-amt"><b><?php echo $h($mny_bn($page_sale)); ?></b></td>
                                <td class="num <?php echo $tone($page_profit); ?>"><b><?php echo $h($mny_bn($page_profit)); ?></b></td>
                                <?php foreach ($book_rows as $bi => $br):
                                    $psum = (float)($page_sh[(int)$br['p']['id']] ?? 0); ?>
                                <td class="num psh psh<?php echo ($bi % 4) + 1; ?> <?php echo $tone($psum); ?>"><b><?php echo $h($mny_bn($psum)); ?></b></td>
                                <?php endforeach; ?>
                            </tr>
                                <?php endif; ?>
                            <tr class="pst-trow">
                                <td colspan="4" class="pst-trow-lbl"><b>সর্বমোট</b> <span class="pst-trow-meta"><?php echo $h($bn(count($lines))); ?>টি লাইন<?php echo $more ? '+' : ''; ?></span></td>
                                <td class="num c-qty"><span class="pst-sum-k">পরিমাণ</span><b><?php echo $h($bn($qty_fmt($total_sale_qty))); ?></b></td>
                                <td class="num c-amt"><span class="pst-sum-k">বিক্রয়</span><b><?php echo $h($mny_bn($total_sale)); ?></b></td>
                                <td class="num <?php echo $tone($total); ?>"><span class="pst-sum-k">লাভ</span><b><?php echo $h($mny_bn($total)); ?></b></td>
                                <?php foreach ($book_rows as $bi => $br):
                                    $psum = (float)($sales_share_sum[(int)$br['p']['id']] ?? 0); ?>
                                <td class="num psh psh<?php echo ($bi % 4) + 1; ?> <?php echo $tone($psum); ?>"><span class="pst-sum-k">পাৎ. <?php echo $h($bn($bi + 1)); ?></span><b><?php echo $h($mny_bn($psum)); ?></b></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php elseif ($sec === 'purchase'): ?>
                            <tr class="pst-trow">
                                <td colspan="6"><b>সর্বমোট ক্রয়</b><span><?php echo $h($bn(count($purchases))); ?>টি এন্ট্রি<?php echo $pur_more ? '+' : ''; ?></span></td>
                                <td class="num c-pur"><?php echo $h($mny($total_pur)); ?></td>
                            </tr>
                            <?php else: ?>
                            <tr class="pst-trow">
                                <td colspan="4"><b>সর্বমোট খরচ</b><span><?php echo $h($bn(count($expenses))); ?>টি খরচ</span></td>
                                <td class="num neg"><?php echo $h($mny($total_exp)); ?></td>
                                <?php foreach ($book_rows as $bi => $br): ?><td class="num psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($sgn($exp_share_sum[(int)$br['p']['id']] ?? 0, '-')); ?></td><?php endforeach; ?>
                            </tr>
                            <?php endif; ?>
                            <?php endif;
                        endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($sec === 'cats'): ?>
                <div class="pst-minis">
                    <?php foreach ($page_items as $blk): ?>
                    <?php if ($blk['k'] === 'sales'): ?>
                    <div class="psp-sec sales">
                        <div class="psp-sec-title"><span class="psp-sec-dot"></span><span class="psp-sec-name">খাত অনুযায়ী বিক্রয় লাভ</span><span class="psp-sec-sub"><?php echo $h($range_on ? $period : $month_label); ?></span></div>
                        <table class="pst-mini" data-ame-no-page="1">
                            <colgroup><col><col style="width:64px"><col style="width:64px"><col style="width:100px"><col style="width:90px"><?php echo str_repeat('<col style="width:' . ($np <= 2 ? 88 : 76) . 'px">', count($book_rows)); ?></colgroup>
                            <thead><tr><th>খাত</th><th class="num">লাইন</th><th class="num">পরিমাণ</th><th class="num">বিক্রয়</th><th class="num">লাভ</th><?php foreach ($book_rows as $bi => $br): ?><th class="num psh psh<?php echo ($bi % 4) + 1; ?>">পার্টনার <?php echo $h($bn($bi + 1)); ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                            <?php foreach ($blk['rows'] as [$cn, $cs]): ?>
                                <tr><td><?php echo $h($cn); ?></td><td class="num"><?php echo $h($bn((int)$cs['n'])); ?>টি</td><td class="num c-qty"><?php echo $h($bn($qty_fmt($cs['qty'] ?? 0))); ?></td><td class="num c-amt"><?php echo $h($mny($cs['sale'])); ?></td><td class="num <?php echo $tone($cs['profit']); ?>"><?php echo $h($mny($cs['profit'])); ?></td><?php foreach ($book_rows as $bi => $br): ?><td class="num psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($mny($cs['shares'][(int)$br['p']['id']] ?? 0)); ?></td><?php endforeach; ?></tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr><td>মোট</td><td class="num"><?php echo $h($bn(count($lines))); ?>টি</td><td class="num c-qty"><?php echo $h($bn($qty_fmt($total_sale_qty))); ?></td><td class="num"><?php echo $h($mny($total_sale)); ?></td><td class="num"><?php echo $h($mny($total)); ?></td><?php foreach ($book_rows as $bi => $br): ?><td class="num psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($mny($sales_share_sum[(int)$br['p']['id']] ?? 0)); ?></td><?php endforeach; ?></tr></tfoot>
                        </table>
                    </div>
                    <?php elseif ($blk['k'] === 'purchase'): ?>
                    <div class="psp-sec purchase">
                        <div class="psp-sec-title"><span class="psp-sec-dot"></span><span class="psp-sec-name">খাত অনুযায়ী ক্রয়</span><span class="psp-sec-sub"><?php echo $h($range_on ? $period : $month_label); ?></span></div>
                        <table class="pst-mini" data-ame-no-page="1">
                            <colgroup><col><col style="width:72px"><col style="width:72px"><col style="width:130px"></colgroup>
                            <thead><tr><th>খাত</th><th class="num">এন্ট্রি</th><th class="num">পরিমাণ</th><th class="num">ক্রয় মূল্য</th></tr></thead>
                            <tbody>
                            <?php
                            $pur_qty_total = 0.0;
                            foreach ($purchases as $pr) {
                                $pur_qty_total += (float)($pr['qty'] ?? 0);
                            }
                            $pur_qty_total = round($pur_qty_total, 3);
                            foreach ($blk['rows'] as [$cn, $cs]): ?>
                                <tr><td><?php echo $h($cn); ?></td><td class="num"><?php echo $h($bn((int)$cs['n'])); ?>টি</td><td class="num c-qty"><?php echo $h($bn($qty_fmt($cs['qty'] ?? 0))); ?></td><td class="num c-pur"><?php echo $h($mny($cs['cost'])); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr><td>মোট</td><td class="num"><?php echo $h($bn(count($purchases))); ?>টি</td><td class="num c-qty"><?php echo $h($bn($qty_fmt($pur_qty_total))); ?></td><td class="num"><?php echo $h($mny($total_pur)); ?></td></tr></tfoot>
                        </table>
                    </div>
                    <?php elseif ($blk['k'] === 'exp'): ?>
                    <div class="psp-sec exp">
                        <div class="psp-sec-title"><span class="psp-sec-dot"></span><span class="psp-sec-name">খাত অনুযায়ী খরচ</span><span class="psp-sec-sub"><?php echo $h($month_label); ?></span></div>
                        <table class="pst-mini" data-ame-no-page="1">
                            <colgroup><col><col style="width:90px"><col style="width:150px"></colgroup>
                            <thead><tr><th>খাত</th><th class="num">সংখ্যা</th><th class="num">টাকা</th></tr></thead>
                            <tbody>
                            <?php foreach ($blk['rows'] as [$cn, $cs]): ?>
                                <tr><td><?php echo $h($cn); ?></td><td class="num"><?php echo $h($bn((int)$cs['n'])); ?>টি</td><td class="num neg"><?php echo $h($mny($cs['amount'])); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr><td>মোট</td><td class="num"><?php echo $h($bn(count($expenses))); ?>টি</td><td class="num"><?php echo $h($mny($total_exp)); ?></td></tr></tfoot>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="psp-sec wd">
                        <div class="psp-sec-title"><span class="psp-sec-dot"></span><span class="psp-sec-name">পার্টনারদের উত্তোলন / পরিশোধ</span><span class="psp-sec-sub"><?php echo $h($month_label); ?></span></div>
                        <table class="pst-mini pst-wd" data-ame-no-page="1">
                            <colgroup><col style="width:104px"><col style="width:140px"><col><col style="width:116px"></colgroup>
                            <thead><tr><th>তারিখ</th><th>পার্টনার</th><th>বিবরণ</th><th class="num">টাকা</th></tr></thead>
                            <tbody>
                            <?php foreach ($blk['rows'] as $wi): ?>
                                <tr><td class="c-date"><?php echo $h($fmt_date($wi['date'])); ?></td><td class="nm"><?php echo $h($wi['partner']); ?></td><td><?php echo $h($wi['description']); ?></td><td class="num neg"><?php echo $h($sgn($wi['amount'], '-')); ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (count($wd_items) > count($blk['rows'])): ?>
                                <tr><td colspan="4" class="more">আরও <?php echo $h($bn(count($wd_items) - count($blk['rows']))); ?>টি — বিস্তারিত পার্টনার ড্যাশবোর্ডে</td></tr>
                            <?php endif; ?>
                            </tbody>
                            <tfoot><tr><td colspan="3">মোট উত্তোলন / পরিশোধ</td><td class="num"><?php echo $h($mny($sum_draw)); ?></td></tr></tfoot>
                        </table>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="psp-sec set">
                    <div class="psp-sec-title">
                        <span class="psp-sec-dot"></span>
                        <span class="psp-sec-name">পার্টনার হিসাবের সারসংক্ষেপ</span>
                        <span class="psp-sec-sub"><?php echo $h($range_on ? $period : $month_label); ?></span>
                    </div>
                    <table class="pst-period" data-ame-no-page="1">
                        <thead>
                            <tr>
                                <th>পার্টনার</th>
                                <th class="num">সাবেক ব্যালেন্স</th>
                                <th class="num">(+) লাভের অংশ</th>
                                <th class="num">(−) খরচ কাটা</th>
                                <th class="num">= সময়কাল নিট</th>
                                <th class="num">মাস শেষ ব্যালেন্স</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($book_rows as $bi => $br):
                            $net = isset($br['net']) ? (float)$br['net'] : round((float)$br['profit'] - (float)$br['exp'], 2); ?>
                            <tr>
                                <td class="nm psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($br['p']['name']); ?></td>
                                <td class="num"><?php echo $h($mny($br['open'])); ?></td>
                                <td class="num pos"><?php echo $h($sgn($br['profit'], '+')); ?></td>
                                <td class="num neg"><?php echo $h($sgn($br['exp'], '-')); ?></td>
                                <td class="num <?php echo $tone($net); ?>"><?php echo $h($mny($net)); ?></td>
                                <td class="num hi"><?php echo $h($mny($br['total'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><b>মোট</b></td>
                                <td class="num"><?php echo $h($mny($sum_open)); ?></td>
                                <td class="num pos"><?php echo $h($sgn($sum_profit, '+')); ?></td>
                                <td class="num neg"><?php echo $h($sgn($sum_exp, '-')); ?></td>
                                <td class="num <?php echo $tone($sum_period_net); ?>"><b><?php echo $h($mny($sum_period_net)); ?></b></td>
                                <td class="num"><b><?php echo $h($mny($sum_total)); ?></b></td>
                            </tr>
                        </tfoot>
                    </table>
                    <p class="psp-period-hint">উপরে বিক্রয়/খরচের তালিকায় প্রতি লাইনে পার্টনারের অংশ। নিচে প্লাস/মাইনাস সারসংক্ষেপ — সাবেক + লাভ − খরচ − উত্তোলন (সব অংক সার্ভার)।</p>
                    <table class="pst-set" data-ame-no-page="1">
                        <colgroup><col><?php foreach ($book_rows as $br): ?><col style="width:<?php echo (int)$pcol; ?>px"><?php endforeach; ?><col style="width:150px"></colgroup>
                        <thead>
                            <tr>
                                <th>বিবরণ</th>
                                <?php foreach ($book_rows as $bi => $br): ?><th class="num psh psh<?php echo ($bi % 4) + 1; ?>"><span class="nm"><?php echo $h($br['p']['name']); ?></span></th><?php endforeach; ?>
                                <th class="num">দুজনের মোট</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="r-open">
                                <td class="lbl">সাবেক ব্যালেন্স <small>আগের হিসাব পর্যন্ত</small></td>
                                <?php foreach ($book_rows as $bi => $br): ?><td class="num psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($mny($br['open'])); ?></td><?php endforeach; ?>
                                <td class="num tot <?php echo $tone($sum_open); ?>"><?php echo $h($mny($sum_open)); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">(+) <?php echo $h($sum_word); ?> লাভ <small>শেয়ার অনুযায়ী</small></td>
                                <?php foreach ($book_rows as $br): ?><td class="num pos"><?php echo $h($sgn($br['profit'], '+')); ?></td><?php endforeach; ?>
                                <td class="num tot pos"><?php echo $h($sgn($sum_profit, '+')); ?></td>
                            </tr>
                            <tr>
                                <td class="lbl">(−) <?php echo $h($sum_word); ?> খরচ <small>শেয়ার অনুযায়ী</small></td>
                                <?php foreach ($book_rows as $br): ?><td class="num neg"><?php echo $h($sgn($br['exp'], '-')); ?></td><?php endforeach; ?>
                                <td class="num tot neg"><?php echo $h($sgn($sum_exp, '-')); ?></td>
                            </tr>
                            <tr class="net">
                                <td class="lbl"><?php echo $h($sum_word); ?> নিট লাভ <small>লাভ − খরচ</small></td>
                                <?php foreach ($net_profit_rows as $ni => $nv): ?><td class="num psh psh<?php echo ($ni % 4) + 1; ?>"><?php echo $h($mny($nv)); ?></td><?php endforeach; ?>
                                <td class="num tot <?php echo $tone($sum_net); ?>"><?php echo $h($mny($sum_net)); ?></td>
                            </tr>
                            <tr class="r-draw">
                                <td class="lbl">(−) উত্তোলন / পরিশোধ <small>পার্টনার যা নিয়েছেন</small></td>
                                <?php foreach ($book_rows as $bi => $br): ?><td class="num psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($sgn($br['draw'], '-')); ?></td><?php endforeach; ?>
                                <td class="num tot neg"><?php echo $h($sgn($sum_draw, '-')); ?></td>
                            </tr>
                            <?php if ($show_other): ?>
                            <tr>
                                <td class="lbl">(±) মূলধন জমা / সমন্বয়</td>
                                <?php foreach ($book_rows as $bi => $br): ?><td class="num psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($sgn($br['other'], $br['other'] < 0 ? '-' : '+')); ?></td><?php endforeach; ?>
                                <td class="num tot <?php echo $tone($sum_other); ?>"><?php echo $h($sgn($sum_other, $sum_other < 0 ? '-' : '+')); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="grand">
                                <td class="lbl">সর্বমোট ব্যালেন্স</td>
                                <?php foreach ($book_rows as $bi => $br): ?><td class="num psh psh<?php echo ($bi % 4) + 1; ?>"><?php echo $h($mny($br['total'])); ?></td><?php endforeach; ?>
                                <td class="num tot"><?php echo $h($mny($sum_total)); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="pst-calc">
                        <div class="cl-formula">সর্বমোট ব্যালেন্স = সাবেক ব্যালেন্স + <?php echo $h($sum_word); ?> লাভ − <?php echo $h($sum_word); ?> খরচ − উত্তোলন<?php echo $show_other ? ' ± মূলধন/সমন্বয়' : ''; ?></div>
                        <div class="cl-row"><span class="k">দুজনের মোট</span><span class="v"><?php echo $h($mny($sum_open)); ?> + <?php echo $h($mny($sum_profit)); ?> − <?php echo $h($mny($sum_exp)); ?> − <?php echo $h($mny($sum_draw)); ?><?php echo $show_other ? ' ' . ($sum_other < 0 ? '−' : '+') . ' ' . $h($mny(abs($sum_other))) : ''; ?> = <b><?php echo $h($mny($sum_total)); ?></b></span></div>
                        <?php foreach ($book_rows as $bi => $br): ?>
                        <div class="cl-row psh psh<?php echo ($bi % 4) + 1; ?>"><span class="k"><?php echo $h($br['p']['name']); ?></span><span class="v"><?php echo $h($mny($br['open'])); ?> + <?php echo $h($mny($br['profit'])); ?> − <?php echo $h($mny($br['exp'])); ?> − <?php echo $h($mny($br['draw'])); ?><?php echo $show_other ? ' ' . ($br['other'] < 0 ? '−' : '+') . ' ' . $h($mny(abs($br['other']))) : ''; ?> = <b><?php echo $h($mny($br['total'])); ?></b></span></div>
                        <?php endforeach; ?>
                        <div class="cl-net"><span class="k"><?php echo $h($sum_word); ?> নিট লাভ <small>(লাভ − খরচ)</small></span><span class="v"><?php echo $h($mny($sum_profit)); ?> − <?php echo $h($mny($sum_exp)); ?> = <b class="<?php echo $tone($sum_net); ?>"><?php echo $h($mny($sum_net)); ?></b></span></div>
                    </div>
                    <?php if ($partial_range): ?><div class="psp-formula note">PA-INTL-100 · সব অংক সার্ভার থেকে — সময়কাল <?php echo $h($period); ?>। বিক্রয় লাভ, ক্রয়, খরচ বিবরণ ও সারসংক্ষেপ একই তারিখ সীমায়।</div><?php endif; ?>
                </div>
                <div class="psp-sign">
                    <?php foreach ($book_rows as $bi => $br): ?>
                    <div class="psp-sig" data-sig="p<?php echo (int)$br['p']['id']; ?>" role="button" tabindex="0"><i><img class="psp-sig-img" alt="" hidden></i><span class="psp-sig-name"><?php echo $h($br['p']['name']); ?></span><em class="psp-sig-hint">সই করতে চাপুন</em></div>
                    <?php endforeach; ?>
                    <div class="psp-sig" data-sig="prep" role="button" tabindex="0"><i><img class="psp-sig-img" alt="" hidden></i><span class="psp-sig-name">হিসাব প্রস্তুতকারক</span><em class="psp-sig-hint">সই করতে চাপুন</em></div>
                </div>
            <?php endif; ?>
            </div>
            <footer class="psp-foot">
                <span><?php echo $h($shop); ?> · <?php echo $h($doc_no); ?> · <?php echo $h($partner_label); ?></span>
                <span>পাতা <?php echo $h($bn($page_no)); ?> / <?php echo $h($bn($page_count)); ?></span>
            </footer>
        </section>
        <?php endforeach; ?>
    </div>
</div>
<div class="psp-busy no-print" id="pspBusy" hidden>তৈরি হচ্ছে…</div>
<div class="psp-ready no-print" id="pspReady" hidden>
    <div class="psp-ready-box">
        <p id="pspReadyText">ফাইল তৈরি হয়েছে। নিচের বোতামে চেপে সরাসরি শেয়ার করুন।</p>
        <button type="button" class="psp-btn share" id="pspReadyGo">শেয়ার করুন</button>
        <button type="button" class="psp-btn soft" id="pspReadyCancel">পরে</button>
    </div>
</div>
<div class="psp-sigmodal no-print" id="pspSigModal" hidden>
    <div class="psp-sigbox">
        <div class="psp-sigtitle" id="pspSigTitle">সই করুন</div>
        <canvas id="pspSigCanvas" width="640" height="260"></canvas>
        <div class="psp-sigtip">আঙুল বা কলম দিয়ে উপরের সাদা ঘরে সই করুন।</div>
        <div class="psp-sigbtns">
            <button type="button" class="psp-btn soft" data-sig-act="clear">মুছুন</button>
            <button type="button" class="psp-btn soft" data-sig-act="remove">সই বাদ দিন</button>
            <button type="button" class="psp-btn soft" data-sig-act="cancel">বাতিল</button>
            <button type="button" class="psp-btn wide" data-sig-act="save">সই সংরক্ষণ</button>
            <button type="button" class="psp-btn share wide" data-sig-act="save-share">সই করে শেয়ার</button>
        </div>
    </div>
</div>
<script src="/assets/js/html2canvas.min.js?v=<?php echo $ver; ?>"></script>
<script src="/assets/js/jspdf.umd.min.js?v=<?php echo $ver; ?>"></script>
<script src="/assets/js/pa-sales-share.js?v=<?php echo $ver; ?>" defer></script>
<?php include '../../partials/footer.php'; ?>
