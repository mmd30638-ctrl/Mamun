<?php
/**
 * Memo paper — one place for sale / purchase print & share.
 * সাবেক + এই বিল − পরিশোধ = সর্বমোট। ফোলা SUM(debit) কাগজে বসে না।
 * লেবেল ভাষা বদলায়। টাকার অঙ্ক ও হিসাবের সূত্র আগের মতো।
 */
if (!function_exists('ame_ui')) {
    require_once __DIR__ . '/memo_lang.php';
}

function ame_memo_paper_looks_inflated(float $amount, float $bill): bool
{
    // পুরনো লেজারে SUM(debit) ফুলে সাবেক বকেয়া হয় (যেমন +৩৩৬৩৪০)।
    // ঋণাত্মক সংখ্যা আসল অগ্রিম জমা। ছোট বিলের সাথে মিলিয়ে সেটা ০ করা যাবে না।
    if ($amount <= 50000.0) {
        return false;
    }
    $billAbs = max(abs($bill), 1.0);
    return $amount > ($billAbs * 15.0);
}

function ame_memo_paper_num($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return round((float)$value, 2);
}

/**
 * @param array{
 *   bill:float,
 *   paid?:float,
 *   extra?:float,
 *   stamp_prev?:float|null,
 *   stamp_after?:float|null,
 *   run_prev?:float|null,
 *   run_after?:float|null,
 *   sum_prev?:float|null,
 *   sum_after?:float|null,
 *   payable?:float|null,
 *   receivable?:float|null,
 *   supplier_due?:float|null,
 *   party_net?:float|null
 * } $in
 * @return array{previous:float,current:float,bill:float,paid:float,extra:float,total:float,src:string,kind:string}
 */
