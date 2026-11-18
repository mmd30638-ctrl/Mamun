<?php
/**
 * ক্রয় মেমো — বিক্রয় ইনভয়েসের একই কাগজ। হিসাব বদলায় না।
 */

if (!function_exists('ame_memo_paper_rows_html')) {
    require_once __DIR__ . '/memo_paper.php';
}

function ame_purchase_memo_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * এক মেমোর নাম এক রেকর্ড থেকে। supplier_id থাকলে সেই সাপ্লায়ারই মেমোর পরিচয়।
 * লিংক করা কাস্টমারের নাম একই কাগজে বসানো হয় না।
 */
function ame_purchase_memo_party(array $memo): array
{
    $supplier_id = (int) ($memo['supplier_id'] ?? 0);
    $customer_id = (int) ($memo['customer_id'] ?? 0);
    if ($supplier_id > 0) {
        return [
            'role' => 'supplier',
            'name' => trim((string) ($memo['supplier_name'] ?? '')),
            'phone' => trim((string) ($memo['supplier_phone'] ?? '')),
            'code' => '',
        ];
    }
    if ($customer_id > 0 || !empty($memo['is_customer'])) {
        return [
            'role' => 'customer',
            'name' => trim((string) ($memo['customer_name'] ?? '')),
            'phone' => trim((string) ($memo['customer_phone'] ?? '')),
            'code' => trim((string) ($memo['customer_code'] ?? '')),
        ];
    }
    return [
        'role' => 'supplier',
        'name' => trim((string) ($memo['supplier_name'] ?? $memo['customer_name'] ?? '')),
        'phone' => trim((string) ($memo['supplier_phone'] ?? '')),
        'code' => '',
    ];
}

function ame_purchase_memo_paper_kind(array $memo): string
{
    if ((int) ($memo['supplier_id'] ?? 0) > 0) {
        return 'purchase_supplier';
    }
    $due = is_array($memo['due'] ?? null) ? $memo['due'] : [];
    $book = is_array($due['book'] ?? null) ? $due['book'] : [];
    if (($book['kind'] ?? '') === 'purchase_customer' || !empty($due['is_customer']) || !empty($memo['is_customer'])) {
        return 'purchase_customer';
    }
    return 'purchase_supplier';
}

function ame_purchase_memo_paper_book(array $memo): array
{
    $due = is_array($memo['due'] ?? null) ? $memo['due'] : [];
    $book = is_array($due['book'] ?? null) ? $due['book'] : [];
    $kind = ame_purchase_memo_paper_kind($memo);
    $bill = round((float) ($memo['invoice_total'] ?? $due['current'] ?? 0), 2);
    $paid = round((float) ($due['paid'] ?? 0), 2);
    $prev = round((float) ($due['previous'] ?? 0), 2);
    $after = $due['total'] ?? null;
    if (function_exists('ame_balance_book')) {
        $book = ame_balance_book($kind, $prev, $bill, $paid, $after);
    }
    if ($book === []) {
        $book = [
            'previous' => $prev,
            'bill' => $bill,
            'current' => $bill,
            'paid' => $paid,
            'extra' => 0.0,
            'total' => $after !== null ? round((float) $after, 2) : round($prev + $bill - $paid, 2),
        ];
    }
    $book['kind'] = $kind;
    if (!isset($book['bill'])) {
        $book['bill'] = $bill;
    }
    return $book;
}

function ame_purchase_memo_wa_text(array $memo, array $book, string $shop_name): string
{
    $kind = ame_purchase_memo_paper_kind($memo);
    $as_customer = $kind === 'purchase_customer';
    $partyRow = ame_purchase_memo_party($memo);
    $party = (string) ($partyRow['name'] ?? '');
    $bill = round((float) ($book['bill'] ?? $memo['invoice_total'] ?? 0), 2);
    $paid = round((float) ($book['paid'] ?? 0), 2);
    $total = round((float) ($book['total'] ?? 0), 2);
    if ($total < -0.009) {
        $dueLine = $as_customer
            ? ('কাস্টমার পাবে: ৳' . number_format(abs($total), 2))
            : ('অগ্রিম জমা: ৳' . number_format(abs($total), 2));
    } elseif ($total > 0.009) {
        $dueLine = $as_customer
            ? ('দোকান পাবে: ৳' . number_format($total, 2))
            : ('দোকান দেবে: ৳' . number_format($total, 2));
    } else {
        $dueLine = 'হিসাব সমান: ৳0.00';
    }
    return $shop_name . "\n"
        . 'ক্রয় মেমো: #' . ($memo['memo_no'] ?? '') . "\n"
        . 'তারিখ: ' . ($memo['bought_at'] ?? '') . "\n"
        . ($as_customer ? 'কাস্টমার: ' : 'সাপ্লায়ার: ') . $party . "\n"
        . 'মোট ক্রয়: ৳' . number_format($bill, 2) . "\n"
        . 'পরিশোধ: ৳' . number_format($paid, 2) . "\n"
        . $dueLine . "\n"
        . 'ধন্যবাদ!';
}