function ame_memo_paper_pick(string $kind, array $in): array
{
    if (!function_exists('ame_balance_book')) {
        require_once __DIR__ . '/memo_functions.php';
    }
    $kind = ame_balance_book_norm_kind($kind);
    $bill = round((float)($in['bill'] ?? 0), 2);
    $paid = round((float)($in['paid'] ?? 0), 2);
    $extra = round((float)($in['extra'] ?? 0), 2);
    if ($extra < 0) {
        $extra = 0.0;
    }
    $afterOf = static function (float $prev) use ($kind, $bill, $paid, $extra): float {
        return ame_balance_book_after_amount($kind, $prev, $bill, $paid, $extra);
    };
    $prevOf = static function (float $after) use ($kind, $bill, $paid, $extra): float {
        return ame_balance_book_previous_amount($kind, $after, $bill, $paid, $extra);
    };

    $stampPrev = ame_memo_paper_num($in['stamp_prev'] ?? null);
    $stampAfter = ame_memo_paper_num($in['stamp_after'] ?? null);
    $runPrev = ame_memo_paper_num($in['run_prev'] ?? null);
    $runAfter = ame_memo_paper_num($in['run_after'] ?? null);
    $sumPrev = ame_memo_paper_num($in['sum_prev'] ?? null);
    $sumAfter = ame_memo_paper_num($in['sum_after'] ?? null);
    $payable = ame_memo_paper_num($in['payable'] ?? null);
    $receivable = ame_memo_paper_num($in['receivable'] ?? null);
    $supplierDue = ame_memo_paper_num($in['supplier_due'] ?? null);
    $partyNet = ame_memo_paper_num($in['party_net'] ?? null);

    $use = static function (float $prev, float $after, string $src) use ($kind, $bill, $paid, $extra): array {
        $book = ame_balance_book($kind, $prev, $bill, $paid, $after, $extra);
        $book['src'] = $src;
        $book['kind'] = $kind;
        return $book;
    };

    // শূন্য running সাবেক জমা ঢেকে দেয় না — আগের সারি না থাকলে ০ মানে "অজানা"।
    if ($runPrev !== null && abs($runPrev) > 0.009 && !ame_memo_paper_looks_inflated($runPrev, $bill)) {
        $after = $runAfter !== null && !ame_memo_paper_looks_inflated($runAfter, $bill)
            ? $runAfter
            : $afterOf($runPrev);
        return $use($runPrev, $after, 'running');
    }

    if ($stampPrev !== null && abs($stampPrev) > 0.009 && !ame_memo_paper_looks_inflated($stampPrev, $bill)) {
        $after = $stampAfter !== null && !ame_memo_paper_looks_inflated($stampAfter, $bill)
            ? $stampAfter
            : $afterOf($stampPrev);
        if ($stampAfter !== null && ame_memo_paper_looks_inflated($stampAfter, $bill)) {
            $after = $afterOf($stampPrev);
        }
        return $use($stampPrev, $after, 'stamp');
    }

    $heldDeposit = ($payable !== null && $payable > 0.009 ? $payable : 0.0)
        + ($supplierDue !== null && $supplierDue > 0.009 ? $supplierDue : 0.0);
    $sumIsDeposit = $sumPrev !== null && $sumPrev < -0.009 && !ame_memo_paper_looks_inflated($sumPrev, $bill);
    $partyIsDeposit = $partyNet !== null && $partyNet < -0.009 && !ame_memo_paper_looks_inflated($partyNet, $bill);
    $depositWaiting = $heldDeposit > 0.009 || $sumIsDeposit || $partyIsDeposit;

    if ($runAfter !== null && !ame_memo_paper_looks_inflated($runAfter, $bill)) {
        $fromZero = $afterOf(0.0);
        if (abs($runAfter - $fromZero) > 0.02) {
            $prev = $prevOf($runAfter);
            if (abs($prev) > 0.009 && !ame_memo_paper_looks_inflated($prev, $bill)) {
                if (!$depositWaiting || $prev < -0.009) {
                    return $use($prev, $runAfter, 'running_after');
                }
            }
        }
    }

    if ($stampAfter !== null && !ame_memo_paper_looks_inflated($stampAfter, $bill)) {
        $prev = $prevOf($stampAfter);
        if (abs($prev) > 0.009 && !ame_memo_paper_looks_inflated($prev, $bill)) {
            if (!$depositWaiting || $prev < -0.009) {
                return $use($prev, $stampAfter, 'implied_after');
            }
        }
    }

    // লেজার SUM(debit−credit) — running ০ হলেও আগের ক্রেডিট জমা এখানে থাকে।
    if ($sumPrev !== null && abs($sumPrev) > 0.009 && !ame_memo_paper_looks_inflated($sumPrev, $bill)) {
        $after = $sumAfter !== null && !ame_memo_paper_looks_inflated($sumAfter, $bill)
            ? $sumAfter
            : $afterOf($sumPrev);
        return $use($sumPrev, $after, 'sum_prev');
    }

    if ($sumAfter !== null && !ame_memo_paper_looks_inflated($sumAfter, $bill)) {
        $fromZero = $afterOf(0.0);
        if (abs($sumAfter - $fromZero) > 0.02) {
            $prev = $prevOf($sumAfter);
            if (abs($prev) > 0.009 && !ame_memo_paper_looks_inflated($prev, $bill)) {
                return $use($prev, $sumAfter, 'sum_after');
            }
        }
    }

    if ($kind === 'sale' && ($payable === null || $payable < 0.009)
        && $supplierDue !== null && $supplierDue > 0.009
        && !ame_memo_paper_looks_inflated($supplierDue, $bill)) {
        $payable = $supplierDue;
    }

    if ($kind === 'sale' && $payable !== null && $payable > 0.009 && !ame_memo_paper_looks_inflated($payable, $bill)) {
        $prev = round(-$payable, 2);
        return $use($prev, $afterOf($prev), 'payable');
    }

    if ($kind === 'sale' && $partyNet !== null && abs($partyNet) > 0.009
        && !ame_memo_paper_looks_inflated($partyNet, $bill)) {
        $fromZero = $afterOf(0.0);
        $recNow = $receivable;
        $depositHeld = ($payable !== null && $payable > 0.009 ? $payable : 0.0)
            + ($supplierDue !== null && $supplierDue > 0.009 ? $supplierDue : 0.0);
        if ($recNow !== null && ($recNow < -0.009 || abs($recNow - $partyNet) < 0.02)) {
            $prev = $prevOf($partyNet);
            if (abs($prev) > 0.009 && !ame_memo_paper_looks_inflated($prev, $bill)) {
                return $use($prev, $partyNet, 'party_after');
            }
        }
        if ($depositHeld > 0.009 && $recNow !== null && abs($recNow) < 0.009) {
            return $use($partyNet, $afterOf($partyNet), 'party_prev');
        }
        if ($depositHeld > 0.009 && $recNow !== null && abs($recNow - $fromZero) < 0.02) {
            $prev = $prevOf($partyNet);
            if (abs($prev) > 0.009 && !ame_memo_paper_looks_inflated($prev, $bill)) {
                return $use($prev, $partyNet, 'party_after');
            }
        }
        if (abs($partyNet - $fromZero) > 0.02) {
            $prev = $prevOf($partyNet);
            if ($prev < -0.009 && !ame_memo_paper_looks_inflated($prev, $bill)) {
                return $use($prev, $partyNet, 'party_after');
            }
            if ($partyNet < -0.009) {
                return $use($partyNet, $afterOf($partyNet), 'party_prev');
            }
        }
    }

    if ($kind === 'sale' && $receivable !== null && !ame_memo_paper_looks_inflated($receivable, $bill)) {
        $prev = $prevOf($receivable);
        if (abs($prev) > 0.009 && !ame_memo_paper_looks_inflated($prev, $bill)) {
            return $use($prev, $receivable, 'receivable');
        }
    }

    $prev = $stampPrev ?? 0.0;
    $after = $stampAfter ?? $afterOf($prev);
    if (ame_memo_paper_looks_inflated($prev, $bill)) {
        if ($sumPrev !== null && abs($prev - $sumPrev) < 0.05) {
            $prev = 0.0;
            $after = $afterOf(0.0);
            return $use($prev, $after, 'sum_zeroed');
        }
        $prev = 0.0;
        $after = $afterOf(0.0);
        return $use($prev, $after, 'inflated_zeroed');
    }
    return $use($prev, $after, $stampPrev !== null ? 'stamp_raw' : 'zero');
}

function ame_memo_paper_labels(array $paper): array
{
    $kind = (string)($paper['kind'] ?? 'sale');
    $prev = (float)($paper['previous'] ?? 0);
    $total = (float)($paper['total'] ?? 0);
    if ($kind === 'purchase_customer') {
        return [
            'previous' => $prev < -0.009 ? ame_ui('সাবেক জমা · দোকানের কাছে পাওনা ছিল', 'Previous deposit · shop owed the customer') : ($prev > 0.009 ? ame_ui('সাবেক · দোকান পাবে', 'Previous · shop will receive') : ame_ui('সাবেক', 'Previous')),
            'bill' => ame_ui('এই ক্রয় (−)', 'This purchase (−)'),
            'paid' => ame_ui('পরিশোধ (+)', 'Paid (+)'),
            'total' => $total < -0.009 ? ame_ui('কাস্টমার পাবে', 'Customer will receive') : ($total > 0.009 ? ame_ui('দোকান পাবে', 'Shop will receive') : ame_ui('সর্বমোট', 'Total balance')),
        ];
    }
    if ($kind === 'purchase_supplier') {
        return [
            'previous' => $prev < -0.009 ? ame_ui('সাবেক · সাপ্লায়ারের কাছে পাওনা', 'Previous · owed by the supplier') : ($prev > 0.009 ? ame_ui('সাবেক · দোকান দেবে', 'Previous · shop will pay') : ame_ui('সাবেক', 'Previous')),
            'bill' => ame_ui('এই ক্রয় (+)', 'This purchase (+)'),
            'paid' => ame_ui('পরিশোধ (−)', 'Paid (−)'),
            'total' => $total < -0.009 ? ame_ui('সাপ্লায়ার পাবে না', 'Supplier will not receive') : ($total > 0.009 ? ame_ui('দোকান দেবে', 'Shop will pay') : ame_ui('সর্বমোট', 'Total balance')),
        ];
    }
    return [
        'previous' => $prev < -0.009 ? ame_ui('সাবেক জমা · দোকানের কাছে পাওনা ছিল', 'Previous deposit · shop owed the customer') : ($prev > 0.009 ? ame_ui('সাবেক · দোকান পাবে', 'Previous · shop will receive') : ame_ui('সাবেক', 'Previous')),
        'bill' => ame_ui('পণ্য ক্রয় (+)', 'Product purchase (+)'),
        'paid' => ame_ui('পরিশোধ (−)', 'Paid (−)'),
        'total' => $total < -0.009 ? ame_ui('কাস্টমার পাবে', 'Customer will receive') : ($total > 0.009 ? ame_ui('দোকান পাবে', 'Shop will receive') : ame_ui('সর্বমোট', 'Total balance')),
    ];
}

function ame_memo_paper_money(float $amount, bool $alwaysSign = false): string
{
    $n = round($amount, 2);
    if ($n < -0.009) {
        return '−৳' . number_format(abs($n), 2);
    }
    if ($alwaysSign && $n > 0.009) {
        return '+৳' . number_format($n, 2);
    }
    return '৳' . number_format(abs($n), 2);
}