function ame_purchase_memo_render_sheet($db, array $memo): void
{
    global $shop;
    $h = 'ame_purchase_memo_h';
    $shop_name = function_exists('shop_name') ? shop_name() : (string) ($shop['shop_name'] ?? 'APONSHOP');
    $shop_phone = function_exists('shop_phone') ? (string) shop_phone() : (string) ($shop['shop_phone'] ?? '');
    $shop_addr = function_exists('shop_address') ? (string) shop_address() : (string) ($shop['shop_address'] ?? '');
    $book = ame_purchase_memo_paper_book($memo);
    $bill = round((float) ($book['bill'] ?? $memo['invoice_total'] ?? 0), 2);
    $paid = round((float) ($book['paid'] ?? 0), 2);
    $goods = round((float) ($memo['goods_total'] ?? $bill), 2);
    $is_cust = ame_purchase_memo_paper_kind($memo) === 'purchase_customer';
    $partyRow = ame_purchase_memo_party($memo);
    $party = (string) ($partyRow['name'] ?? '');
    $phone = (string) ($partyRow['phone'] ?? '');
    $code = (string) ($partyRow['code'] ?? '');
    $footer = 'ধন্যবাদ, আবার আসবেন!';
    if (function_exists('get_invoice_settings') && $db instanceof mysqli) {
        $inv = get_invoice_settings($db);
        if (is_array($inv) && trim((string) ($inv['footer_note'] ?? '')) !== '') {
            $footer = (string) $inv['footer_note'];
        }
    }
    $adj = is_array($memo['adjustments'] ?? null) ? $memo['adjustments'] : [];
    ?>
    <div id="memo-container" class="memo-wrapper" data-ame-capture="clone">
        <div class="memo-head">
            <?php echo function_exists('shop_print_logo_tag') ? shop_print_logo_tag(56) : ''; ?>
            <h1 class="memo-shop-name"><?php echo $h($shop_name); ?></h1>
            <?php if ($shop_addr !== ''): ?>
            <p class="memo-shop-desc"><?php echo $h($shop_addr); ?></p>
            <?php endif; ?>
            <?php if ($shop_phone !== ''): ?>
            <p class="memo-shop-phone">📞 <?php echo $h($shop_phone); ?></p>
            <?php endif; ?>
            <div class="memo-badge">ক্রয় মেমো</div>
        </div>

        <div class="memo-meta-grid">
            <div class="memo-meta-item">
                <span class="lbl">মেমো নম্বর</span>
                <span class="val">#<?php echo $h($memo['memo_no'] ?? ''); ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl">তারিখ ও সময়</span>
                <span class="val"><?php echo $h($memo['bought_at'] ?? ''); ?></span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl"><?php echo $is_cust ? 'কাস্টমার' : 'সাপ্লায়ার'; ?></span>
                <span class="val">
                    <?php echo $h($party); ?>
                    <?php if ($is_cust): ?>
                    <span style="background:#E0F2FE;color:#0369A1;padding:1px 6px;border-radius:4px;font-size:11px;margin-left:4px;font-weight:700;">কাস্টমার থেকে ক্রয়</span>
                    <?php endif; ?>
                    <?php echo $code !== '' ? ' (' . $h($code) . ')' : ''; ?>
                </span>
            </div>
            <div class="memo-meta-item">
                <span class="lbl">মোবাইল</span>
                <span class="val"><?php echo $phone !== '' ? $h($phone) : '—'; ?></span>
            </div>
        </div>

        <table class="memo-table">
            <thead>
                <tr>
                    <th style="width:28px;" class="tc">নং</th>
                    <th>বিবরণ</th>
                    <th style="width:48px;" class="tc">পরিমাণ</th>
                    <th style="width:70px;" class="tr">দর</th>
                    <th style="width:80px;" class="tr">মোট</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $items = is_array($memo['items'] ?? null) ? $memo['items'] : [];
            if ($items === []): ?>
                <tr><td colspan="5" class="tc" style="padding:12px;color:#6B7280;">কোনো পণ্য তালিকাভুক্ত নেই</td></tr>
            <?php else:
                $sl = 1;
                foreach ($items as $row):
                    $qty = $row['qty'] ?? '';
                    $free = (float) ($row['free_qty'] ?? 0);
            ?>
                <tr>
                    <td class="tc"><?php echo $sl++; ?></td>
                    <td>
                        <span style="font-weight:600;"><?php echo $h($row['name'] ?? ''); ?></span>
                        <?php if ($free > 0.009): ?>
                        <span style="display:block;font-size:10px;color:#64748B;">ফ্রি <?php echo $h($row['free_qty']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="tc"><?php echo $h($qty); ?></td>
                    <td class="tr"><?php echo number_format((float) ($row['rate'] ?? 0), 2); ?></td>
                    <td class="tr" style="font-weight:700;"><?php echo number_format((float) ($row['line_total'] ?? 0), 2); ?></td>
                </tr>
            <?php
                endforeach;
            endif; ?>
            </tbody>
        </table>

        <?php if (!empty($memo['expenses']) && is_array($memo['expenses'])): ?>
        <div style="margin:10px 0 6px;font-weight:700;color:#003F8F;font-size:12px;">অতিরিক্ত খরচ</div>
        <table class="memo-table">
            <thead>
                <tr>
                    <th>বিবরণ</th>
                    <th style="width:80px;" class="tr">টাকা</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($memo['expenses'] as $exp): ?>
                <tr>
                    <td><?php echo $h($exp['type'] ?? ''); ?></td>
                    <td class="tr" style="font-weight:700;"><?php echo number_format((float) ($exp['amount'] ?? 0), 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="memo-summary">
            <div class="memo-sum-row">
                <span>পণ্য মোট:</span>
                <span>৳<?php echo number_format($goods, 2); ?></span>
            </div>
            <?php
            $disc = (float) ($adj['discount_amount'] ?? 0);
            $round = (float) ($adj['rounding'] ?? 0);
            $special = (float) ($adj['special_discount'] ?? 0);
            if (abs($disc) > 0.009): ?>
            <div class="memo-sum-row" style="color:#DC2626;">
                <span>ডিসকাউন্ট<?php echo !empty($adj['discount_percent']) ? ' (' . (float) $adj['discount_percent'] . '%)' : ''; ?>:</span>
                <span>−৳<?php echo number_format(abs($disc), 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if (abs($round) > 0.009): ?>
            <div class="memo-sum-row">
                <span>রাউন্ডিং:</span>
                <span><?php echo $round < 0 ? '−' : ''; ?>৳<?php echo number_format(abs($round), 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if (abs($special) > 0.009): ?>
            <div class="memo-sum-row" style="color:#DC2626;">
                <span>স্পেশাল ডিসকাউন্ট:</span>
                <span>−৳<?php echo number_format(abs($special), 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="memo-sum-row bold" style="font-size:13px;color:#003F8F;border-top:1px solid #E2E8F0;padding-top:4px;">
                <span>ইনভয়েস মোট:</span>
                <span>৳<?php echo number_format($bill, 2); ?></span>
            </div>
            <div class="memo-sum-row highlight">
                <span>পরিশোধিত টাকা:</span>
                <span>৳<?php echo number_format($paid, 2); ?></span>
            </div>
            <?php echo ame_memo_paper_rows_html($book); ?>
        </div>

        <div class="memo-foot">
            <?php if (trim((string) ($memo['account_name'] ?? '')) !== '' && $paid > 0.009): ?>
            <p style="margin:0 0 3px;">পেমেন্ট মাধ্যম: <b><?php echo $h($memo['account_name']); ?></b></p>
            <?php endif; ?>
            <p style="margin:0 0 3px;font-weight:600;color:#374151;"><?php echo $h($footer); ?></p>
            <?php echo function_exists('ame_operator_stamp_html') ? ame_operator_stamp_html($db, (int) ($memo['created_by'] ?? 0)) : ''; ?>
        </div>
    </div>
    <?php
}