/**
 * জমা/পাওনা কাটা — লেজার সূত্র বদলায় না।
 * জমা ছিল = সাবেক ঋণাত্মক; কাটা = আগে−পরে (০-এর নিচে নয়); অবশিষ্ট = এখনও ঋণাত্মক হলে।
 *
 * @return array{show:bool,before:float,used:float,after:float}
 */
function ame_memo_deposit_slice(array $paper): array
{
    $prev = round((float) ($paper['previous'] ?? 0), 2);
    $total = round((float) ($paper['total'] ?? 0), 2);
    $before = $prev < -0.009 ? round(abs($prev), 2) : 0.0;
    $after = $total < -0.009 ? round(abs($total), 2) : 0.0;
    $used = round(max(0, $before - $after), 2);
    return [
        'show' => $before > 0.009,
        'before' => $before,
        'used' => $used,
        'after' => $after,
    ];
}

/**
 * কাস্টমার পেমেন্ট রসিদ: সাবেক − পরিশোধ = এখন (জমা হলে ঋণাত্মক)।
 *
 * @return array{previous:float,paid:float,after:float,formula:string,prev_label:string,after_label:string}
 */
function ame_customer_pay_memo_ledger(float $previous, float $paid, ?float $after = null): array
{
    $previous = round($previous, 2);
    $paid = round(max(0, $paid), 2);
    $computed = round($previous - $paid, 2);
    if ($after !== null && abs(round($after, 2) - $computed) <= 0.02) {
        $computed = round($after, 2);
    }
    $money = static function (float $n): string {
        if (function_exists('ame_balance_book_money')) {
            return ame_balance_book_money($n);
        }
        $sign = $n < -0.009 ? '−' : '';
        return $sign . '৳' . number_format(abs($n), 2);
    };
    return [
        'previous' => $previous,
        'paid' => $paid,
        'after' => $computed,
        'remaining_due' => $computed > 0.009 ? $computed : 0.0,
        'advance' => $computed < -0.009 ? round(abs($computed), 2) : 0.0,
        'formula' => $money($previous) . ' − ' . $money($paid) . ' = ' . $money($computed),
        'prev_label' => $previous > 0.009 ? 'সাবেক বকেয়া' : ($previous < -0.009 ? 'সাবেক জমা' : 'সাবেক'),
        'after_label' => $computed > 0.009 ? 'এখন বকেয়া' : ($computed < -0.009 ? 'এখন জমা' : 'এখন'),
    ];
}

function ame_memo_paper_rows_html(array $paper): string
{
    $labels = ame_memo_paper_labels($paper);
    $prev = (float)$paper['previous'];
    $bill = (float)($paper['bill'] ?? $paper['current'] ?? 0);
    $paid = (float)($paper['paid'] ?? 0);
    $extra = (float)($paper['extra'] ?? 0);
    $total = (float)$paper['total'];
    $h = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    };
    $row = static function (string $label, string $value, string $style = '') use ($h): string {
        return '<div class="ame-paper-row sabek-line" style="display:flex;justify-content:space-between;gap:8px;' . $style . '">'
            . '<span>' . $h($label) . '</span><span>' . $h($value) . '</span></div>';
    };
    $html = '<div class="ame-paper-box memo-sum-row sabek-box">';
    $html .= $row($labels['previous'], ame_memo_paper_money($prev, true), $prev < -0.009 ? 'color:#15803D;font-weight:700;' : '');
    $html .= $row($labels['bill'], ame_memo_paper_money($bill, true));
    $kindName = (string)($paper['kind'] ?? 'sale');
    $deposit = round($paid + $extra, 2);
    $fullPay = $kindName === 'sale' && $extra > 0.009 && $deposit > 0.009;
    if ($fullPay) {
        $html .= $row(ame_ui('জমা (−)', 'Deposit (−)'), ame_memo_paper_money(-$deposit, true));
    } elseif ($paid > 0.009) {
        $html .= $row($labels['paid'], ame_memo_paper_money(-$paid, true));
    }
    if (!$fullPay && $extra > 0.009) {
        $html .= $row(ame_ui('সাবেক সমন্বয় (−)', 'Previous adjustment (−)'), ame_memo_paper_money(-$extra, true));
    }
    $totalStyle = $total < -0.009
        ? 'color:#166534;font-weight:800;border-top:1px solid #86EFAC;margin-top:6px;padding-top:6px;'
        : ($total > 0.009
            ? 'color:#991B1B;font-weight:800;border-top:1px solid #F59E0B;margin-top:6px;padding-top:6px;'
            : 'font-weight:800;border-top:1px solid #D1D5DB;margin-top:6px;padding-top:6px;');
    $html .= $row($labels['total'], ame_memo_paper_money($total), $totalStyle);
    $remainDue = $total > 0.009 ? $total : 0.0;
    $advance = $total < -0.009 ? round(abs($total), 2) : 0.0;
    if ($remainDue > 0.009 || $advance > 0.009 || abs($prev) > 0.009 || $paid > 0.009 || $extra > 0.009) {
        $html .= $row(ame_ui('মেমো শেষে অবশিষ্ট বকেয়া', 'Due left on this memo'), '৳' . number_format($remainDue, 2), 'color:#991B1B;font-weight:700;');
        $html .= $row(ame_ui('অগ্রিম জমা', 'Advance deposit'), '৳' . number_format($advance, 2), 'color:#166534;font-weight:700;');
    }
    if ($fullPay) {
        $proof = ame_memo_paper_money($prev, true) . ' + ' . ame_memo_paper_money($bill, true)
            . ' − ' . ame_memo_paper_money($deposit) . ' = ' . ame_memo_paper_money($total);
        $html .= '<div class="ame-paper-proof" style="margin-top:6px;font-size:14px;font-weight:800;color:#0B1E4D;text-align:center;">'
            . $h($proof) . '</div>';
        $oldCut = round(min($extra, max(0, $prev)), 2);
        $billCut = round(max(0, $deposit - $extra), 2);
        $oldLeft = round(max(0, $prev - $oldCut), 2);
        $html .= '<div class="ame-due-cut-box" style="margin-top:8px;padding:8px;border:1px solid #f0e8b8;border-radius:8px;background:#fffceb;">';
        $html .= '<div style="font-weight:800;color:#92400e;margin-bottom:4px;">' . $h(ame_ui('সাবেক বকেয়া থেকে কাটা', 'Cut from previous due')) . '</div>';
        $html .= $row(ame_ui('জমা হয়েছে', 'Deposited'), '৳' . number_format($deposit, 2), 'font-weight:800;');
        $html .= $row(ame_ui('এই বিলে লেগেছে', 'Applied to this bill'), '৳' . number_format($billCut, 2));
        $html .= $row(ame_ui('সাবেক থেকে কাটা (−)', 'Cut from previous (−)'), '−৳' . number_format($oldCut, 2), 'color:#b91c1c;font-weight:800;');
        $html .= $row(ame_ui('সাবেক এখন', 'Previous now'), '৳' . number_format($oldLeft, 2), 'font-weight:800;');
        $html .= '</div>';
    } elseif (function_exists('ame_balance_book_proof')) {
        $html .= '<div class="ame-paper-proof" style="margin-top:6px;font-size:14px;font-weight:800;color:#0B1E4D;text-align:center;">'
            . $h(ame_balance_book_proof($paper)) . '</div>';
    }
    $dep = ame_memo_deposit_slice($paper);
    if (!empty($dep['show'])) {
        $depBefore = (float) $dep['before'];
        $depUsed = (float) $dep['used'];
        $depLeft = (float) $dep['after'];
        $html .= '<div class="ame-deposit-box" style="margin-top:8px;padding:8px;border:1px solid #86EFAC;border-radius:8px;background:#ffffff;">';
        $html .= '<div style="font-weight:800;color:#166534;margin-bottom:4px;">' . $h(ame_ui('সাবেক জমা সমন্বয়', 'Previous deposit adjustment')) . '</div>';
        $html .= $row(ame_ui('জমা ছিল (+)', 'Deposit was (+)'), '+৳' . number_format($depBefore, 2), 'color:#15803d;font-weight:800;');
        $html .= $row(ame_ui('এই বিল থেকে কাটা (−)', 'Cut from this bill (−)'), '−৳' . number_format($depUsed, 2), 'color:#b91c1c;font-weight:800;');
        $html .= $row(ame_ui('রয়ে গেল (+)', 'Left (+)'), '+৳' . number_format($depLeft, 2), 'color:#166534;font-weight:800;');
        $html .= '<div class="ame-paper-proof" style="margin-top:4px;font-size:14px;font-weight:800;color:#0B1E4D;text-align:center;">'
            . $h('+৳' . number_format($depBefore, 2) . ' − ৳' . number_format($depUsed, 2) . ' = +৳' . number_format($depLeft, 2))
            . '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
